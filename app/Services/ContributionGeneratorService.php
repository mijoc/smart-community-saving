<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\ContributionSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContributionGeneratorService
{
    public function run(?int $groupId = null, ?Carbon $asOf = null): array
    {
        $today = $asOf ?? Carbon::today();

        $query = ContributionSchedule::query()
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->with(['group.activeMembers']);

        if ($groupId) $query->where('group_id', $groupId);

        $stats = ['schedules' => 0, 'created' => 0, 'advanced' => 0];

        $query->orderBy('id')->each(function (ContributionSchedule $sched) use (&$stats, $today) {
            $stats['schedules']++;

            $next = $sched->next_due_on
                ? Carbon::parse($sched->next_due_on)
                : Carbon::parse($sched->start_date);

            // generate every period whose due date is on or before today
            while ($next->lte($today)) {
                if ($sched->end_date && $next->gt(Carbon::parse($sched->end_date))) {
                    break;
                }

                $created = $this->generatePeriod($sched, $next);
                $stats['created'] += $created;
                $stats['advanced']++;

                $next = $sched->advance($next);
            }

            $sched->next_due_on       = $next->toDateString();
            $sched->last_generated_on = $today->toDateString();
            $sched->saveQuietly();
        });

        Log::info('vsla.contributions.generated', $stats);
        return $stats;
    }

    protected function generatePeriod(ContributionSchedule $sched, Carbon $dueOn): int
    {
        $periodStart = $dueOn->copy();
        $periodEnd   = $sched->periodEndFor($periodStart);

        $count = 0;

        DB::transaction(function () use ($sched, $dueOn, $periodStart, $periodEnd, &$count) {
            foreach ($sched->group->activeMembers as $member) {
                $exists = Contribution::where('group_id', $sched->group_id)
                    ->where('member_id', $member->id)
                    ->where('contribution_schedule_id', $sched->id)
                    ->where('period_start', $periodStart->toDateString())
                    ->where('type', $sched->type)
                    ->exists();

                if ($exists) continue;

                Contribution::create([
                    'group_id'                 => $sched->group_id,
                    'member_id'                => $member->id,
                    'contribution_schedule_id' => $sched->id,
                    'type'                     => $sched->type,
                    'expected_amount'          => $sched->amount,
                    'paid_amount'              => 0,
                    'late_fee_amount'          => 0,
                    'period_start'             => $periodStart->toDateString(),
                    'period_end'               => $periodEnd->toDateString(),
                    // Members have until the END of the period (plus the
                    // configured grace days) to pay — not until grace_days
                    // after the period STARTS.
                    'due_on'                   => $periodEnd->copy()->addDays((int) $sched->grace_days)->toDateString(),
                    'status'                   => 'pending',
                ]);
                $count++;
            }
        });

        return $count;
    }
}
