<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInterestAccrual;
use App\Models\LoanRepayment;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * TreasuryService
 * ---------------
 * Single source of truth for "where is the group's money?" and
 * "what is each member worth / what do they owe?" computations.
 *
 * Two main entry points:
 *  - groupSummary(...)  – consolidated group wealth view
 *  - memberSummary(...) – per-member equity, debt and projected share-out
 *
 * Both honour the active-group / accessible-groups scoping pattern that
 * the rest of the controllers use.
 */
class TreasuryService
{
    /**
     * Build the consolidated wealth picture for a single group (when
     * $groupId is given) or for every group the user can see.
     *
     * Cash on hand
     *   + member payments (savings, social fund, fines, late fees, …)
     *   + loan repayments (principal + interest portions)
     *   + cashbook income (donations, bank interest, grants, …)
     *   − loan principal disbursed (money that left the till)
     *   − cashbook expenses
     *
     * Receivables
     *   = principal outstanding on open loans  (asset – we expect it back)
     *   + interest still to be earned on open loans
     *   + open arrears (overdue contributions + late fees)
     *
     * Total fund = cash on hand + principal receivable
     * (we exclude future interest and arrears from the "fund" because they
     *  haven't been collected yet, but show them separately so the picture
     *  is complete).
     */
    public function groupSummary(?int $groupId, ?Collection $accessibleIds): array
    {
        $scope = fn ($q, string $col = 'group_id') => $this->applyScope($q, $col, $groupId, $accessibleIds);

        // -------- Cash inflows --------
        $memberPayments  = (float) $scope(Payment::query())->sum('amount');
        $loanRepayments  = (float) $scope(LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id'), 'loans.group_id')
            ->sum('loan_repayments.amount');
        $cashbookIncome       = (float) $scope(CashbookEntry::query()->where('type', 'income'))->sum('amount');
        $cashbookIncomeForExp = (float) $scope(CashbookEntry::query()->where('type', 'income')->where('category', '!=', 'balance_adjustment'))->sum('amount');

        // -------- Cash outflows --------
        $loansDisbursed  = (float) $scope(Loan::query()
            ->whereIn('status', ['disbursed', 'repaying', 'paid', 'defaulted', 'written_off']))
            ->sum('principal');
        $cashbookExpense = (float) $scope(CashbookEntry::query()->where('type', 'expense'))->sum('amount');

        $cashOnHand = round(
            $memberPayments + $loanRepayments + $cashbookIncome
            - $loansDisbursed - $cashbookExpense,
            2
        );

        // -------- Loan portfolio --------
        $openLoanQuery = $scope(Loan::query()->whereIn('status', ['disbursed', 'repaying']));

        // Only subtract repayments whose loan is still active ('disbursed'/'repaying').
        // 'paid' loans are already excluded from the principal sum above, so
        // including their repayments in the deduction would double-subtract them.
        $principalOutstanding = (float) (clone $openLoanQuery)->sum('principal')
            - (float) $scope(LoanRepayment::query()
                ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
                ->whereIn('loans.status', ['disbursed', 'repaying']), 'loans.group_id')
                ->sum('loan_repayments.principal_portion');
        $principalOutstanding = max(0, round($principalOutstanding, 2));

        $interestEarnedToDate = (float) $scope(LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id'), 'loans.group_id')
            ->sum('loan_repayments.interest_portion');

        $interestReceivable = (float) (clone $openLoanQuery)->sum('total_interest')
            - (float) $scope(LoanRepayment::query()
                ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
                ->whereIn('loans.status', ['disbursed', 'repaying']), 'loans.group_id')
                ->sum('loan_repayments.interest_portion');
        $interestReceivable = max(0, round($interestReceivable, 2));

        $loansCount = (clone $openLoanQuery)->count();

        // -------- Arrears & late-fee income --------
        $openArrears = (float) $scope(Arrear::query()->where('status', 'open'))->sum('outstanding_amount');

        // Late fees collected ≈ late_fee_amount on fully-paid contributions.
        $lateFeesCollected = (float) $scope(Contribution::query()->where('status', 'paid'))->sum('late_fee_amount');

        // -------- Aggregates --------
        // "Group profit" per the new business rule: every contribution the
        // group has booked (savings, social fund, fines, late fees, etc.)
        // counts toward the profit pool, even if the member has not paid yet.
        // Only fully-waived contributions are excluded.
        $expectedContributionsAll = (float) $scope(Contribution::query()
            ->where('status', '!=', 'waived'))
            ->sum('expected_amount');

        // -------- Total expected (every receivable the group has booked) --------
        // The big "all money the group expects to ever have collected"
        // figure, paid or still pending. We deliberately exclude
        // loan_repayment contribution rows here because the principal
        // portion of those is just money returning to the till (not new
        // income — it was already disbursed) and the interest portion is
        // captured separately via `loans.total_interest`. Adding both
        // would double-count.
        $expectedNonLoan = (float) $scope(Contribution::query()
            ->where('status', '!=', 'waived')
            ->where('type', '!=', 'loan_repayment'))
            ->sum('expected_amount');

        // Late-fee penalties stacked on top of any non-waived contribution.
        $lateFeesBooked = (float) $scope(Contribution::query()
            ->where('status', '!=', 'waived'))
            ->sum('late_fee_amount');

        // Total interest the group expects on every loan it has ever
        // approved/disbursed (rejected loans don't count).
        $loanInterestExpected = (float) $scope(Loan::query()
            ->whereIn('status', ['approved', 'disbursed', 'repaying', 'paid', 'defaulted', 'written_off']))
            ->sum('total_interest');

        // Attendance fines that have been charged (paid + still due).
        $attendanceFinesCharged = (float) $this->applyScope(
            MeetingAttendance::query()
                ->join('meetings', 'meetings.id', '=', 'meeting_attendances.meeting_id'),
            'meetings.group_id', $groupId, $accessibleIds
        )->sum('meeting_attendances.fine_amount');

        // Balance-adjustment entries affect cash position but are excluded from P&L.
        // Computed here (before totalExpectedAll) so expenses can be netted out.
        $nonProfitExpense = (float) $scope(CashbookEntry::query()
            ->where('type', 'expense')
            ->whereIn('category', \App\Models\CashbookEntry::NON_PROFIT_CATEGORIES))->sum('amount');

        // Operating expenses reduce what the group actually expects to have at
        // share-out time. Balance-adjustment entries are excluded (they are
        // cash-neutral from a P&L perspective).
        $operatingExpense = $cashbookExpense - $nonProfitExpense;

        $totalExpectedAll = round(
            $expectedNonLoan
            + $lateFeesBooked
            + $loanInterestExpected
            + $cashbookIncomeForExp
            + $attendanceFinesCharged
            - $operatingExpense,
            2
        );

        $totalIncome   = round($expectedContributionsAll + $interestEarnedToDate + $lateFeesCollected + $cashbookIncomeForExp, 2);
        $totalExpense  = round($cashbookExpense - $nonProfitExpense, 2);
        $netProfit     = round($totalIncome - $totalExpense, 2);

        // Dashboard-matching profit: penalties accrued (all contributions, not just paid)
        // + interest accrued (LoanInterestAccrual) + cashbook income.
        // This is the same formula shown in the Group Profit card on the dashboard
        // and must be used as the base for member equity / share-out calculations.
        $penaltiesAccrued = (float) $scope(Contribution::query())->sum('late_fee_amount');
        $interestAccrued  = (float) LoanInterestAccrual::query()
            ->whereHas('loan', function ($q) use ($groupId, $accessibleIds) {
                if ($groupId)       $q->where('group_id', $groupId);
                elseif ($accessibleIds) $q->whereIn('group_id', $accessibleIds);
                else                $q->whereRaw('1 = 0');
            })->sum('interest_amount');
        $realizedProfit = max(0, round($penaltiesAccrued + $interestAccrued + $cashbookIncome, 2));

        $totalGroupFund = round($cashOnHand + $principalOutstanding, 2);

        // -------- Member equity total (sum of "savings"-type payments) --------
        $memberEquityTotal = (float) $scope(Payment::query()
            ->join('contributions', 'contributions.id', '=', 'payments.contribution_id')
            ->where('contributions.type', 'savings'), 'payments.group_id')
            ->sum('payments.amount');

        return [
            'cash_on_hand'           => $cashOnHand,
            'principal_outstanding'  => $principalOutstanding,
            'interest_receivable'    => $interestReceivable,
            'interest_earned'        => round($interestEarnedToDate, 2),
            'late_fees_collected'    => round($lateFeesCollected, 2),
            'open_arrears'           => round($openArrears, 2),
            'open_loans_count'       => $loansCount,

            'cashbook_income'        => round($cashbookIncome, 2),
            'cashbook_expense'       => round($cashbookExpense, 2),
            'member_payments'        => round($memberPayments, 2),
            'loan_repayments_total'  => round($loanRepayments, 2),
            'loans_disbursed_total'  => round($loansDisbursed, 2),

            'expected_contributions' => round($expectedContributionsAll, 2),
            'total_expected_all'     => $totalExpectedAll,
            'total_income'           => $totalIncome,
            'total_expense'          => $totalExpense,
            'net_profit'             => $netProfit,
            'realized_profit'        => $realizedProfit,

            'total_group_fund'       => $totalGroupFund,
            'member_equity_total'    => round($memberEquityTotal, 2),
        ];
    }

    /**
     * Per-member equity, debt, and projected share-out for one group.
     * (A member can belong to several groups; we always compute one group
     * at a time so the numbers actually mean something.)
     */
    public function memberSummary(Member $member, int $groupId, ?array $precomputedGroupSummary = null): array
    {
        // -------- Equity / contributions paid --------
        $savingsPaid = (float) Payment::query()
            ->where('payments.member_id', $member->id)
            ->where('payments.group_id', $groupId)
            ->join('contributions', 'contributions.id', '=', 'payments.contribution_id')
            ->where('contributions.type', 'savings')
            ->sum('payments.amount');

        $socialFundPaid = (float) Payment::query()
            ->where('payments.member_id', $member->id)
            ->where('payments.group_id', $groupId)
            ->join('contributions', 'contributions.id', '=', 'payments.contribution_id')
            ->where('contributions.type', 'social_fund')
            ->sum('payments.amount');

        $finesPaid = (float) Payment::query()
            ->where('payments.member_id', $member->id)
            ->where('payments.group_id', $groupId)
            ->join('contributions', 'contributions.id', '=', 'payments.contribution_id')
            ->whereIn('contributions.type', ['fine', 'late_fee'])
            ->sum('payments.amount');

        $totalContributed = (float) Payment::query()
            ->where('member_id', $member->id)
            ->where('group_id', $groupId)
            ->sum('amount');

        // -------- Outstanding debts --------
        $openLoans = Loan::with('repayments')
            ->where('member_id', $member->id)
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying'])
            ->get();

        $loanPrincipalOutstanding = 0.0;
        $loanInterestOutstanding  = 0.0;
        $loanRows = [];
        foreach ($openLoans as $loan) {
            $paidPrincipal = (float) $loan->repayments->sum('principal_portion');
            $paidInterest  = (float) $loan->repayments->sum('interest_portion');
            $principalLeft = max(0, round((float) $loan->principal      - $paidPrincipal, 2));
            $interestLeft  = max(0, round((float) $loan->total_interest - $paidInterest,  2));

            $loanPrincipalOutstanding += $principalLeft;
            $loanInterestOutstanding  += $interestLeft;

            $loanRows[] = [
                'loan'           => $loan,
                'principal_left' => $principalLeft,
                'interest_left'  => $interestLeft,
                'total_left'     => round($principalLeft + $interestLeft, 2),
            ];
        }

        $openArrears = Arrear::with('contribution')
            ->where('member_id', $member->id)
            ->where('group_id', $groupId)
            ->where('status', 'open')
            ->get();
        $arrearsOutstanding = (float) $openArrears->sum('outstanding_amount');

        // -------- All unpaid contribution balances (any type) --------
        // Per the business rule: every pending / partial / overdue
        // contribution the member owes — savings, social fund, fines,
        // late-fees, loan repayments, etc. — is part of their outstanding
        // debt to the group.
        $unpaidContribRows = Contribution::query()
            ->where('member_id', $member->id)
            ->where('group_id', $groupId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->get(['id', 'type', 'expected_amount', 'paid_amount', 'late_fee_amount']);

        $contributionsDue = 0.0;
        $finesDue         = 0.0;
        $lateFeesDue      = 0.0;
        foreach ($unpaidContribRows as $row) {
            $balance = max(0, (float) $row->expected_amount + (float) $row->late_fee_amount - (float) $row->paid_amount);
            $contributionsDue += $balance;
            if (in_array($row->type, ['fine'], true))      $finesDue    += $balance;
            if (in_array($row->type, ['late_fee'], true))  $lateFeesDue += $balance;
        }
        $contributionsDue = round($contributionsDue, 2);

        // -------- Outstanding attendance fines (penalties) --------
        // SQLite has no GREATEST(); compute the per-row balance in PHP and
        // floor each one at zero before summing.
        $attendanceFinesDue = (float) MeetingAttendance::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_attendances.meeting_id')
            ->where('meeting_attendances.member_id', $member->id)
            ->where('meetings.group_id', $groupId)
            ->get(['meeting_attendances.fine_amount', 'meeting_attendances.paid_amount'])
            ->sum(fn ($r) => max(0, (float) $r->fine_amount - (float) $r->paid_amount));
        $attendanceFinesDue = round($attendanceFinesDue, 2);

        // Total debt = loans + every unpaid contribution + attendance fines.
        // (We deliberately do NOT add $arrearsOutstanding on top of
        // $contributionsDue — an arrear is the same obligation already
        // captured by the underlying contribution's balance, and adding
        // both would double-count it.)
        $totalDebt = round(
            $loanPrincipalOutstanding
            + $loanInterestOutstanding
            + $contributionsDue
            + $attendanceFinesDue,
            2
        );

        // -------- Group-level figures for share-out --------
        $groupSummary = $precomputedGroupSummary ?? $this->groupSummary($groupId, null);
        $groupSavings = (float) $groupSummary['member_equity_total'];
        $groupProfit  = (float) $groupSummary['realized_profit'];

        // Total expected = the dashboard's "TOTAL EXPECTED" figure:
        // all booked inflows (contributions, loan repayments, interest, cashbook, fines).
        $groupTotalExpected = (float) $groupSummary['total_expected_all'];

        // Count active members via contributions (members are linked through contributions,
        // not via group_id on the members table directly).
        $activeMemberCount = (int) Contribution::query()
            ->where('group_id', $groupId)
            ->distinct('member_id')
            ->count('member_id');
        $activeMemberCount = max(1, $activeMemberCount);

        // Equal share: every member gets 1 / memberCount of the group total.
        // Savings-ratio is kept for informational profit-share display only.
        $shareRatio       = $groupSavings > 0 ? $savingsPaid / $groupSavings : 0.0;
        $equalShareRatio  = 1 / $activeMemberCount;
        $profitShare      = round($shareRatio * $groupProfit, 2);

        // -------- Projected share-out --------
        // Formula: Total Expected ÷ number of members − member's debts.
        $shareOfTotalExpected = round($equalShareRatio * $groupTotalExpected, 2);
        $grossPayout          = $shareOfTotalExpected;   // before debt deduction
        $projectedPayout      = round($shareOfTotalExpected - $totalDebt, 2);

        $netAmount = $projectedPayout;   // alias kept for backward compatibility

        // -------- Lifetime totals (loans ever issued, ever repaid) --------
        $loansEverBorrowed = (float) Loan::query()
            ->where('member_id', $member->id)
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid', 'defaulted', 'written_off'])
            ->sum('principal');
        $interestEverPaid = (float) LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('loans.member_id', $member->id)
            ->where('loans.group_id', $groupId)
            ->sum('loan_repayments.interest_portion');

        return [
            // Equity
            'savings_paid'        => round($savingsPaid, 2),
            'social_fund_paid'    => round($socialFundPaid, 2),
            'fines_paid'          => round($finesPaid, 2),
            'total_contributed'   => round($totalContributed, 2),
            'share_ratio_pct'     => round($shareRatio * 100, 2),

            // Debts
            'loan_principal_due'   => round($loanPrincipalOutstanding, 2),
            'loan_interest_due'    => round($loanInterestOutstanding, 2),
            'arrears_due'          => round($arrearsOutstanding, 2),
            'contributions_due'    => $contributionsDue,
            'fines_due'            => round($finesDue, 2),
            'late_fees_due'        => round($lateFeesDue, 2),
            'attendance_fines_due' => $attendanceFinesDue,
            'total_debt'           => $totalDebt,
            'loan_rows'            => $loanRows,
            'arrear_rows'          => $openArrears,

            // Profit / share-out
            'group_profit'             => round($groupProfit, 2),
            'profit_share'             => $profitShare,
            'member_profit'            => $profitShare,
            'gross_payout'             => $grossPayout,
            'projected_payout'         => $projectedPayout,
            'share_of_total_expected'  => $shareOfTotalExpected,
            'net_amount'               => $netAmount,
            // Equal-share fields
            'active_member_count'      => $activeMemberCount,
            'equal_share_ratio_pct'    => round($equalShareRatio * 100, 4),
            // Savings-based ratio (informational only)
            'share_ratio_pct'          => round($shareRatio * 100, 2),

            // Group context (so members understand where their slice comes from)
            'group_total_expected'   => $groupTotalExpected,
            'group_total_savings'    => round($groupSavings, 2),

            // Lifetime
            'loans_ever_borrowed' => round($loansEverBorrowed, 2),
            'interest_ever_paid'  => round($interestEverPaid, 2),
        ];
    }

    /**
     * Apply the same active-group / accessible-groups scoping the
     * controllers use, but in a service-friendly way.
     */
    protected function applyScope($query, string $column, ?int $groupId, ?Collection $accessibleIds)
    {
        if ($groupId)            return $query->where($column, $groupId);
        if ($accessibleIds)      return $query->whereIn($column, $accessibleIds);
        return $query;
    }
}
