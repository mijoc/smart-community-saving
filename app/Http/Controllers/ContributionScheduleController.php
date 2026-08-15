<?php

namespace App\Http\Controllers;

use App\Models\ContributionSchedule;
use App\Models\Group;
use App\Services\ContributionGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContributionScheduleController extends Controller
{
    public function index(Group $group)
    {
        $this->authorize('view', $group);
        return view('schedules.index', [
            'group'     => $group,
            'schedules' => $group->schedules()->orderBy('is_active', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function create(Group $group)
    {
        $this->authorize('update', $group);
        return view('schedules.create', compact('group'));
    }

    public function store(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $data = $this->validateData($request);
        $data['group_id']    = $group->id;
        $data['next_due_on'] = $data['start_date'];
        ContributionSchedule::create($data);
        return redirect()->route('groups.schedules.index', $group)->with('status', 'Schedule created.');
    }

    public function edit(Group $group, ContributionSchedule $schedule)
    {
        abort_if($schedule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        return view('schedules.edit', compact('group', 'schedule'));
    }

    public function update(Request $request, Group $group, ContributionSchedule $schedule)
    {
        abort_if($schedule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        $schedule->update($this->validateData($request));
        return redirect()->route('groups.schedules.index', $group)->with('status', 'Schedule updated.');
    }

    public function destroy(Group $group, ContributionSchedule $schedule)
    {
        abort_if($schedule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        $schedule->delete();
        return back()->with('status', 'Schedule removed.');
    }

    public function generate(Group $group, ContributionSchedule $schedule, ContributionGeneratorService $svc)
    {
        abort_if($schedule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        $r = $svc->run($group->id);
        return back()->with('status', "Generation run: {$r['created']} contributions created.");
    }

    public function resetPointer(Request $request, Group $group, ContributionSchedule $schedule)
    {
        abort_if($schedule->group_id !== $group->id, 404);
        $this->authorize('update', $group);

        $data = $request->validate([
            'reset_to'     => ['required', 'date'],
            'delete_ahead' => ['nullable', 'boolean'],
        ]);

        $resetTo = Carbon::parse($data['reset_to']);

        if (!empty($data['delete_ahead'])) {
            $schedule->contributions()
                ->whereIn('status', ['pending', 'partial'])
                ->where('period_start', '>=', $resetTo->toDateString())
                ->delete();
        }

        $schedule->next_due_on      = $resetTo->toDateString();
        $schedule->last_generated_on = null;
        $schedule->saveQuietly();

        return back()->with('status', "Schedule pointer reset to {$resetTo->toDateString()}. Run the generator (or click Catch up) to rebuild contributions from this date.");
    }

    public function catchUp(Group $group, ContributionGeneratorService $svc)
    {
        $this->authorize('update', $group);

        $contributionDue = $group->rule('contribution_due', 'today');

        $asOf = match (true) {
            str_contains((string) $contributionDue, 'end_of_month')   => Carbon::today()->endOfMonth(),
            str_contains((string) $contributionDue, 'start_of_month'),
            str_contains((string) $contributionDue, 'beginning_of_month') => Carbon::today()->startOfMonth(),
            default => Carbon::today(),
        };

        $r = $svc->run($group->id, $asOf);

        $msg = $r['created'] > 0
            ? "Catch-up complete: {$r['created']} contribution(s) created across {$r['schedules']} schedule(s) (up to {$asOf->toDateString()})."
            : "All schedules are already up to date (checked up to {$asOf->toDateString()}).";

        return back()->with('status', $msg);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'type'          => ['required', 'in:savings,social_fund,loan_repayment,fine,other'],
            'frequency'     => ['required', 'in:weekly,fortnightly,monthly,quarterly'],
            'amount'        => ['required', 'numeric', 'min:0'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
            'grace_days'    => ['required', 'integer', 'min:0', 'max:60'],
            'late_fee_pct'  => ['required', 'numeric', 'min:0', 'max:100'],
            'late_fee_flat' => ['required', 'numeric', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
    }
}
