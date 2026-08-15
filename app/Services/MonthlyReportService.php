<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportService
{
    /**
     * Build the full monthly report payload for one group + month.
     *
     * Returns a structured array with:
     *   header   – group, month label, generated_by/at
     *   members  – flat list of members included
     *   section_a, section_b, section_c, section_d – per the spec
     *   kpis     – treasurer dashboard cards
     */
    public function generate(Group $group, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd   = $month->copy()->endOfMonth();
        $prevEnd    = $monthStart->copy()->subDay()->endOfDay();
        $prevStart  = $monthStart->copy()->subMonth()->startOfMonth();

        // Cycle window for Section D (cycle-to-date). Falls back to year-to-date.
        $cycleStart = $group->cycle_starts_on
            ? Carbon::parse($group->cycle_starts_on)->startOfDay()
            : $monthStart->copy()->startOfYear();

        $members = Member::query()
            ->whereHas('groups', fn ($q) => $q->where('groups.id', $group->id))
            ->orderBy('member_no')
            ->get(['id', 'member_no', 'full_name', 'status']);

        $sectionA = $this->buildSectionA($group->id, $members, $prevStart, $prevEnd);
        $sectionB = $this->buildSectionB($group->id, $members, $monthStart, $monthEnd, $sectionA['by_member']);
        $sectionC = $this->buildSectionC($group->id, $monthStart, $monthEnd, $prevEnd, $sectionB['totals']);
        $sectionD = $this->buildSectionD($group->id, $members, $cycleStart, $monthEnd);

        // Extra KPIs
        $loanOutstandingNow  = $this->loanOutstandingByMemberAt($group->id, $monthEnd);
        $totalLoansOut       = (float) $loanOutstandingNow->sum();

        $totalPenalties = (float) Contribution::query()
            ->where('group_id', $group->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('late_fee_amount');

        $totalArrears = (float) Contribution::query()
            ->where('group_id', $group->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->selectRaw('SUM(expected_amount + late_fee_amount - paid_amount) AS t')
            ->value('t');

        $overdueMembers = Contribution::query()
            ->where('group_id', $group->id)
            ->where('status', 'overdue')
            ->distinct('member_id')
            ->count('member_id');

        $sectionE = $this->buildSectionE($group->id, $members, $monthStart, $monthEnd, $loanOutstandingNow);

        // Late fees collected in cash this month: contributions whose penalty was settled
        // (status=paid, paid_on within month, late_fee_amount > 0). Already included inside
        // contributions_collected but broken out here for the financial summary sub-line.
        $penaltiesCollectedMonth = (float) Contribution::query()
            ->where('group_id', $group->id)
            ->where('late_fee_amount', '>', 0)
            ->where('status', 'paid')
            ->whereBetween('paid_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('late_fee_amount');

        // Cycle-to-date cumulative contributions: all periods up to and including this month.
        $cumulRow = \App\Models\Contribution::where('group_id', $group->id)
            ->where('period_start', '<=', $monthEnd->toDateString())
            ->selectRaw("SUM(expected_amount) AS exp, SUM(paid_amount) AS paid,
                         COUNT(DISTINCT strftime('%Y-%m', period_start)) AS months")
            ->first();
        $cumulExpected   = (float) ($cumulRow->exp    ?? 0);
        $cumulCollected  = (float) ($cumulRow->paid   ?? 0);
        $cumulMonths     = (int)   ($cumulRow->months ?? 0);

        // Loan interest outstanding = accrued but unpaid interest on ALL active loans
        // (compound + flat).  We can't use SUM(outstanding - principal) because when a
        // member has repaid some principal, outstanding < principal and the term goes
        // negative (e.g. Jean Claude: outstanding=25k, principal=500k → -475k).
        // Correct formula: total outstanding − remaining principal still owed.
        $activeLoanBase = \App\Models\Loan::where('group_id', $group->id)
            ->whereIn('status', ['disbursed', 'repaying']);
        $outstandingTotal  = (float) (clone $activeLoanBase)->sum('outstanding');
        $principalTotal    = (float) (clone $activeLoanBase)->sum('principal');
        $principalRepaidNow = (float) LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('loans.group_id', $group->id)
            ->whereIn('loans.status', ['disbursed', 'repaying'])
            ->sum('loan_repayments.principal_portion');
        $principalStillOwed = max(0.0, $principalTotal - $principalRepaidNow);
        $interestReceivable = max(0.0, $outstandingTotal - $principalStillOwed);

        // ── Total Expected Income (cycle-to-date) ──────────────────────────────
        // Everything the group is entitled to receive: contributions + penalties
        // + loan interest accrued + cashbook income.
        $allContribExpected  = (float) \App\Models\Contribution::where('group_id', $group->id)
            ->where('period_start', '<=', $monthEnd->toDateString())
            ->sum('expected_amount');
        $allPenaltiesExpected = (float) \App\Models\Contribution::where('group_id', $group->id)
            ->where('period_start', '<=', $monthEnd->toDateString())
            ->sum('late_fee_amount');
        // Use loans.total_interest (pre-computed for all periods) rather than the sparse
        // LoanInterestAccrual table which only has 7 rows and understates expected interest.
        $allInterestExpected  = (float) \App\Models\Loan::where('group_id', $group->id)
            ->whereIn('status', ['approved', 'disbursed', 'repaying', 'paid', 'defaulted', 'written_off'])
            ->sum('total_interest');
        $allCashIncome = (float) \App\Models\CashbookEntry::where('group_id', $group->id)
            ->where('type', 'income')
            ->where('category', '!=', 'balance_adjustment')
            ->where('occurred_on', '<=', $monthEnd->toDateString())
            ->sum('amount');

        // What has actually been received against those expectations
        $allContribCollected  = (float) \App\Models\Contribution::where('group_id', $group->id)
            ->where('period_start', '<=', $monthEnd->toDateString())
            ->sum('paid_amount');
        $allPenaltiesCollected = (float) \App\Models\Contribution::where('group_id', $group->id)
            ->where('status', 'paid')
            ->where('period_start', '<=', $monthEnd->toDateString())
            ->sum('late_fee_amount');
        $allInterestCollected  = (float) \DB::table('loan_repayments')
            ->join('loans', 'loans.id', 'loan_repayments.loan_id')
            ->where('loans.group_id', $group->id)
            ->where('loan_repayments.paid_on', '<=', $monthEnd->toDateString())
            ->sum('loan_repayments.interest_portion');

        $totalExpectedIncome  = $allContribExpected + $allPenaltiesExpected + $allInterestExpected + $allCashIncome;
        $totalReceivedIncome  = $allContribCollected + $allPenaltiesCollected + $allInterestCollected + $allCashIncome;

        // Cycle-to-date cashbook expenses reduce the group's earned profit.
        // Balance-adjustment entries are excluded: they affect the cash position
        // (via TreasuryService) but are pure reconciliation items, not operating expenses.
        $cycleWithdrawals = (float) \App\Models\CashbookEntry::where('group_id', $group->id)
            ->where('type', 'expense')
            ->whereNotIn('category', \App\Models\CashbookEntry::NON_PROFIT_CATEGORIES)
            ->where('occurred_on', '<=', $monthEnd->toDateString())
            ->sum('amount');

        // Group profit = everything earned ON TOP of member contributions, minus withdrawals
        // (contributions are member capital — returned at share-out, not profit)
        // Profit = penalties accrued + loan interest accrued + other cashbook income − withdrawals
        $groupProfitExpected  = $allPenaltiesExpected + $allInterestExpected + $allCashIncome - $cycleWithdrawals;
        $groupProfitCollected = $allPenaltiesCollected + $allInterestCollected + $allCashIncome - $cycleWithdrawals;

        // ── Member profit share breakdown ──────────────────────────────────────
        // profit per share = group profit / total shares
        // each member's profit = their share_count × profit_per_share
        $memberShareRows = \DB::table('group_member as gm')
            ->join('members as m', 'm.id', 'gm.member_id')
            ->where('gm.group_id', $group->id)
            ->where('gm.is_active', 1)
            ->selectRaw('m.id, m.full_name, COALESCE(gm.share_count, 1) as share_count')
            ->orderBy('m.full_name')
            ->get();

        $totalGroupShares  = max(1, $memberShareRows->sum('share_count'));
        $profitPerShare    = $groupProfitExpected / $totalGroupShares;

        $memberProfitShares = $memberShareRows->map(fn ($r) => [
            'id'           => $r->id,
            'name'         => $r->full_name,
            'shares'       => (int) $r->share_count,
            'profit'       => round($profitPerShare * $r->share_count, 2),
        ])->values();

        $report = [
            'header' => [
                'group'         => $group,
                'month'         => $monthStart->copy(),
                'month_label'   => $monthStart->format('F Y'),
                'cycle_start'   => $cycleStart,
                'generated_by'  => auth()->user()?->name ?? 'System',
                'generated_at'  => now(),
                'currency'      => $group->currency ?? 'RWF',
            ],
            'members'   => $members,
            'section_a' => $sectionA,
            'section_b' => $sectionB,
            'section_c' => $sectionC,
            'section_d' => $sectionD,
            'section_e' => $sectionE,
            'kpis'      => [
                'opening_balance'           => $sectionC['opening_balance'],
                'contributions'             => $sectionC['contributions_collected'],
                // Expected = schedule amount × months × members (summed from contribution rows for this period)
                'expected_contributions'    => $sectionE['totals']['expected'],
                // Cycle-to-date cumulative
                'cumulative_expected'       => $cumulExpected,
                'cumulative_collected'      => $cumulCollected,
                'cumulative_months'         => $cumulMonths,
                'disbursements'             => $sectionC['loan_disbursements'],
                'repayments'                => $sectionC['loan_repayments'],
                'interest_earned'           => $sectionC['interest_earned'],
                'closing_balance'           => $sectionC['closing_balance'],
                'group_amount'              => $sectionC['closing_balance'] + $totalLoansOut,
                'total_loans_out'           => $totalLoansOut,
                'interest_receivable'       => $interestReceivable,
                'total_penalties'           => $totalPenalties,
                'penalties_collected_month' => $penaltiesCollectedMonth,
                'total_arrears'             => $totalArrears,
                // Comprehensive cycle-to-date expected vs received
                'total_expected_income'     => $totalExpectedIncome,
                'total_received_income'     => $totalReceivedIncome,
                'expected_contributions_all'=> $allContribExpected,
                'expected_penalties_all'    => $allPenaltiesExpected,
                'expected_interest_all'     => $allInterestExpected,
                'expected_cash_income_all'  => $allCashIncome,
                // Group profit = total expected - member contributions - withdrawals
                'group_profit_expected'     => $groupProfitExpected,
                'group_profit_collected'    => $groupProfitCollected,
                'cycle_withdrawals'         => $cycleWithdrawals,
                'total_group_shares'        => $totalGroupShares,
                'profit_per_share'          => $profitPerShare,
                'member_profit_shares'      => $memberProfitShares,
                'total_members'             => $members->count(),
                'overdue_members'           => $overdueMembers,
                'cash_income'               => $sectionC['contributions_collected'] + $sectionC['loan_repayments'] + $sectionC['interest_earned'],
                'total_disbursed_out'       => $sectionC['loan_disbursements'] + $sectionC['withdrawals'],
            ],
        ];

        $report['sheet'] = $this->buildSingleSheet(
            $group->id, $members, $monthStart, $monthEnd, $prevStart, $prevEnd, $sectionA, $sectionB, $sectionC
        );

        return $report;
    }

    // ------------------------------------------------------------------
    // Single-sheet (Kinyarwanda) consolidated view
    // ------------------------------------------------------------------
    /**
     * Build the consolidated single-page report that combines previous-month
     * carry-in, current-month transactions and current debt into one table
     * per the Kinyarwanda VSLA report layout.
     */
    protected function buildSingleSheet(
        int $groupId, Collection $members,
        Carbon $monthStart, Carbon $monthEnd,
        Carbon $prevStart, Carbon $prevEnd,
        array $sectionA, array $sectionB, array $sectionC,
    ): array {
        // ---- per-member loan outstanding at prev_end (carry-in for this month)
        $loanCarryIn = $this->loanOutstandingByMemberAt($groupId, $prevEnd);
        // ---- per-member loan outstanding now (debt as of month end)
        $loanNow = $this->loanOutstandingByMemberAt($groupId, $monthEnd);

        // ---- per-member arrears (unpaid contributions) at prev_end
        $arrearsPrev = Contribution::query()
            ->selectRaw('member_id, SUM(expected_amount + late_fee_amount - paid_amount) AS amt')
            ->where('group_id', $groupId)
            ->where('due_on', '<=', $prevEnd->toDateString())
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // ---- per-member arrears settled in prev month (late contributions paid)
        $arrearsPaidPrev = Contribution::query()
            ->selectRaw('member_id, SUM(paid_amount) AS amt')
            ->where('group_id', $groupId)
            ->where('status', 'paid')
            ->whereNotNull('paid_on')
            ->whereColumn('paid_on', '>', 'due_on')
            ->whereBetween('paid_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // ---- per-member late-fee interest in prev month and this month
        // Filter by period_start (when the contribution was due) not paid_on,
        // so we capture accrued fees on all contributions for that period
        // regardless of whether/when they were settled.
        $lateFeesPrev = Contribution::query()
            ->selectRaw('member_id, SUM(late_fee_amount) AS amt')
            ->where('group_id', $groupId)
            ->where('late_fee_amount', '>', 0)
            ->whereBetween('period_start', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');
        $lateFeesNow = Contribution::query()
            ->selectRaw('member_id, SUM(late_fee_amount) AS amt')
            ->where('group_id', $groupId)
            ->where('late_fee_amount', '>', 0)
            ->whereBetween('period_start', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // ---- per-member loan interest paid in prev month
        $loanInterestPrev = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('loans.member_id, SUM(loan_repayments.interest_portion) AS amt')
            ->where('loans.group_id', $groupId)
            ->whereBetween('loan_repayments.paid_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->groupBy('loans.member_id')
            ->pluck('amt', 'loans.member_id');

        // Index section A/B rows by member_no for quick lookup.
        $aByNo = collect($sectionA['rows'])->keyBy('member_no');
        $bByNo = collect($sectionB['rows'])->keyBy('member_no');

        $rows = [];
        $totals = [
            'last_arrears_in'           => 0.0,
            'last_arrears_paid'         => 0.0,
            'last_late_interest'        => 0.0,
            'last_loan_interest'        => 0.0,
            'last_arrears_outstanding'  => 0.0,
            'now_money_in'              => 0.0,
            'now_late_interest_in'      => 0.0,
            'now_loan_existing'         => 0.0,
            'now_loan_new'              => 0.0,
            'now_loan_interest_charged' => 0.0,
            'now_loan_principal_paid'   => 0.0,
            'now_loan_interest_paid'    => 0.0,
            'debt_now'                  => 0.0,
        ];

        foreach ($members as $idx => $m) {
            $a = $aByNo->get($m->member_no, []);
            $b = $bByNo->get($m->member_no, []);

            $row = [
                'no'                        => $idx + 1,
                'member_no'                 => $m->member_no,
                'member_name'               => $m->full_name,
                // Ukwezi Gushize (last month)
                'last_arrears_in'           => (float) ($a['previous_savings']  ?? 0),
                'last_arrears_paid'         => (float) ($arrearsPaidPrev[$m->id] ?? 0),
                'last_late_interest'        => (float) ($lateFeesPrev[$m->id]    ?? 0),
                'last_loan_interest'        => (float) ($loanInterestPrev[$m->id] ?? 0),
                'last_arrears_outstanding'  => (float) ($arrearsPrev[$m->id]     ?? 0),
                // Uku Kwezi (this month)
                'now_money_in'              => (float) ($b['monthly_contribution'] ?? 0),
                'now_late_interest_in'      => (float) ($lateFeesNow[$m->id]   ?? 0),
                'now_loan_existing'         => (float) ($loanCarryIn[$m->id]   ?? 0),
                'now_loan_new'              => (float) ($b['loans_issued']      ?? 0),
                'now_loan_interest_charged' => (float) ($b['interest_collected']?? 0),
                'now_loan_principal_paid'   => (float) ($b['loans_repaid']      ?? 0),
                'now_loan_interest_paid'    => (float) ($b['interest_collected']?? 0),
                // Umwenda
                'debt_now'                  => (float) ($loanNow[$m->id]        ?? 0),
            ];

            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
            $rows[] = $row;
        }

        // Summary block at the bottom.
        $totalLoanOutstanding = array_sum($loanNow->all());
        $moneyIn = (float) $sectionC['contributions_collected'] + (float) $sectionC['loan_repayments'] + (float) $sectionC['interest_earned'];
        $moneyOut = (float) $sectionC['loan_disbursements'] + (float) $sectionC['withdrawals'];
        $summary = [
            'amafaranga_yinjiye'       => $moneyIn,
            'amafaranga_ari_kuti_konti'=> (float) $sectionC['opening_balance'] + $moneyIn,
            'ayasohotse'               => $moneyOut,
            'asigaye_kuri_konti'       => (float) $sectionC['closing_balance'],
            'umutungo_wose'            => (float) $sectionC['closing_balance'] + $totalLoanOutstanding,
        ];

        return ['rows' => $rows, 'totals' => $totals, 'summary' => $summary];
    }

    // ------------------------------------------------------------------
    // Section E — Per-Member Monthly Status (contributions + loan debt)
    // ------------------------------------------------------------------
    protected function buildSectionE(int $groupId, Collection $members, Carbon $start, Carbon $end, Collection $loanOutstanding): array
    {
        // ---- This month's contributions — drives Expected & this-month Paid columns.
        // We look at contributions whose period falls in the selected month.
        $monthContribs = Contribution::query()
            ->where('group_id', $groupId)
            ->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'member_id', 'status', 'expected_amount', 'paid_amount', 'late_fee_amount'])
            ->groupBy('member_id');

        // ---- Cash actually paid this month (from Payment records).
        // More accurate than contribution.paid_amount which may reflect cumulative payments.
        $monthPayments = Payment::query()
            ->where('group_id', $groupId)
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('member_id, SUM(amount) AS amt')
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // ---- ALL outstanding (unpaid) contributions with due_on ≤ month-end.
        // This captures carry-forward arrears from previous months so the
        // Outstanding column reflects the member's true total debt, not just
        // what was due this calendar month.
        $allUnpaid = Contribution::query()
            ->where('group_id', $groupId)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->where('due_on', '<=', $end->toDateString())
            ->get(['id', 'member_id', 'status', 'expected_amount', 'paid_amount', 'late_fee_amount'])
            ->groupBy('member_id');

        $rows   = [];
        $totals = ['expected' => 0.0, 'paid' => 0.0, 'penalty' => 0.0, 'outstanding' => 0.0, 'loan_out' => 0.0, 'total_debt' => 0.0];

        foreach ($members as $m) {
            $mc    = $monthContribs->get($m->id, collect());
            $allUn = $allUnpaid->get($m->id, collect());

            // Expected this month
            $expected = (float) $mc->sum('expected_amount');
            // Cash received this month
            $paid = (float) ($monthPayments[$m->id] ?? 0);

            // All-time outstanding penalty (on all still-unpaid contributions ≤ month end)
            $penaltyOutstanding = (float) $allUn->sum('late_fee_amount');
            // Penalty on this month's contributions that were already settled
            $penaltyCollected   = (float) $mc->where('status', 'paid')->sum('late_fee_amount');
            // Display total = what's still owed + what was collected this month
            $penaltyTotal       = $penaltyOutstanding + $penaltyCollected;

            // Total outstanding = all unpaid principal + all outstanding penalties
            // across every period up to and including this month.
            $outstanding = max(0.0,
                (float) $allUn->sum('expected_amount')
                + $penaltyOutstanding
                - (float) $allUn->sum('paid_amount')
            );

            $loanOut   = (float) ($loanOutstanding[$m->id] ?? 0);
            $totalDebt = $outstanding + $loanOut;

            // Status: worst status across ALL outstanding contributions (not just this month)
            $overdue = $allUn->where('status', 'overdue')->count();
            $pending = $allUn->whereIn('status', ['pending', 'partial'])->count()
                     + $mc->whereIn('status', ['pending', 'partial'])->count();
            $paidCnt = $mc->where('status', 'paid')->count();

            if ($allUn->isEmpty() && $mc->isEmpty()) $status = 'none';
            elseif ($overdue > 0)                    $status = 'overdue';
            elseif ($pending > 0)                    $status = 'pending';
            else                                     $status = 'paid';

            $rows[] = [
                'member_no'           => $m->member_no,
                'member_name'         => $m->full_name,
                'expected'            => $expected,           // this month only
                'paid'                => $paid,               // cash received this month
                'penalty'             => $penaltyTotal,       // display: outstanding + collected this month
                'penalty_collected'   => $penaltyCollected,
                'penalty_outstanding' => $penaltyOutstanding, // still owed across all periods
                'outstanding'         => $outstanding,        // ALL-TIME total contribution debt
                'loan_out'            => $loanOut,
                'total_debt'          => $totalDebt,
                'status'              => $status,
                'paid_count'          => $paidCnt,
                'overdue_count'       => $overdue,
                'pending_count'       => $pending,
            ];

            $totals['expected']    += $expected;
            $totals['paid']        += $paid;
            $totals['penalty']     += $penaltyTotal;
            $totals['outstanding'] += $outstanding;
            $totals['loan_out']    += $loanOut;
            $totals['total_debt']  += $totalDebt;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Per-member loan outstanding as-of a date: sum(principal disbursed by date)
     * minus sum(principal repaid by date). Returns a {member_id => float} map.
     */
    protected function loanOutstandingByMemberAt(int $groupId, Carbon $asOf): Collection
    {
        $disbursed = Loan::query()
            ->selectRaw('member_id, SUM(principal) AS amt')
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->where('disbursed_on', '<=', $asOf->toDateString())
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $repaid = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('loans.member_id, SUM(loan_repayments.principal_portion) AS amt')
            ->where('loans.group_id', $groupId)
            ->where('loan_repayments.paid_on', '<=', $asOf->toDateString())
            ->groupBy('loans.member_id')
            ->pluck('amt', 'loans.member_id');

        $out = collect();
        $ids = $disbursed->keys()->merge($repaid->keys())->unique();
        foreach ($ids as $mid) {
            $out[$mid] = max(0.0, (float) ($disbursed[$mid] ?? 0) - (float) ($repaid[$mid] ?? 0));
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Section A — Previous Month Carry Forward
    // ------------------------------------------------------------------
    protected function buildSectionA(int $groupId, Collection $members, Carbon $prevStart, Carbon $prevEnd): array
    {
        // Savings balance at end of previous month (per member): sum of credits - debits
        // for category=savings entries dated <= prev month end.
        $balRows = PassbookEntry::query()
            ->selectRaw('member_id, SUM(credit) - SUM(debit) AS bal')
            ->where('group_id', $groupId)
            ->where('category', 'savings')
            ->where('entry_date', '<=', $prevEnd->toDateString())
            ->groupBy('member_id')
            ->pluck('bal', 'member_id');

        // When passbook is empty, fall back to cumulative payments up to prev month end.
        if ($balRows->isEmpty()) {
            $balRows = Payment::query()
                ->selectRaw('member_id, SUM(amount) AS bal')
                ->where('group_id', $groupId)
                ->where('paid_on', '<=', $prevEnd->toDateString())
                ->groupBy('member_id')
                ->pluck('bal', 'member_id');
        }

        // Withdrawals during the previous month (per member).
        $withRows = PassbookEntry::query()
            ->selectRaw('member_id, SUM(credit) AS amt')
            ->where('group_id', $groupId)
            ->where('category', 'withdrawal')
            ->whereBetween('entry_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // Loans disbursed in the previous month — proxy for "savings used as guarantee".
        // (We don't model collateral explicitly; this gives a meaningful figure.)
        $loanRows = Loan::query()
            ->selectRaw('member_id, SUM(principal) AS amt')
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->whereBetween('disbursed_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $rows = [];
        $totals = [
            'previous_savings'    => 0.0,
            'savings_withdrawn'   => 0.0,
            'used_for_guarantee'  => 0.0,
            'guarantee_contrib'   => 0.0,
            'remaining_balance'   => 0.0,
        ];
        $byMember = [];

        foreach ($members as $m) {
            $prev = (float) ($balRows[$m->id] ?? 0);
            $with = (float) ($withRows[$m->id] ?? 0);
            $used = (float) ($loanRows[$m->id] ?? 0);
            $remaining = $prev; // running savings balance carried forward

            $rows[] = [
                'member_no'           => $m->member_no,
                'member_name'         => $m->full_name,
                'previous_savings'    => $prev,
                'savings_withdrawn'   => $with,
                'used_for_guarantee'  => $used,
                'guarantee_contrib'   => 0.0,
                'remaining_balance'   => $remaining,
            ];

            $totals['previous_savings']   += $prev;
            $totals['savings_withdrawn']  += $with;
            $totals['used_for_guarantee'] += $used;
            $totals['remaining_balance']  += $remaining;
            $byMember[$m->id] = $remaining;
        }

        return ['rows' => $rows, 'totals' => $totals, 'by_member' => $byMember];
    }

    // ------------------------------------------------------------------
    // Section B — Current Month Transactions
    // ------------------------------------------------------------------
    protected function buildSectionB(int $groupId, Collection $members, Carbon $start, Carbon $end, array $carryByMember): array
    {
        // Payments (deposits) per member, split by contribution type.
        $payRows = Payment::query()
            ->leftJoin('contributions', 'contributions.id', '=', 'payments.contribution_id')
            ->selectRaw("payments.member_id,
                COALESCE(contributions.type,'other') AS ctype,
                SUM(payments.amount) AS amt")
            ->where('payments.group_id', $groupId)
            ->whereBetween('payments.paid_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('payments.member_id', 'ctype')
            ->get();

        $payByMember = [];
        foreach ($payRows as $r) {
            $payByMember[$r->member_id] ??= ['total' => 0.0, 'savings' => 0.0];
            $payByMember[$r->member_id]['total']   += (float) $r->amt;
            if ($r->ctype === 'savings') {
                $payByMember[$r->member_id]['savings'] += (float) $r->amt;
            }
        }

        // Loans issued this month (per member).
        $issued = Loan::query()
            ->selectRaw('member_id, SUM(principal) AS amt')
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->whereBetween('disbursed_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        // Loan repayments this month (per member): split into principal + interest portions.
        $repays = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('loans.member_id,
                SUM(loan_repayments.amount) AS amt,
                SUM(loan_repayments.principal_portion) AS principal,
                SUM(loan_repayments.interest_portion) AS interest')
            ->where('loans.group_id', $groupId)
            ->whereBetween('loan_repayments.paid_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('loans.member_id')
            ->get()
            ->keyBy('member_id');

        // Withdrawals this month (per member) from passbook.
        $withdrawals = PassbookEntry::query()
            ->selectRaw('member_id, SUM(credit) AS amt')
            ->where('group_id', $groupId)
            ->where('category', 'withdrawal')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $rows = [];
        $totals = [
            'monthly_contribution' => 0.0,
            'mandatory_savings'    => 0.0,
            'loans_issued'         => 0.0,
            'installments'         => 0.0,
            'interest_collected'   => 0.0,
            'loans_repaid'         => 0.0,
            'savings_withdrawals'  => 0.0,
            'net_position'         => 0.0,
        ];

        foreach ($members as $m) {
            $contrib   = (float) ($payByMember[$m->id]['total']   ?? 0);
            $mand      = (float) ($payByMember[$m->id]['savings'] ?? 0);
            $issuedAmt = (float) ($issued[$m->id] ?? 0);
            $r         = $repays->get($m->id);
            $instAmt   = (float) ($r->amt       ?? 0);
            $intAmt    = (float) ($r->interest  ?? 0);
            $princAmt  = (float) ($r->principal ?? 0);
            $withAmt   = (float) ($withdrawals[$m->id] ?? 0);

            $carry = (float) ($carryByMember[$m->id] ?? 0);
            $net   = $carry + $contrib - $withAmt + $princAmt - $issuedAmt;

            $rows[] = [
                'member_no'            => $m->member_no,
                'member_name'          => $m->full_name,
                'monthly_contribution' => $contrib,
                'mandatory_savings'    => $mand,
                'loans_issued'         => $issuedAmt,
                'installments'         => $instAmt,
                'interest_collected'   => $intAmt,
                'loans_repaid'         => $princAmt,
                'savings_withdrawals'  => $withAmt,
                'net_position'         => $net,
            ];

            $totals['monthly_contribution'] += $contrib;
            $totals['mandatory_savings']    += $mand;
            $totals['loans_issued']         += $issuedAmt;
            $totals['installments']         += $instAmt;
            $totals['interest_collected']   += $intAmt;
            $totals['loans_repaid']         += $princAmt;
            $totals['savings_withdrawals']  += $withAmt;
            $totals['net_position']         += $net;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    // ------------------------------------------------------------------
    // Section C — Monthly Financial Summary (group totals)
    // ------------------------------------------------------------------
    protected function buildSectionC(int $groupId, Carbon $start, Carbon $end, Carbon $prevEnd, array $sectionBTotals): array
    {
        $opening = $this->cashPositionAt($groupId, $prevEnd);

        $contributions = (float) Payment::query()
            ->where('group_id', $groupId)
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $disbursements = (float) Loan::query()
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->whereBetween('disbursed_on', [$start->toDateString(), $end->toDateString()])
            ->sum('principal');

        $repaymentRows = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('SUM(loan_repayments.principal_portion) AS principal,
                         SUM(loan_repayments.interest_portion) AS interest')
            ->where('loans.group_id', $groupId)
            ->whereBetween('loan_repayments.paid_on', [$start->toDateString(), $end->toDateString()])
            ->first();

        $repayments    = (float) ($repaymentRows->principal ?? 0);
        $interestEarn  = (float) ($repaymentRows->interest  ?? 0);

        $withdrawals = (float) PassbookEntry::query()
            ->where('group_id', $groupId)
            ->where('category', 'withdrawal')
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->sum('credit');

        // Cashbook adjustments during the month (income +, expense -).
        $cashIncome = (float) CashbookEntry::query()
            ->where('group_id', $groupId)
            ->where('type', 'income')
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $cashExpense = (float) CashbookEntry::query()
            ->where('group_id', $groupId)
            ->where('type', 'expense')
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $adjustments = $cashIncome - $cashExpense;

        $closing = $opening + $contributions + $repayments + $interestEarn - $disbursements - $withdrawals + $adjustments;

        $cashOnHand = $this->cashPositionAt($groupId, $end);

        // Late fees collected in cash this month — a sub-component of contributions_collected.
        // (Already counted inside $contributions above, just broken out for the summary view.)
        $penaltiesCollected = (float) Contribution::query()
            ->where('group_id', $groupId)
            ->where('late_fee_amount', '>', 0)
            ->where('status', 'paid')
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])
            ->sum('late_fee_amount');

        return [
            'opening_balance'         => $opening,
            'contributions_collected' => $contributions,
            'penalties_collected'     => $penaltiesCollected,
            'loan_disbursements'      => $disbursements,
            'loan_repayments'         => $repayments,
            'interest_earned'         => $interestEarn,
            'withdrawals'             => $withdrawals,
            'cash_on_hand'            => $cashOnHand,
            'adjustments'             => $adjustments,
            'closing_balance'         => $closing,
        ];
    }

    // ------------------------------------------------------------------
    // Section D — Member Summary (cycle-to-date)
    // ------------------------------------------------------------------
    protected function buildSectionD(int $groupId, Collection $members, Carbon $cycleStart, Carbon $monthEnd): array
    {
        $contribTotals = Payment::query()
            ->selectRaw('member_id, SUM(amount) AS amt')
            ->where('group_id', $groupId)
            ->whereBetween('paid_on', [$cycleStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $savingsBalances = PassbookEntry::query()
            ->selectRaw('member_id, SUM(credit) - SUM(debit) AS bal')
            ->where('group_id', $groupId)
            ->where('category', 'savings')
            ->where('entry_date', '<=', $monthEnd->toDateString())
            ->groupBy('member_id')
            ->pluck('bal', 'member_id');

        // When passbook has no entries, fall back to payment totals (cycle-to-date).
        // Payments represent savings deposits and are the correct fallback source.
        $passBookEmpty = $savingsBalances->isEmpty();

        $loansReceived = Loan::query()
            ->selectRaw('member_id, SUM(principal) AS amt')
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->whereBetween('disbursed_on', [$cycleStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $loanOutstanding = Loan::query()
            ->selectRaw('member_id, SUM(outstanding) AS amt')
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying'])
            ->groupBy('member_id')
            ->pluck('amt', 'member_id');

        $interestPaid = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('loans.member_id, SUM(loan_repayments.interest_portion) AS amt')
            ->where('loans.group_id', $groupId)
            ->whereBetween('loan_repayments.paid_on', [$cycleStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('loans.member_id')
            ->pluck('amt', 'member_id');

        $rows = [];
        $totals = [
            'contributions'  => 0.0,
            'savings'        => 0.0,
            'loans_received' => 0.0,
            'outstanding'    => 0.0,
            'interest_paid'  => 0.0,
            'net_equity'     => 0.0,
        ];

        foreach ($members as $m) {
            $c    = (float) ($contribTotals[$m->id]   ?? 0);
            // Savings balance: prefer passbook (credit-debit), fall back to payment totals
            // when the passbook hasn't been populated yet for this group.
            $s    = $passBookEmpty
                ? $c
                : (float) ($savingsBalances[$m->id] ?? 0);
            $lr   = (float) ($loansReceived[$m->id]   ?? 0);
            $out  = (float) ($loanOutstanding[$m->id] ?? 0);
            $ip   = (float) ($interestPaid[$m->id]    ?? 0);
            $eq   = $s - $out;

            $rows[] = [
                'member_no'      => $m->member_no,
                'member_name'    => $m->full_name,
                'contributions'  => $c,
                'savings'        => $s,
                'loans_received' => $lr,
                'outstanding'    => $out,
                'interest_paid'  => $ip,
                'net_equity'     => $eq,
            ];

            $totals['contributions']  += $c;
            $totals['savings']        += $s;
            $totals['loans_received'] += $lr;
            $totals['outstanding']    += $out;
            $totals['interest_paid']  += $ip;
            $totals['net_equity']     += $eq;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Group cash position at a specific point in time.
     *
     * Cash flowing INTO the group safe = member payments + loan repayments + cashbook income.
     * Cash flowing OUT of the safe   = loan disbursements + savings withdrawals + cashbook expenses.
     */
    protected function cashPositionAt(int $groupId, Carbon $asOf): float
    {
        $date = $asOf->toDateString();

        $payIn = (float) Payment::query()
            ->where('group_id', $groupId)
            ->where('paid_on', '<=', $date)
            ->sum('amount');

        // Loan repayments (principal + interest) are real cash flowing back into the safe.
        // Previously omitted, which caused cash_on_hand to understate the balance.
        $loanRepaid = (float) LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('loans.group_id', $groupId)
            ->where('loan_repayments.paid_on', '<=', $date)
            ->sum('loan_repayments.amount');

        $cashIn = (float) CashbookEntry::query()
            ->where('group_id', $groupId)->where('type', 'income')
            ->where('occurred_on', '<=', $date)->sum('amount');

        $cashOut = (float) CashbookEntry::query()
            ->where('group_id', $groupId)->where('type', 'expense')
            ->where('occurred_on', '<=', $date)->sum('amount');

        $disbursed = (float) Loan::query()
            ->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->whereNotNull('disbursed_on')
            ->where('disbursed_on', '<=', $date)
            ->sum('principal');

        $withdrawn = (float) PassbookEntry::query()
            ->where('group_id', $groupId)
            ->where('category', 'withdrawal')
            ->where('entry_date', '<=', $date)
            ->sum('credit');

        return $payIn + $loanRepaid + $cashIn - $cashOut - $disbursed - $withdrawn;
    }

    // ------------------------------------------------------------------
    // Excel export — consolidated single sheet (Kinyarwanda layout)
    // ------------------------------------------------------------------
    public function exportXlsx(array $report): StreamedResponse
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Raporo y\'Ukwezi');

        $currency   = $report['header']['currency'];
        $fmt        = "#,##0;[Red]-#,##0;\"\"";
        $monthLabel = $report['header']['month']->format('m/Y');
        $rows       = $report['sheet']['rows'];
        $tot        = $report['sheet']['totals'];
        $sum        = $report['sheet']['summary'];

        // ---- Title rows ----
        $sheet->setCellValue('A1', $report['header']['group']->name.' — Raporo y\'Ukwezi '.$report['header']['month_label']);
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ---- Two-tier header (rows 3–4) ----
        $h1Style = function (string $range, string $rgb) use ($sheet) {
            $sheet->getStyle($range)->getFont()->setBold(true);
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
            $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        };

        $sheet->setCellValue('A3', "Ukwezi\n".$monthLabel);  $sheet->mergeCells('A3:A4');
        $sheet->setCellValue('B3', 'No');                     $sheet->mergeCells('B3:B4');
        $sheet->setCellValue('C3', 'Amazina');                $sheet->mergeCells('C3:C4');

        $sheet->setCellValue('D3', 'Ukwezi Gushize');         $sheet->mergeCells('D3:H3');
        $sheet->setCellValue('I3', 'Uku Kwezi');              $sheet->mergeCells('I3:O3');
        $sheet->setCellValue('P3', 'Umwenda');                $sheet->mergeCells('P3:P4');

        $lastHeaders = [
            'D' => 'Ibirarane arimo',
            'E' => 'Ibirarane byishywe',
            'F' => "Inyungu y'inyungu kubukererwe",
            'G' => "Inyungu y'inyungu kunguzanyo",
            'H' => 'Ubukererwe bwishyu',
        ];
        $nowHeaders = [
            'I' => 'Ayinjiye',
            'J' => "Inyungu n'ubukererwe",
            'K' => 'Inguzanyo asanganywe',
            'L' => 'Inguzanyo nshya itanzwe',
            'M' => 'Inyungu kunguzanyo',
            'N' => 'Inguzanyo yishyuwe',
            'O' => 'Inyungu yishyuwe',
        ];
        foreach ($lastHeaders as $col => $label) $sheet->setCellValue($col.'4', $label);
        foreach ($nowHeaders  as $col => $label) $sheet->setCellValue($col.'4', $label);

        $h1Style('A3:C4', 'F2F2F2');
        $h1Style('D3:H4', 'D9E1F2');  // Ukwezi Gushize — blue
        $h1Style('I3:O4', 'FFF2CC');  // Uku Kwezi — yellow
        $h1Style('P3:P4', 'FFE699');  // Umwenda

        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->getRowDimension(4)->setRowHeight(48);

        // ---- Body rows ----
        $r = 5;
        $bodyStart = $r;
        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$r}", $i === 0 ? "Ukwezi\n".$monthLabel : '');
            $sheet->setCellValue("B{$r}", $row['no']);
            $sheet->setCellValue("C{$r}", $row['member_name']);
            $sheet->setCellValue("D{$r}", $row['last_arrears_in'] ?: null);
            $sheet->setCellValue("E{$r}", $row['last_arrears_paid'] ?: null);
            $sheet->setCellValue("F{$r}", $row['last_late_interest'] ?: null);
            $sheet->setCellValue("G{$r}", $row['last_loan_interest'] ?: null);
            $sheet->setCellValue("H{$r}", $row['last_arrears_outstanding'] ?: null);
            $sheet->setCellValue("I{$r}", $row['now_money_in'] ?: null);
            $sheet->setCellValue("J{$r}", $row['now_late_interest_in'] ?: null);
            $sheet->setCellValue("K{$r}", $row['now_loan_existing'] ?: null);
            $sheet->setCellValue("L{$r}", $row['now_loan_new'] ?: null);
            $sheet->setCellValue("M{$r}", $row['now_loan_interest_charged'] ?: null);
            $sheet->setCellValue("N{$r}", $row['now_loan_principal_paid'] ?: null);
            $sheet->setCellValue("O{$r}", $row['now_loan_interest_paid'] ?: null);
            $sheet->setCellValue("P{$r}", $row['debt_now'] ?: null);
            $r++;
        }
        if (count($rows) > 1) {
            $sheet->mergeCells("A{$bodyStart}:A".($r - 1));
        }
        $bodyEnd = $r - 1;

        // Body styling: borders, numeric format, soft yellow on "Uku Kwezi" cells
        $sheet->getStyle("A{$bodyStart}:P{$bodyEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D{$bodyStart}:P{$bodyEnd}")->getNumberFormat()->setFormatCode($fmt);
        $sheet->getStyle("D{$bodyStart}:P{$bodyEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("I{$bodyStart}:O{$bodyEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEA');
        $sheet->getStyle("P{$bodyStart}:P{$bodyEnd}")->getFont()->setBold(true)->getColor()->setRGB('B32D2D');
        $sheet->getStyle("A{$bodyStart}:A{$bodyEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A{$bodyStart}:A{$bodyEnd}")->getFont()->setBold(true);
        $sheet->getStyle("B{$bodyStart}:B{$bodyEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ---- Totals row ("Tatal") ----
        $sheet->setCellValue("C{$r}", 'Tatal');
        $sheet->setCellValue("D{$r}", $tot['last_arrears_in']);
        $sheet->setCellValue("E{$r}", $tot['last_arrears_paid']);
        $sheet->setCellValue("F{$r}", $tot['last_late_interest']);
        $sheet->setCellValue("G{$r}", $tot['last_loan_interest']);
        $sheet->setCellValue("H{$r}", $tot['last_arrears_outstanding']);
        $sheet->setCellValue("I{$r}", $tot['now_money_in']);
        $sheet->setCellValue("J{$r}", $tot['now_late_interest_in']);
        $sheet->setCellValue("K{$r}", $tot['now_loan_existing']);
        $sheet->setCellValue("L{$r}", $tot['now_loan_new']);
        $sheet->setCellValue("M{$r}", $tot['now_loan_interest_charged']);
        $sheet->setCellValue("N{$r}", $tot['now_loan_principal_paid']);
        $sheet->setCellValue("O{$r}", $tot['now_loan_interest_paid']);
        $sheet->setCellValue("P{$r}", $sum['umutungo_wose']);
        $sheet->getStyle("A{$r}:P{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sheet->getStyle("A{$r}:P{$r}")->getFont()->setBold(true)->getColor()->setRGB('B32D2D');
        $sheet->getStyle("A{$r}:P{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D{$r}:P{$r}")->getNumberFormat()->setFormatCode("#,##0");
        $sheet->getStyle("D{$r}:P{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // ---- Summary block (left-anchored under the table) ----
        $r += 2;
        $items = [
            ['Amafaranga yinjiye',         $sum['amafaranga_yinjiye']],
            ['Amafaranga ari kuri konti',  $sum['amafaranga_ari_kuti_konti']],
            ['Ayasohotse',                 $sum['ayasohotse']],
            ['Asigaye kuri konti',         $sum['asigaye_kuri_konti']],
            ['Umutungo wose',              $sum['umutungo_wose']],
        ];
        foreach ($items as $i => [$label, $val]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->mergeCells("A{$r}:C{$r}");
            $sheet->setCellValue("D{$r}", $val);
            $sheet->mergeCells("D{$r}:E{$r}");
            $sheet->getStyle("A{$r}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            $sheet->getStyle("D{$r}:E{$r}")->getNumberFormat()->setFormatCode("#,##0");
            $sheet->getStyle("D{$r}:E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$r}:E{$r}")->getFont()->setBold($i === count($items) - 1);
            if ($i === count($items) - 1) {
                $sheet->getStyle("A{$r}:E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            }
            $r++;
        }

        // ---- Column widths ----
        $widths = [
            'A' => 10, 'B' => 4, 'C' => 26,
            'D' => 13, 'E' => 13, 'F' => 14, 'G' => 14, 'H' => 13,
            'I' => 12, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 13, 'N' => 13, 'O' => 13,
            'P' => 13,
        ];
        foreach ($widths as $col => $w) $sheet->getColumnDimension($col)->setWidth($w);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->freezePane('D5');

        $writer = new XlsxWriter($book);
        $name = 'monthly-report-'.\Illuminate\Support\Str::slug($report['header']['group']->name).'-'.$report['header']['month']->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ------------------------------------------------------------------
    // (legacy) multi-section writer — retained for reference only.
    // ------------------------------------------------------------------
    /** @deprecated kept for the writeSection helper which is no longer used. */
    protected function exportXlsxLegacy(array $report): StreamedResponse
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Monthly Report');

        $currency = $report['header']['currency'];
        $fmt = "#,##0.00 \"{$currency}\"";

        // ---- Header ----
        $sheet->setCellValue('A1', $report['header']['group']->name.' — Treasurer Summary Report');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Reporting Month: '.$report['header']['month_label']);
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Generated by '.$report['header']['generated_by'].' on '.$report['header']['generated_at']->toDayDateTimeString());
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3')->getFont()->setItalic(true);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 5;

        // ---- Section A ----
        $row = $this->writeSection($sheet, $row, 'A',
            'Section A — Previous Month Carry Forward',
            ['Member No','Member Name','Previous Savings','Savings Withdrawn','Used for Guarantee','Guarantee Contributions','Remaining Balance'],
            $report['section_a']['rows'],
            ['member_no','member_name','previous_savings','savings_withdrawn','used_for_guarantee','guarantee_contrib','remaining_balance'],
            [
                'Member No' => 'TOTAL',
                'previous_savings' => $report['section_a']['totals']['previous_savings'],
                'savings_withdrawn' => $report['section_a']['totals']['savings_withdrawn'],
                'used_for_guarantee' => $report['section_a']['totals']['used_for_guarantee'],
                'guarantee_contrib' => $report['section_a']['totals']['guarantee_contrib'],
                'remaining_balance' => $report['section_a']['totals']['remaining_balance'],
            ],
            [3,4,5,6,7], $fmt, '4F81BD'
        );

        // ---- Section B ----
        $row = $this->writeSection($sheet, $row + 1, 'B',
            'Section B — Current Month Transactions',
            ['Member No','Member Name','Monthly Contribution','Mandatory Savings','Loans Issued','Installments Received','Interest Collected','Loans Repaid','Savings Withdrawals','Net Position'],
            $report['section_b']['rows'],
            ['member_no','member_name','monthly_contribution','mandatory_savings','loans_issued','installments','interest_collected','loans_repaid','savings_withdrawals','net_position'],
            [
                'Member No' => 'TOTAL',
                'monthly_contribution' => $report['section_b']['totals']['monthly_contribution'],
                'mandatory_savings'    => $report['section_b']['totals']['mandatory_savings'],
                'loans_issued'         => $report['section_b']['totals']['loans_issued'],
                'installments'         => $report['section_b']['totals']['installments'],
                'interest_collected'   => $report['section_b']['totals']['interest_collected'],
                'loans_repaid'         => $report['section_b']['totals']['loans_repaid'],
                'savings_withdrawals'  => $report['section_b']['totals']['savings_withdrawals'],
                'net_position'         => $report['section_b']['totals']['net_position'],
            ],
            [3,4,5,6,7,8,9,10], $fmt, '9BBB59'
        );

        // ---- Section C ----
        $row += 1;
        $sheet->setCellValue("A{$row}", 'Section C — Monthly Financial Summary');
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C0504D');
        $row++;

        $c = $report['section_c'];
        $items = [
            ['Total Cash at Beginning',     $c['opening_balance']],
            ['Total Contributions Collected', $c['contributions_collected']],
            ['Total Loan Disbursements',    -$c['loan_disbursements']],
            ['Total Loan Repayments',        $c['loan_repayments']],
            ['Total Interest Earned',        $c['interest_earned']],
            ['Total Withdrawals',           -$c['withdrawals']],
            ['Adjustments (Cashbook Net)',   $c['adjustments']],
            ['Cash in Bank / On Hand',       $c['cash_on_hand']],
            ['Closing Fund Balance',         $c['closing_balance']],
        ];
        foreach ($items as $i => [$label, $val]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("H{$row}", $val);
            $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode($fmt);
            $sheet->getStyle("A{$row}:H{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            if ($label === 'Closing Fund Balance') {
                $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE9D9');
            }
            $row++;
        }

        // ---- Section D ----
        $row = $this->writeSection($sheet, $row + 1, 'D',
            'Section D — Member Summary (Cycle-to-Date)',
            ['Member No','Member Name','Total Contributions','Total Savings','Total Loans Received','Loan Outstanding','Interest Paid','Net Equity'],
            $report['section_d']['rows'],
            ['member_no','member_name','contributions','savings','loans_received','outstanding','interest_paid','net_equity'],
            [
                'Member No' => 'TOTAL',
                'contributions'  => $report['section_d']['totals']['contributions'],
                'savings'        => $report['section_d']['totals']['savings'],
                'loans_received' => $report['section_d']['totals']['loans_received'],
                'outstanding'    => $report['section_d']['totals']['outstanding'],
                'interest_paid'  => $report['section_d']['totals']['interest_paid'],
                'net_equity'     => $report['section_d']['totals']['net_equity'],
            ],
            [3,4,5,6,7,8], $fmt, '8064A2'
        );

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new XlsxWriter($book);
        $name = 'monthly-report-'.\Illuminate\Support\Str::slug($report['header']['group']->name).'-'.$report['header']['month']->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Write a "Section X — title / headers / rows / totals" block onto the sheet
     * and return the row index AFTER the block.
     *
     * @param  int[]  $numericCols  1-indexed columns to format as currency
     */
    protected function writeSection($sheet, int $row, string $letter, string $title, array $headers, array $rows, array $cols, array $totals, array $numericCols, string $fmt, string $themeRgb): int
    {
        $colCount = count($headers);
        $lastCol  = Coordinate::stringFromColumnIndex($colCount);

        // Title bar
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($themeRgb);
        $row++;

        // Header row
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        // Body
        foreach ($rows as $idx => $r) {
            foreach ($cols as $i => $key) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$col}{$row}", $r[$key]);
            }
            if ($idx % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            }
            foreach ($numericCols as $n) {
                $col = Coordinate::stringFromColumnIndex($n);
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($fmt);
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
        }

        // Totals row — first two columns get the label, numeric columns get values.
        $sheet->setCellValue("A{$row}", $totals['Member No'] ?? 'TOTAL');
        $sheet->mergeCells("A{$row}:B{$row}");
        foreach ($cols as $i => $key) {
            if ($i < 2) continue;
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$row}", $totals[$key] ?? '');
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        foreach ($numericCols as $n) {
            $col = Coordinate::stringFromColumnIndex($n);
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($fmt);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $row + 1;
    }
}
