<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeetingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Meeting::class, 'meeting');
    }

    /** List all meetings in the active group with filters. */
    public function index(Request $request)
    {
        $q = Meeting::query()
            ->with(['group:id,name,currency', 'creator:id,name'])
            ->withCount([
                'attendances as present_count' => fn ($a) => $a->where('status', 'present'),
                'attendances as late_count'    => fn ($a) => $a->where('status', 'late'),
                'attendances as absent_count'  => fn ($a) => $a->where('status', 'absent'),
                'attendances as excused_count' => fn ($a) => $a->where('status', 'excused'),
            ])
            ->withSum('attendances as fines_total', 'fine_amount')
            ->withSum('attendances as fines_paid',  'paid_amount');

        $this->scopeToActiveGroup($q);

        if ($s = $request->string('status')->toString())  $q->where('status', $s);
        if ($from = $request->string('from')->toString()) $q->whereDate('meeting_date', '>=', $from);
        if ($to   = $request->string('to')->toString())   $q->whereDate('meeting_date', '<=', $to);

        $meetings = $q->orderByDesc('meeting_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('meetings.index', [
            'meetings' => $meetings,
        ]);
    }

    public function create()
    {
        $group = $this->activeGroup();
        if (! $group) {
            return redirect()->route('groups.select')
                ->with('error', 'Pick a group before scheduling a meeting.');
        }

        return view('meetings.create', [
            'group'       => $group,
            'lateFine'    => (float) $group->rule('attendance_late_fine',   500),
            'absentFine'  => (float) $group->rule('attendance_absent_fine', 2000),
            'meetingDay'  => $group->rule('meeting_day', null),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id'     => ['required', 'exists:groups,id'],
            'meeting_date' => ['required', 'date'],
            'title'        => ['nullable', 'string', 'max:160'],
            'agenda'       => ['nullable', 'string'],
            'late_fine'    => ['required', 'numeric', 'min:0'],
            'absent_fine'  => ['required', 'numeric', 'min:0'],
        ]);

        $groupId = (int) $data['group_id'];
        if (! auth()->user()->canAccessGroup($groupId)) {
            abort(403, 'You cannot schedule meetings in that group.');
        }

        $group = Group::findOrFail($groupId);

        $meeting = DB::transaction(function () use ($data, $group) {
            $meeting = Meeting::create([
                'group_id'     => $group->id,
                'meeting_date' => $data['meeting_date'],
                'title'        => $data['title']
                    ?? ('Meeting · '.Carbon::parse($data['meeting_date'])->format('M j, Y')),
                'agenda'       => $data['agenda'] ?? null,
                'late_fine'    => $data['late_fine'],
                'absent_fine'  => $data['absent_fine'],
                'status'       => 'open',
                'created_by'   => auth()->id(),
            ]);

            // Pre-create one attendance row per active member, defaulting
            // everyone to "present" with a 0 fine. The roll-call form on
            // the show page just edits these rows.
            $members = $group->activeMembers()->orderBy('full_name')->get();
            foreach ($members as $m) {
                MeetingAttendance::create([
                    'meeting_id'  => $meeting->id,
                    'member_id'   => $m->id,
                    'status'      => 'present',
                    'fine_amount' => 0,
                    'paid_amount' => 0,
                    'recorded_by' => auth()->id(),
                ]);
            }
            return $meeting;
        });

        ActivityLogger::log(
            groupId:     $meeting->group_id,
            type:        'meeting.created',
            description: "scheduled meeting on ".$meeting->meeting_date->format('M j, Y'),
            subject:     $meeting,
            icon:        'calendar-plus',
            color:       'blue',
        );

        return redirect()->route('meetings.show', $meeting)
            ->with('status', 'Meeting created. Now mark attendance below.');
    }

    public function show(Meeting $meeting)
    {
        $meeting->load([
            'group:id,name,currency',
            'creator:id,name',
            'attendances.member:id,first_name,last_name,full_name,member_no,phone,photo_path',
            'attendances.recorder:id,name',
        ]);

        // Sort by member name for the roll-call.
        $rows = $meeting->attendances->sortBy(fn ($a) => $a->member->full_name)->values();

        return view('meetings.show', [
            'meeting' => $meeting,
            'rows'    => $rows,
        ]);
    }

    /**
     * Bulk save the attendance roll-call. One POST records every member's
     * status + recomputes their fine_amount based on the meeting's fines.
     */
    public function recordAttendance(Request $request, Meeting $meeting)
    {
        $this->authorize('update', $meeting);

        if (! $meeting->isOpen()) {
            return back()->with('error', 'This meeting has been closed.');
        }

        $data = $request->validate([
            'rows'              => ['required', 'array', 'min:1'],
            'rows.*.id'         => ['required', 'integer', 'exists:meeting_attendances,id'],
            'rows.*.status'     => ['required', 'in:present,late,absent,excused'],
            'rows.*.notes'      => ['nullable', 'string', 'max:255'],
            'rows.*.fine_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $changed = 0;
        DB::transaction(function () use ($data, $meeting, &$changed) {
            foreach ($data['rows'] as $row) {
                $att = MeetingAttendance::where('meeting_id', $meeting->id)
                    ->where('id', $row['id'])
                    ->first();
                if (! $att) continue;

                // Default fine derived from meeting + status, but allow an override.
                $defaultFine = match ($row['status']) {
                    'late'   => (float) $meeting->late_fine,
                    'absent' => (float) $meeting->absent_fine,
                    default  => 0.0,
                };
                $fine = isset($row['fine_override']) && $row['fine_override'] !== ''
                    ? (float) $row['fine_override']
                    : $defaultFine;

                // Don't let fine drop below what the member has already paid in —
                // that would create a negative outstanding balance.
                $fine = max($fine, (float) $att->paid_amount);

                $att->update([
                    'status'      => $row['status'],
                    'notes'       => $row['notes'] ?? null,
                    'fine_amount' => $fine,
                    'recorded_by' => auth()->id(),
                ]);
                $changed++;
            }
        });

        ActivityLogger::log(
            groupId:     $meeting->group_id,
            type:        'meeting.attendance.recorded',
            description: "updated attendance for the ".$meeting->meeting_date->format('M j, Y')." meeting",
            subject:     $meeting,
            icon:        'checklist',
            color:       'azure',
            data:        ['rows' => $changed],
        );

        return redirect()->route('meetings.show', $meeting)
            ->with('status', "Attendance saved ({$changed} members).");
    }

    /**
     * Record a fine payment from a single member. Writes a cashbook income
     * entry so the group's cash on hand reflects the deposit.
     */
    public function payFine(Request $request, Meeting $meeting, MeetingAttendance $attendance)
    {
        abort_if($attendance->meeting_id !== $meeting->id, 404);
        $this->authorize('recordPayment', $meeting);

        $data = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'method'  => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'paid_on' => ['required', 'date'],
            'notes'   => ['nullable', 'string', 'max:255'],
        ]);

        $outstanding = (float) $attendance->fine_amount - (float) $attendance->paid_amount;
        if ($outstanding <= 0) {
            return back()->with('error', 'No outstanding fine to pay for this member.');
        }
        if ($data['amount'] > $outstanding + 0.0001) {
            return back()->with('error',
                'Payment exceeds the outstanding fine ('.number_format($outstanding, 2).').');
        }

        DB::transaction(function () use ($data, $meeting, $attendance) {
            $newPaid = (float) $attendance->paid_amount + (float) $data['amount'];
            $attendance->update([
                'paid_amount' => $newPaid,
                'paid_on'     => $newPaid >= (float) $attendance->fine_amount
                    ? $data['paid_on']
                    : $attendance->paid_on,
            ]);

            // Mirror the deposit into the cashbook so treasury / reports
            // pick it up as group income.
            $reference = $this->nextCashRef();
            CashbookEntry::create([
                'reference'    => $reference,
                'group_id'     => $meeting->group_id,
                'type'         => 'income',
                'category'     => 'attendance_fine',
                'amount'       => $data['amount'],
                'method'       => $data['method'],
                'channel_ref'  => null,
                'counterparty' => $attendance->member->full_name,
                'occurred_on'  => $data['paid_on'],
                'notes'        => 'Attendance fine — meeting '
                    .$meeting->meeting_date->format('Y-m-d')
                    .(! empty($data['notes']) ? ' · '.$data['notes'] : ''),
                'recorded_by'  => auth()->id(),
            ]);
        });

        ActivityLogger::log(
            groupId:     $meeting->group_id,
            type:        'meeting.fine.paid',
            description: "received attendance fine from {$attendance->member->full_name}",
            subject:     $meeting,
            icon:        'cash',
            color:       'green',
            data:        ['amount' => number_format((float) $data['amount'], 2)],
        );

        return redirect()->route('meetings.show', $meeting)
            ->with('status', 'Fine payment recorded.');
    }

    /** Close the meeting — read-only state. Reopen with the same action. */
    public function toggleStatus(Meeting $meeting)
    {
        $this->authorize('update', $meeting);
        $meeting->update([
            'status' => $meeting->isOpen() ? 'closed' : 'open',
        ]);

        ActivityLogger::log(
            groupId:     $meeting->group_id,
            type:        'meeting.'.$meeting->status,
            description: ($meeting->status === 'closed' ? 'closed' : 'reopened')
                .' the '.$meeting->meeting_date->format('M j, Y').' meeting',
            subject:     $meeting,
            icon:        $meeting->status === 'closed' ? 'lock' : 'lock-open',
            color:       $meeting->status === 'closed' ? 'red' : 'green',
        );

        return back()->with('status', 'Meeting marked as '.$meeting->status.'.');
    }

    public function destroy(Meeting $meeting)
    {
        ActivityLogger::log(
            groupId:     $meeting->group_id,
            type:        'meeting.deleted',
            description: 'removed meeting '.$meeting->meeting_date->format('M j, Y'),
            subject:     $meeting,
            icon:        'trash',
            color:       'red',
        );
        $meeting->delete();
        return redirect()->route('meetings.index')->with('status', 'Meeting removed.');
    }

    protected function nextCashRef(): string
    {
        do {
            $ref = 'IN-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (CashbookEntry::where('reference', $ref)->exists());
        return $ref;
    }
}
