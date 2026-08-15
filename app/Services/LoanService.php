<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanInterestAccrual;
use App\Models\LoanRepayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanService
{
    /**
     * Compute flat-interest totals for a loan (does not save).
     * Only used for the legacy flat model.
     */
    public function computeTotals(Loan $loan): array
    {
        $principal = (float) $loan->principal;
        $rate      = (float) $loan->interest_rate_pct / 100;
        $months    = (int) ($loan->term_months ?: 1);

        $interest  = round($principal * $rate * $months, 2);
        $repayable = round($principal + $interest, 2);

        return [
            'total_interest'  => $interest,
            'total_repayable' => $repayable,
        ];
    }

    public function approve(Loan $loan, int $approverId): Loan
    {
        if ($loan->isCompound()) {
            $loan->fill([
                'status'          => 'approved',
                'approved_on'     => now()->toDateString(),
                'approved_by'     => $approverId,
                'total_interest'  => 0,
                'total_repayable' => (float) $loan->principal,
                'outstanding'     => (float) $loan->principal,
            ])->save();
        } else {
            $totals = $this->computeTotals($loan);
            $loan->fill([
                'status'          => 'approved',
                'approved_on'     => now()->toDateString(),
                'approved_by'     => $approverId,
                'total_interest'  => $totals['total_interest'],
                'total_repayable' => $totals['total_repayable'],
                'outstanding'     => $totals['total_repayable'],
            ])->save();
        }

        return $loan;
    }

    public function reject(Loan $loan, ?string $reason): Loan
    {
        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);
        return $loan;
    }

    /**
     * Disburse a loan.
     *
     * @param  array $consolidateLoanIds  IDs of prior active loans whose outstanding
     *                                    balances should be rolled into this loan's
     *                                    starting principal (compound loans only).
     */
    public function disburse(Loan $loan, string $disbursedOn, array $consolidateLoanIds = []): Loan
    {
        if ($loan->status !== 'approved') {
            abort(422, 'Loan must be approved before it can be disbursed.');
        }

        $updates = [
            'status'       => 'disbursed',
            'disbursed_on' => $disbursedOn,
            'due_on'       => null,
        ];

        if (! $loan->isCompound() && $loan->term_months) {
            $updates['due_on'] = Carbon::parse($disbursedOn)
                ->addMonths((int) $loan->term_months)
                ->toDateString();
        }

        // Consolidate outstanding balances from prior loans (compound only).
        if ($loan->isCompound() && ! empty($consolidateLoanIds)) {
            $priorLoans = Loan::whereIn('id', $consolidateLoanIds)
                ->where('member_id', $loan->member_id)
                ->where('group_id',  $loan->group_id)
                ->whereIn('status',  ['disbursed', 'repaying'])
                ->get();

            if ($priorLoans->isNotEmpty()) {
                $priorOutstanding = $priorLoans->sum(fn ($l) => (float) $l->outstanding);
                $combinedPrincipal = round((float) $loan->principal + $priorOutstanding, 2);

                // Fold the rolled-over outstanding into principal so the new loan
                // is a single unified loan. prior_outstanding is kept purely for
                // the audit trail / info banner on the show page.
                $updates['principal']             = $combinedPrincipal;
                $updates['prior_outstanding']     = round($priorOutstanding, 2);
                $updates['consolidated_loan_ids'] = $priorLoans->pluck('id')->toArray();
                $updates['outstanding']           = $combinedPrincipal;

                // Close the prior loans as consolidated.
                Loan::whereIn('id', $priorLoans->pluck('id'))
                    ->update([
                        'status' => 'consolidated',
                        'notes'  => DB::raw("COALESCE(notes || ' | ', '') || 'Consolidated into {$loan->reference} on {$disbursedOn}'"),
                    ]);
            }
        }

        $loan->update($updates);

        // Backfill any missed monthly accruals when disbursement date is in the past.
        if ($loan->isCompound()) {
            $loan->refresh();
            $this->backfillMissingAccruals($loan);
        }

        return $loan;
    }

    /**
     * Accrue all missing monthly periods for a compound loan from the first
     * full month after disbursement up to (and including) the current month.
     * Runs periods in strict chronological order so compound interest is correct.
     * Idempotent: already-existing periods are skipped by accrueMonthlyInterest().
     *
     * Returns the number of new accrual rows created.
     */
    public function backfillMissingAccruals(Loan $loan): int
    {
        if (! $loan->isCompound() || ! $loan->disbursed_on) {
            return 0;
        }

        $firstPeriod   = Carbon::parse($loan->disbursed_on)->addMonthNoOverflow()->startOfMonth();
        $currentPeriod = now()->startOfMonth();
        $created       = 0;

        // Always accrue at least the first period immediately, even when the
        // loan was just disbursed this month (first period is "next month").
        $until = $firstPeriod->gt($currentPeriod) ? $firstPeriod->copy() : $currentPeriod->copy();

        $p = $firstPeriod->copy();
        while ($p->lte($until)) {
            $accrual = $this->accrueMonthlyInterest($loan, $p->copy());
            if ($accrual) {
                $loan->refresh(); // pick up updated outstanding before next iteration
                $created++;
            }
            $p->addMonthNoOverflow();
        }

        return $created;
    }

    /**
     * Submit or record a repayment.
     *
     * - If $pending = true  → member-submitted, status = 'pending', loan balance NOT touched.
     * - If $pending = false → staff-recorded,   status = 'approved', applies immediately.
     *
     * payment_type: full | interest_only | principal_only | partial
     * accrual_period: date string (first of month) when payment_type = interest_only
     */
    public function recordRepayment(Loan $loan, array $data, int $recorderId, bool $pending = false): LoanRepayment
    {
        if (! in_array($loan->status, ['disbursed', 'repaying'])) {
            abort(422, 'Repayments can only be recorded on disbursed loans.');
        }

        return DB::transaction(function () use ($loan, $data, $recorderId, $pending) {
            $loan        = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $amount      = (float) $data['amount'];
            $paymentType = $data['payment_type'] ?? 'full';

            if ($pending) {
                if ($loan->repayments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'amount' => 'A repayment for this loan is already waiting for approval.',
                    ]);
                }

                if ($amount > (float) $loan->outstanding) {
                    throw ValidationException::withMessages([
                        'amount' => 'The repayment cannot be greater than the current outstanding balance.',
                    ]);
                }
                // Member-submitted: don't touch the loan balance yet.
                // Portions will be recalculated on approval.
                $rep = LoanRepayment::create([
                    'loan_id'           => $loan->id,
                    'amount'            => $amount,
                    'principal_portion' => 0,
                    'interest_portion'  => 0,
                    'paid_on'           => $data['paid_on'],
                    'method'            => $data['method'] ?? 'cash',
                    'reference'         => $data['reference'] ?? null,
                    'recorded_by'       => $recorderId,
                    'notes'             => $data['notes'] ?? null,
                    'proof_file'        => $data['proof_file'] ?? null,
                    'status'            => 'pending',
                    'payment_type'      => $paymentType,
                    'accrual_period'    => $data['accrual_period'] ?? null,
                    'approved_by'       => null,
                    'approved_at'       => null,
                ]);
                return $rep;
            }

            // Staff-recorded: calculate portions and apply immediately.
            [$interestPortion, $principalPortion, $newOutstanding] =
                $this->calculatePortions($loan, $amount, $paymentType, $data['accrual_period'] ?? null);

            $rep = LoanRepayment::create([
                'loan_id'           => $loan->id,
                'amount'            => $amount,
                'principal_portion' => $principalPortion,
                'interest_portion'  => $interestPortion,
                'paid_on'           => $data['paid_on'],
                'method'            => $data['method'] ?? 'cash',
                'reference'         => $data['reference'] ?? null,
                'recorded_by'       => $recorderId,
                'notes'             => $data['notes'] ?? null,
                'proof_file'        => $data['proof_file'] ?? null,
                'status'            => 'approved',
                'payment_type'      => $paymentType,
                'accrual_period'    => $data['accrual_period'] ?? null,
                'approved_by'       => $recorderId,
                'approved_at'       => now(),
            ]);

            $loan->amount_repaid = round((float) $loan->amount_repaid + $amount, 2);
            $loan->outstanding   = $newOutstanding;
            $loan->status        = $newOutstanding <= 0 ? 'paid' : 'repaying';
            $loan->save();

            return $rep;
        });
    }

    /**
     * Approve a pending repayment: calculate portions and apply to loan balance.
     */
    public function approveRepayment(LoanRepayment $rep, int $approverId): LoanRepayment
    {
        if ($rep->status !== 'pending') {
            abort(422, 'Only pending repayments can be approved.');
        }

        $loan = $rep->loan;

        return DB::transaction(function () use ($rep, $loan, $approverId) {
            $amount = (float) $rep->amount;

            [$interestPortion, $principalPortion, $newOutstanding] =
                $this->calculatePortions($loan, $amount, $rep->payment_type, $rep->accrual_period?->toDateString());

            $rep->update([
                'status'            => 'approved',
                'principal_portion' => $principalPortion,
                'interest_portion'  => $interestPortion,
                'approved_by'       => $approverId,
                'approved_at'       => now(),
            ]);

            $loan->amount_repaid = round((float) $loan->amount_repaid + $amount, 2);
            $loan->outstanding   = $newOutstanding;
            $loan->status        = $newOutstanding <= 0 ? 'paid' : 'repaying';
            $loan->save();

            return $rep;
        });
    }

    /**
     * Reject a pending repayment without applying it.
     */
    public function rejectRepayment(LoanRepayment $rep, ?string $reason, int $rejectorId): LoanRepayment
    {
        if ($rep->status !== 'pending') {
            abort(422, 'Only pending repayments can be rejected.');
        }

        $rep->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
            'approved_by'      => $rejectorId,
            'approved_at'      => now(),
        ]);

        return $rep;
    }

    /**
     * Calculate interest/principal portions and new outstanding balance.
     * Returns [$interestPortion, $principalPortion, $newOutstanding].
     */
    private function calculatePortions(Loan $loan, float $amount, string $paymentType, ?string $accrualPeriod): array
    {
        if ($loan->isCompound()) {
            $unpaidInterest = $loan->unpaidAccruedInterest();

            switch ($paymentType) {
                case 'interest_only':
                    // Pay interest for a specific accrual month (capped at that month's amount)
                    $monthInterest = 0;
                    if ($accrualPeriod) {
                        $accrual = $loan->accruals()
                            ->whereDate('period', $accrualPeriod)
                            ->first();
                        $monthInterest = $accrual ? (float) $accrual->interest_amount : 0;
                    }
                    $interestPortion  = round(min($amount, $monthInterest ?: $unpaidInterest), 2);
                    $principalPortion = 0;
                    break;

                case 'principal_only':
                    $interestPortion  = 0;
                    $principalPortion = round($amount, 2);
                    break;

                case 'partial':
                    // Partial: apply to interest first, remainder to principal
                    $interestPortion  = round(min($unpaidInterest, $amount), 2);
                    $principalPortion = round($amount - $interestPortion, 2);
                    break;

                case 'full':
                default:
                    // Full: interest first (from unpaid accrued), rest to principal
                    $interestPortion  = round(min($unpaidInterest, $amount), 2);
                    $principalPortion = round($amount - $interestPortion, 2);
                    break;
            }

            $newOutstanding = max(0, round((float) $loan->outstanding - $amount, 2));
        } else {
            // Legacy flat model
            $interestRemaining = max(0, (float) $loan->total_interest
                - (float) $loan->approvedRepayments()->sum('interest_portion'));
            $interestPortion   = round(min($interestRemaining, $amount), 2);
            $principalPortion  = round($amount - $interestPortion, 2);
            $newOutstanding    = max(0, round((float) $loan->total_repayable - (float) $loan->amount_repaid - $amount, 2));
        }

        return [$interestPortion, $principalPortion, $newOutstanding];
    }

    /**
     * Accrue one month of compound interest on a single loan.
     * Idempotent: if an accrual for this period already exists, does nothing.
     */
    public function accrueMonthlyInterest(Loan $loan, ?Carbon $period = null): ?LoanInterestAccrual
    {
        if (! $loan->isCompound() || ! in_array($loan->status, ['disbursed', 'repaying'])) {
            return null;
        }

        $period = ($period ?? now())->copy()->startOfMonth();
        $periodStr = $period->toDateString();

        $exists = LoanInterestAccrual::where('loan_id', $loan->id)
            ->whereDate('period', $periodStr)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($loan, $periodStr) {
            $balanceBefore   = (float) $loan->outstanding;
            $rate            = (float) $loan->interest_rate_pct;
            $interestAmount  = round($balanceBefore * $rate / 100, 2);
            $balanceAfter    = round($balanceBefore + $interestAmount, 2);

            $accrual = LoanInterestAccrual::create([
                'loan_id'         => $loan->id,
                'period'          => $periodStr,
                'balance_before'  => $balanceBefore,
                'rate_pct'        => $rate,
                'interest_amount' => $interestAmount,
                'balance_after'   => $balanceAfter,
            ]);

            $loan->outstanding    = $balanceAfter;
            $loan->total_interest = round((float) $loan->total_interest + $interestAmount, 2);
            $loan->save();

            return $accrual;
        });
    }

    public function nextReference(): string
    {
        $n = (int) Loan::withTrashed()->max('id') + 1;
        do {
            $ref = 'L-' . str_pad($n, 5, '0', STR_PAD_LEFT);
            $exists = Loan::withTrashed()->where('reference', $ref)->exists();
            $n++;
        } while ($exists);
        return $ref;
    }
}
