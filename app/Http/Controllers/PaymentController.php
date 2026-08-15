<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\ContributionSchedule;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\ArrearsService;
use App\Services\PaymentRecorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contribution::class);

        $u             = auth()->user();
        $activeGroupId = session('active_group_id');

        // ── Members default to their own payment history (unless they tapped a
        //    specific member card on the picker grid, in which case let them through)
        if ($u->hasRole('member') && $u->member_id
            && $request->string('view')->toString() !== 'group'
            && ! $request->integer('member_id')
        ) {
            return $this->memberPayments($request, Member::find($u->member_id), selfView: true);
        }

        // ── A specific member is selected → show their payment history ────────
        if ($memberId = $request->integer('member_id')) {
            $member = Member::findOrFail($memberId);
            if ($activeGroupId && ! $member->groups()->where('groups.id', $activeGroupId)->exists()) {
                abort(403);
            }
            return $this->memberPayments($request, $member, selfView: false);
        }

        // ── Default: member-picker grid ───────────────────────────────────────
        $memberQuery = Member::query()
            ->with(['payments' => function ($q) use ($activeGroupId) {
                $q->when($activeGroupId, fn ($q) => $q->where('group_id', $activeGroupId))
                  ->select('id', 'member_id', 'amount', 'paid_on', 'method');
            }]);

        if ($activeGroupId) {
            $memberQuery->whereHas('groups', fn ($g) => $g->where('groups.id', $activeGroupId));
        } elseif (! $u->isSuperAdmin()) {
            $memberQuery->whereHas('groups', fn ($g) =>
                $g->whereIn('groups.id', $this->accessibleGroupOptions()->pluck('id'))
            );
        }

        $members = $memberQuery->orderBy('member_no')->get();

        return view('payments.index', [
            'members'       => $members,
            'groups'        => $this->accessibleGroupOptions(),
            'activeGroupId' => $activeGroupId,
        ]);
    }

    // ── Shared helper: build + return a single member's payment list ──────────
    private function memberPayments(Request $request, ?Member $member, bool $selfView)
    {
        $activeGroupId = session('active_group_id');

        $base = Payment::query();
        $this->scopeToActiveGroup($base);

        if ($member) {
            $base->where('member_id', $member->id);
        }

        if ($from = $request->string('from')->toString()) $base->whereDate('paid_on', '>=', $from);
        if ($to   = $request->string('to')->toString())   $base->whereDate('paid_on', '<=', $to);
        if ($ref  = $request->string('search')->toString()) {
            $base->where('reference', 'like', "%$ref%");
        }

        // Filtered totals across all pages
        $filteredTotals = (clone $base)
            ->selectRaw("
                COUNT(*) as row_count,
                COALESCE(SUM(amount), 0) as sum_amount
            ")
            ->first();

        $payments = (clone $base)
            ->with(['group:id,name', 'receiver:id,name', 'contribution:id,type,period_start,period_end'])
            ->orderByDesc('paid_on')
            ->paginate(25)
            ->withQueryString();

        // All-time header stats for this member in this group
        $stats = null;
        if ($member && $activeGroupId) {
            $stats = Payment::where('member_id', $member->id)
                ->where('group_id', $activeGroupId)
                ->selectRaw("
                    COUNT(*) as total_payments,
                    COALESCE(SUM(amount), 0) as total_amount,
                    MAX(paid_on) as last_paid_on
                ")
                ->first();
        }

        return view('payments.member', [
            'member'         => $member,
            'payments'       => $payments,
            'filteredTotals' => $filteredTotals,
            'stats'          => $stats,
            'groups'         => $this->accessibleGroupOptions(),
            'selfView'       => $selfView,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Contribution::class);

        $groups   = $this->accessibleGroupOptions();
        $activeId = session('active_group_id');

        // When coming from a contribution row, pre-resolve group + member + amount.
        $preselectContribId  = 0;
        $preselectGroupId    = $activeId ?? 0;
        $preselectMemberId   = 0;
        $preselectAmount     = '';

        if ($cid = $request->integer('contribution_id')) {
            $contrib = Contribution::find($cid);
            if ($contrib && auth()->user()->canAccessGroup($contrib->group_id)) {
                $preselectContribId = $contrib->id;
                $preselectGroupId   = $contrib->group_id;
                $preselectMemberId  = $contrib->member_id;
                // Outstanding balance as the default amount
                $preselectAmount = max(0,
                    (float) $contrib->expected_amount
                    + (float) $contrib->late_fee_amount
                    - (float) $contrib->paid_amount
                );
            }
        }

        $members = Member::query()
            ->when(! auth()->user()->isSuperAdmin() || $activeId, function ($q) use ($groups, $activeId) {
                $ids = $activeId ? [$activeId] : $groups->pluck('id')->all();
                $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $ids));
            })
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'member_no']);

        return view('payments.create', [
            'groups'                    => $groups,
            'members'                   => $members,
            'preselect_contribution_id' => $preselectContribId,
            'preselect_group_id'        => $preselectGroupId,
            'preselect_member_id'       => $preselectMemberId,
            'preselect_amount'          => $preselectAmount,
        ]);
    }

    public function store(Request $request, PaymentRecorderService $svc)
    {
        $this->authorize('create', Contribution::class);

        $data = $request->validate([
            'group_id'        => ['required', 'exists:groups,id'],
            'member_id'       => ['required', 'exists:members,id'],
            'contribution_id' => ['nullable', 'exists:contributions,id'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'     => ['nullable', 'string', 'max:120'],
            'paid_on'         => ['required', 'date'],
            'reference'       => ['nullable', 'string', 'max:60'],
            'notes'           => ['nullable', 'string'],
        ]);

        if (! auth()->user()->canAccessGroup((int) $data['group_id'])) {
            abort(403, 'You cannot record payments in that group.');
        }

        $payment = $svc->record($data, auth()->id());

        $member = Member::find($payment->member_id);
        ActivityLogger::log(
            groupId: $payment->group_id,
            type: 'payment.created',
            description: "received a payment from {$member?->full_name}",
            subject: $payment,
            icon: 'cash',
            color: 'green',
            data: [
                'amount'    => number_format((float) $payment->amount, 2),
                'method'    => $payment->method,
                'reference' => $payment->reference,
            ],
        );

        return redirect()->route('payments.index')->with('status', "Payment {$payment->reference} recorded.");
    }

    public function show(Payment $payment)
    {
        $this->authorize('viewAny', Contribution::class);
        if (! auth()->user()->canAccessGroup($payment->group_id)) abort(403);
        $payment->load(['group', 'member', 'contribution', 'receiver']);
        return view('payments.show', compact('payment'));
    }

    /**
     * Delete a recorded payment (super_admin only — guarded by PaymentPolicy).
     *
     * Reverses every side-effect the original `record()` call wrote:
     *   1. If the payment was applied to a contribution, decrement
     *      `paid_amount`, refresh the contribution's status, and recompute
     *      arrears for that contribution.
     *   2. Delete every passbook entry whose source was this payment.
     *   3. Delete the payment row itself.
     *   4. Log a `payment.deleted` activity for the audit trail.
     *
     * Wrapped in a single transaction so a failure leaves nothing half-done.
     */
    public function destroy(Payment $payment, ArrearsService $arrears)
    {
        $this->authorize('delete', $payment);
        if (! auth()->user()->canAccessGroup($payment->group_id)) abort(403);

        $snapshot = [
            'reference'       => $payment->reference,
            'group_id'        => $payment->group_id,
            'member'          => $payment->member?->full_name,
            'amount'          => number_format((float) $payment->amount, 2),
            'method'          => $payment->method,
            'paid_on'         => $payment->paid_on?->toDateString(),
            'contribution_id' => $payment->contribution_id,
        ];

        DB::transaction(function () use ($payment, $arrears) {
            // 1. Reverse the contribution allocation, if any.
            if ($payment->contribution_id) {
                $c = Contribution::lockForUpdate()->find($payment->contribution_id);
                if ($c) {
                    $c->paid_amount = max(0, (float) $c->paid_amount - (float) $payment->amount);
                    $c->refreshStatus();
                    $c->save();
                    $arrears->recomputeFor($c);
                }
            }

            // 2. Wipe the passbook entries that were written from this payment.
            PassbookEntry::where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->delete();

            // 3. Drop the payment.
            $payment->delete();
        });

        ActivityLogger::log(
            groupId: $snapshot['group_id'],
            type: 'payment.deleted',
            description: "deleted payment {$snapshot['reference']} ({$snapshot['amount']}) for {$snapshot['member']}",
            subject: null,
            icon: 'trash',
            color: 'red',
            data: $snapshot,
        );

        return redirect()->route('payments.index')
            ->with('status', "Payment {$snapshot['reference']} deleted and reversed.");
    }

    /**
     * Super-admin alternative to destroy(): delete the payment AND force the
     * linked contribution back to "Pending" status (instead of letting the
     * status auto-recompute to "overdue" when the due date is in the past).
     *
     * Same reversal logic as destroy() — only the final status differs.
     */
    public function markPending(Payment $payment, ArrearsService $arrears)
    {
        $this->authorize('delete', $payment);
        if (! auth()->user()->canAccessGroup($payment->group_id)) abort(403);

        $snapshot = [
            'reference'       => $payment->reference,
            'group_id'        => $payment->group_id,
            'member'          => $payment->member?->full_name,
            'amount'          => number_format((float) $payment->amount, 2),
            'method'          => $payment->method,
            'paid_on'         => $payment->paid_on?->toDateString(),
            'contribution_id' => $payment->contribution_id,
        ];

        DB::transaction(function () use ($payment, $arrears) {
            if ($payment->contribution_id) {
                $c = Contribution::lockForUpdate()->find($payment->contribution_id);
                if ($c) {
                    $c->paid_amount = max(0, (float) $c->paid_amount - (float) $payment->amount);
                    // Force the contribution back to "Pending" regardless of
                    // its due date — the user explicitly asked for this.
                    $c->status  = 'pending';
                    $c->paid_on = null;
                    $c->save();
                    $arrears->recomputeFor($c);
                }
            }

            PassbookEntry::where('source_type', Payment::class)
                ->where('source_id', $payment->id)
                ->delete();

            $payment->delete();
        });

        ActivityLogger::log(
            groupId: $snapshot['group_id'],
            type: 'payment.marked_pending',
            description: "deleted payment {$snapshot['reference']} ({$snapshot['amount']}) for {$snapshot['member']} and moved contribution to Pending",
            subject: null,
            icon: 'clock',
            color: 'yellow',
            data: $snapshot,
        );

        return redirect()->route('payments.index')
            ->with('status', "Payment {$snapshot['reference']} deleted; contribution moved to Pending.");
    }

    /**
     * Bulk contribution-day collection page. Pick a group + a schedule and
     * see every active member's outstanding contribution side-by-side, then
     * record everyone's payment in a single submit.
     */
    public function bulk(Request $request)
    {
        $this->authorize('create', Contribution::class);

        $groups   = $this->accessibleGroupOptions();
        [$group, $schedule, $rows] = $this->loadBulkRoster($request);
        $activeId = $group?->id ?? 0;

        $schedules = $activeId
            ? ContributionSchedule::where('group_id', $activeId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('payments.bulk', [
            'groups'    => $groups,
            'activeId'  => $activeId,
            'schedules' => $schedules,
            'schedule'  => $schedule,
            'rows'      => $rows,
        ]);
    }

    /**
     * Process the bulk payment form. Each non-skipped row with amount > 0
     * becomes a single payment routed through PaymentRecorderService so
     * it allocates against the contribution and writes the passbook entry.
     */
    public function storeBulk(Request $request, PaymentRecorderService $svc)
    {
        $this->authorize('create', Contribution::class);

        $data = $request->validate([
            'group_id'             => ['required', 'exists:groups,id'],
            'schedule_id'          => ['required', 'exists:contribution_schedules,id'],
            'paid_on'              => ['required', 'date'],
            'method'               => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'          => ['nullable', 'string', 'max:120'],
            'rows'                 => ['required', 'array', 'min:1'],
            'rows.*.member_id'     => ['required', 'integer', 'exists:members,id'],
            'rows.*.contribution_id' => ['nullable', 'integer', 'exists:contributions,id'],
            'rows.*.amount'        => ['nullable', 'numeric', 'min:0'],
            'rows.*.method'        => ['nullable', 'in:cash,mobile_money,bank,cheque,other'],
            'rows.*.skip'          => ['nullable', 'in:0,1'],
        ]);

        $groupId = (int) $data['group_id'];
        if (! auth()->user()->canAccessGroup($groupId)) {
            abort(403, 'You cannot record payments in that group.');
        }

        $schedule = ContributionSchedule::where('id', $data['schedule_id'])
            ->where('group_id', $groupId)
            ->firstOrFail();

        $paid    = 0;
        $skipped = 0;
        $errors  = [];
        $totalAmt = 0.0;

        DB::transaction(function () use ($data, $schedule, $svc, &$paid, &$skipped, &$errors, &$totalAmt) {
            foreach ($data['rows'] as $i => $row) {
                if (! empty($row['skip']) && $row['skip'] === '1') { $skipped++; continue; }

                $amount = (float) ($row['amount'] ?? 0);
                if ($amount <= 0)               { $skipped++; continue; }

                // The row's contribution_id must belong to the chosen schedule
                // and the chosen member — otherwise silently skip and report.
                $cid = $row['contribution_id'] ?? null;
                if ($cid) {
                    $contrib = Contribution::find($cid);
                    if (! $contrib
                        || $contrib->contribution_schedule_id !== $schedule->id
                        || (int) $contrib->member_id !== (int) $row['member_id']) {
                        $errors[] = "Row #".($i+1).": contribution mismatch, skipped.";
                        $skipped++;
                        continue;
                    }
                }

                $svc->record([
                    'group_id'        => $data['group_id'],
                    'member_id'       => $row['member_id'],
                    'contribution_id' => $cid,
                    'amount'          => $amount,
                    'method'          => $row['method'] ?? $data['method'],
                    'channel_ref'     => $data['channel_ref'] ?? null,
                    'paid_on'         => $data['paid_on'],
                    'notes'           => 'Bulk collection · '.$schedule->name,
                ], auth()->id());

                $paid++;
                $totalAmt += $amount;
            }
        });

        ActivityLogger::log(
            groupId:     $groupId,
            type:        'payment.bulk',
            description: "ran bulk collection for {$schedule->name} ({$paid} paid · ".
                         number_format($totalAmt, 2).")",
            icon:        'cash-banknote',
            color:       'green',
            data:        ['paid' => $paid, 'skipped' => $skipped, 'total' => $totalAmt],
        );

        return redirect()
            ->route('payments.bulk', ['group_id' => $groupId, 'schedule_id' => $schedule->id])
            ->with('status', "Bulk collection done: {$paid} paid, {$skipped} skipped, total "
                .number_format($totalAmt, 2).".")
            ->withErrors($errors);
    }

    /**
     * Print-friendly collection sheet for a group + schedule. Same roster
     * the bulk page shows, but laid out for paper with signature columns.
     * The user prints this from the browser (or saves as PDF).
     */
    public function bulkSheet(Request $request)
    {
        $this->authorize('create', Contribution::class);
        [$group, $schedule, $rows] = $this->loadBulkRoster($request);
        if (! $group || ! $schedule) {
            return redirect()->route('payments.bulk')
                ->with('status', 'Pick a group and a schedule first.');
        }

        return view('payments.bulk_sheet', compact('group', 'schedule', 'rows'));
    }

    /**
     * CSV export of the same roster — useful if you want to fill amounts
     * in a spreadsheet on the way to the meeting and import later.
     */
    public function bulkSheetCsv(Request $request)
    {
        $this->authorize('create', Contribution::class);
        [$group, $schedule, $rows] = $this->loadBulkRoster($request);
        if (! $group || ! $schedule) {
            return redirect()->route('payments.bulk')
                ->with('status', 'Pick a group and a schedule first.');
        }

        $filename = 'collection-sheet_'.\Illuminate\Support\Str::slug($group->name).'_'
            .\Illuminate\Support\Str::slug($schedule->name).'_'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['#', 'Member name', 'Member no', 'Phone', 'Period start', 'Period end',
                'Due on', 'Expected', 'Already paid', 'Outstanding', 'Amount paid (fill in)',
                'Method (cash/mm/bank/cheque/other)', 'Signature']);
            foreach ($rows as $i => $r) {
                $c = $r->contribution;
                fputcsv($out, [
                    $i + 1,
                    $r->member->full_name,
                    $r->member->member_no,
                    $r->member->phone,
                    $c?->period_start?->format('Y-m-d'),
                    $c?->period_end?->format('Y-m-d'),
                    $c?->due_on?->format('Y-m-d'),
                    $c ? number_format((float) $c->expected_amount + (float) $c->late_fee_amount, 2, '.', '') : '',
                    $c ? number_format((float) $c->paid_amount, 2, '.', '') : '',
                    number_format((float) $r->balance, 2, '.', ''),
                    '', '', '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared loader used by `bulk()`, `bulkSheet()` and `bulkSheetCsv()`.
     * Returns [Group|null, ContributionSchedule|null, Collection<row>].
     */
    protected function loadBulkRoster(Request $request): array
    {
        $activeId = (int) ($request->integer('group_id') ?: session('active_group_id'));
        if (! $activeId || ! auth()->user()->canAccessGroup($activeId)) {
            return [null, null, collect()];
        }

        $group = \App\Models\Group::find($activeId);
        $scheduleId = $request->integer('schedule_id');
        $schedule = $scheduleId
            ? ContributionSchedule::where('group_id', $activeId)->where('id', $scheduleId)->first()
            : null;
        if (! $schedule) return [$group, null, collect()];

        $members = $group->activeMembers()
            ->orderBy('full_name')
            ->get(['members.id', 'members.full_name', 'members.member_no', 'members.phone', 'members.photo_path']);

        $contribs = Contribution::where('contribution_schedule_id', $schedule->id)
            ->whereIn('member_id', $members->pluck('id'))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_on')
            ->get()
            ->groupBy('member_id');

        $rows = $members->map(function ($m) use ($contribs) {
            $c = optional($contribs->get($m->id))->first();
            $balance = $c
                ? max(0, (float) $c->expected_amount + (float) $c->late_fee_amount - (float) $c->paid_amount)
                : 0;
            return (object) ['member' => $m, 'contribution' => $c, 'balance' => $balance];
        });

        return [$group, $schedule, $rows];
    }

    public function lookupContributions(Request $request)
    {
        $this->authorize('viewAny', Contribution::class);
        $groupId = $request->integer('group_id');
        if (! auth()->user()->canAccessGroup($groupId)) abort(403);

        $contribs = Contribution::query()
            ->where('group_id', $groupId)
            ->where('member_id', $request->integer('member_id'))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_on')
            ->get(['id', 'type', 'expected_amount', 'paid_amount', 'late_fee_amount', 'due_on', 'period_start', 'period_end', 'status']);
        return response()->json($contribs);
    }
}
