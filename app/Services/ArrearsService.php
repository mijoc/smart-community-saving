<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\Contribution;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArrearsService
{
    public function run(?int $groupId = null): array
    {
        $today = Carbon::today();
        $stats = [
            'evaluated'       => 0,
            'newly_overdue'   => 0,
            'fees_applied'    => 0,
            'arrears_opened'  => 0,
            'arrears_updated' => 0,
        ];

        $query = Contribution::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereDate('due_on', '<', $today)
            ->with(['schedule', 'group.rules']);

        if ($groupId) $query->where('group_id', $groupId);

        $query->chunkById(200, function ($chunk) use (&$stats, $today) {
            foreach ($chunk as $c) {
                $this->evaluateOne($c, $today, $stats);
            }
        });

        Log::info('vsla.arrears.applied', $stats);
        return $stats;
    }

    protected function evaluateOne(Contribution $c, Carbon $today, array &$stats): void
    {
        $stats['evaluated']++;

        DB::transaction(function () use ($c, $today, &$stats) {
            $wasOverdue = $c->status === 'overdue';

            $outstanding = max(0, (float) $c->expected_amount + (float) $c->late_fee_amount - (float) $c->paid_amount);
            if ($outstanding <= 0) return;

            // Fee policy: schedule-level override first (only when > 0),
            // otherwise fall back to group rule, then global config default.
            // Use ?: (Elvis) not ?? so that a 0-value schedule column still
            // falls through to the group rule instead of suppressing fees.
            // Use ?-> (null-safe) because manually-created contributions may
            // have no linked schedule (contribution_schedule_id = null).
            $pct  = (float) ($c->schedule?->late_fee_pct  ?: $c->group->rule('late_fee_pct',  config('vsla.late_fee_pct')));
            $flat = (float) ($c->schedule?->late_fee_flat ?: $c->group->rule('late_fee_flat', 0));

            $arrear = Arrear::firstOrNew(['contribution_id' => $c->id]);
            $isNew  = ! $arrear->exists;

            if ($isNew) {
                $arrear->fill([
                    'group_id'         => $c->group_id,
                    'member_id'        => $c->member_id,
                    'first_overdue_on' => $today->toDateString(),
                    'late_fee_applied' => 0,
                    'status'           => 'open',
                ]);
                $stats['arrears_opened']++;
            } else {
                $stats['arrears_updated']++;
            }

            // ── Per-period penalty cap ────────────────────────────────────────
            // Charge the late fee at most ONCE per billing period elapsed since
            // due_on.  Two modes, selected by the group rule "penalty_on_penalty":
            //
            // STANDARD (penalty_on_penalty = false, default):
            //   fee/period = flat + E × r   (E = expected_amount, constant base)
            //   total cap  = N × fee/period  (linear)
            //
            // COMPOUND (penalty_on_penalty = true) — "interest of interest":
            //   fee/period = flat + (E + already_charged) × r  (base grows)
            //   total cap  = flat×N + E × ((1+r)^N − 1)       (geometric)
            //
            //   Example: E=50,000 r=5% N=3 → cap = 7,881.25
            //     Run 1: fee = 50,000 × 5% = 2,500  → total 2,500
            //     Run 2: fee = 52,500 × 5% = 2,625  → total 5,125
            //     Run 3: fee = 55,125 × 5% = 2,756.25 → total 7,881.25 (cap hit)
            //
            // Using the geometric cap guarantees the engine stops adding fees
            // once N complete periods have compounded — no unbounded growth.
            if ($pct > 0 || $flat > 0) {
                $dueOn          = Carbon::parse($c->due_on);
                $frequency      = $c->schedule?->frequency ?? 'monthly';
                $periodsElapsed = $this->periodsElapsed($dueOn, $today, $frequency);

                $alreadyCharged   = (float) $c->late_fee_amount;
                $E                = (float) $c->expected_amount;

                $penaltyOnPenalty = (bool) $c->group->rule('penalty_on_penalty', false);

                if ($penaltyOnPenalty && $pct > 0) {
                    // Geometric compound: cap = flat×N + E×((1+r)^N − 1)
                    $r             = $pct / 100;
                    $maxAllowedFee = round($flat * $periodsElapsed + $E * (pow(1 + $r, $periodsElapsed) - 1), 2);
                    // This period's fee grows on accumulated charges
                    $feePerPeriod  = round($flat + ($E + $alreadyCharged) * $r, 2);
                } else {
                    // Standard: flat fee every period on original amount
                    $feePerPeriod  = round($flat + ($E * $pct / 100), 2);
                    $maxAllowedFee = $periodsElapsed * $feePerPeriod;
                }

                if ($alreadyCharged < $maxAllowedFee) {
                    $fee = min($feePerPeriod, $maxAllowedFee - $alreadyCharged);

                    if ($fee > 0) {
                        $c->late_fee_amount       = round($alreadyCharged + $fee, 2);
                        $arrear->late_fee_applied = (float) $arrear->late_fee_applied + $fee;
                        $stats['fees_applied']++;
                    }
                }
            }

            $c->status = 'overdue';
            if (! $wasOverdue) $stats['newly_overdue']++;
            $c->save();

            $arrear->outstanding_amount = max(0, (float) $c->expected_amount + (float) $c->late_fee_amount - (float) $c->paid_amount);
            $arrear->days_overdue       = Carbon::parse($c->due_on)->diffInDays($today);
            $arrear->last_evaluated_on  = $today->toDateString();
            $arrear->save();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Loan late-fee engine
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Apply late fees to overdue flat loans in the given group (or all groups).
     *
     * Flat loans have a fixed due_on date.  When today > due_on and outstanding
     * is still > 0, we charge the group's loan_late_fee_pct once per billing
     * period elapsed since due_on (monthly cadence), capped identically to the
     * contribution arrears engine.
     *
     * When the group rule "penalty_on_penalty" is enabled, the fee base is the
     * current outstanding balance (principal + prior late fees), so the penalty
     * compounds each period.  When disabled, the fee base is the original
     * principal only.
     *
     * The accumulated late fee is tracked in loans.late_fee_amount and added
     * directly to loans.outstanding so the normal repayment flow handles it.
     *
     * Returns stats array: evaluated, fees_applied.
     */
    public function runLoanLateFees(?int $groupId = null): array
    {
        $today = Carbon::today();
        $stats = ['evaluated' => 0, 'fees_applied' => 0];

        $query = Loan::query()
            ->whereIn('status', ['disbursed', 'repaying'])
            ->where('interest_model', 'flat')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today)
            ->where('outstanding', '>', 0)
            ->with(['group.rules']);

        if ($groupId) $query->where('group_id', $groupId);

        $query->chunkById(200, function ($chunk) use (&$stats, $today) {
            foreach ($chunk as $loan) {
                $this->evaluateLoanLateFee($loan, $today, $stats);
            }
        });

        Log::info('vsla.loan_late_fees.applied', $stats);
        return $stats;
    }

    protected function evaluateLoanLateFee(Loan $loan, Carbon $today, array &$stats): void
    {
        $stats['evaluated']++;

        $pct  = (float) $loan->group->rule('loan_late_fee_pct', 0);
        $flat = (float) $loan->group->rule('loan_late_fee_flat', 0);

        if ($pct <= 0 && $flat <= 0) return;

        DB::transaction(function () use ($loan, $today, $pct, $flat, &$stats) {
            $dueOn          = Carbon::parse($loan->due_on);
            $periodsElapsed = $this->periodsElapsed($dueOn, $today, 'monthly');

            if ($periodsElapsed <= 0) return;

            $alreadyCharged   = (float) $loan->late_fee_amount;
            $P                = (float) $loan->principal;   // original principal, constant base
            $penaltyOnPenalty = (bool) $loan->group->rule('penalty_on_penalty', false);

            if ($penaltyOnPenalty && $pct > 0) {
                // Geometric compound: cap = flat×N + P×((1+r)^N − 1)
                $r             = $pct / 100;
                $maxAllowedFee = round($flat * $periodsElapsed + $P * (pow(1 + $r, $periodsElapsed) - 1), 2);
                $feePerPeriod  = round($flat + ($P + $alreadyCharged) * $r, 2);
            } else {
                // Standard: flat fee every period on original principal
                $feePerPeriod  = round($flat + ($P * $pct / 100), 2);
                $maxAllowedFee = $periodsElapsed * $feePerPeriod;
            }

            if ($feePerPeriod <= 0 || $alreadyCharged >= $maxAllowedFee) return;

            $fee = min($feePerPeriod, $maxAllowedFee - $alreadyCharged);

            if ($fee > 0) {
                $loan->late_fee_amount = round($alreadyCharged + $fee, 2);
                $loan->outstanding     = round((float) $loan->outstanding + $fee, 2);
                $loan->save();
                $stats['fees_applied']++;
            }
        });
    }

    /**
     * How many penalty charges are allowed for this contribution.
     *
     * Rule: the FIRST penalty fires immediately on the first overdue day (+1),
     * then ONE additional charge per complete billing period that elapses.
     * This means a contribution overdue for 2 full months gets 3 penalties:
     * one at month 0 (immediate), one at month 1, one at month 2.
     *
     * Using +1 (not plain diffInMonths) ensures the daily scheduler never
     * charges more than one fee per period even when run every day.
     */
    protected function periodsElapsed(Carbon $dueOn, Carbon $today, string $frequency): int
    {
        if ($today->lte($dueOn)) return 0;

        return match ($frequency) {
            'weekly'      => (int) $dueOn->diffInWeeks($today) + 1,
            'fortnightly' => (int) floor($dueOn->diffInDays($today) / 14) + 1,
            'quarterly'   => (int) floor($dueOn->diffInMonths($today) / 3) + 1,
            default       => (int) $dueOn->diffInMonths($today) + 1, // monthly + unknown
        };
    }

    public function recomputeFor(Contribution $c): void
    {
        $arrear = Arrear::firstWhere('contribution_id', $c->id);
        if (! $arrear) return;

        $outstanding = max(0, (float) $c->expected_amount + (float) $c->late_fee_amount - (float) $c->paid_amount);

        if ($outstanding <= 0) {
            $arrear->status = 'cleared';
        } elseif ($outstanding < (float) $c->expected_amount + (float) $c->late_fee_amount) {
            $arrear->status = 'partially_cleared';
        }

        $arrear->outstanding_amount  = $outstanding;
        $arrear->days_overdue        = $c->due_on ? Carbon::parse($c->due_on)->diffInDays(Carbon::today()) : $arrear->days_overdue;
        $arrear->last_evaluated_on   = Carbon::today()->toDateString();
        $arrear->save();
    }
}
