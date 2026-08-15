<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AiInsightsService
{
    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE 1 — Loan Default Risk Score
    // ═══════════════════════════════════════════════════════════════════════

    public function loanRiskScore(int $memberId, int $groupId): array
    {
        $total   = Contribution::where('member_id', $memberId)->where('group_id', $groupId)->count();
        $paid    = Contribution::where('member_id', $memberId)->where('group_id', $groupId)->where('status', 'paid')->count();
        $overdue = Contribution::where('member_id', $memberId)->where('group_id', $groupId)->where('status', 'overdue')->count();

        $arrears = (float) DB::table('arrears')
            ->where('member_id', $memberId)->where('group_id', $groupId)->where('status', 'open')
            ->sum('outstanding_amount');

        $savings = (float) Contribution::where('member_id', $memberId)->where('group_id', $groupId)->sum('paid_amount');

        $loansRow = DB::table('loan_repayments')
            ->join('loans', 'loans.id', 'loan_repayments.loan_id')
            ->where('loans.member_id', $memberId)->where('loans.group_id', $groupId)
            ->selectRaw('SUM(loan_repayments.principal_portion) as repaid')
            ->first();

        $loanPrincipal = (float) DB::table('loans')
            ->where('member_id', $memberId)->where('group_id', $groupId)
            ->whereIn('status', ['disbursed', 'repaying', 'paid'])
            ->sum('principal');

        $score = 100;

        $payRate = $total > 0 ? ($paid / $total) : 1.0;
        $score  -= (int) round((1 - $payRate) * 35);

        $score -= min(30, $overdue * 6);

        $arrearsRatio = $savings > 0 ? ($arrears / $savings) : ($arrears > 0 ? 1 : 0);
        $score -= (int) min(20, round($arrearsRatio * 20));

        if ($loanPrincipal > 0) {
            $repaid    = (float) ($loansRow->repaid ?? 0);
            $loanRate  = $repaid / $loanPrincipal;
            $score    -= (int) round((1 - $loanRate) * 15);
        }

        $score = max(0, min(100, $score));
        $level = $score >= 70 ? 'low' : ($score >= 40 ? 'medium' : 'high');

        return [
            'score'   => $score,
            'level'   => $level,
            'color'   => $level === 'low' ? 'success' : ($level === 'medium' ? 'warning' : 'danger'),
            'label'   => $level === 'low' ? 'Low Risk' : ($level === 'medium' ? 'Medium Risk' : 'High Risk'),
            'factors' => [
                'payment_rate'         => round($payRate * 100),
                'overdue_count'        => $overdue,
                'arrears_amount'       => $arrears,
                'loan_repayment_rate'  => $loanPrincipal > 0 ? round((float)($loansRow->repaid ?? 0) / $loanPrincipal * 100) : null,
            ],
        ];
    }

    public function groupRiskSummary(int $groupId): array
    {
        $members = DB::table('group_member')
            ->join('members', 'members.id', 'group_member.member_id')
            ->where('group_member.group_id', $groupId)
            ->where('group_member.is_active', 1)
            ->pluck('group_member.member_id');

        $counts = ['low' => 0, 'medium' => 0, 'high' => 0];
        $topRisk = [];

        foreach ($members as $mid) {
            $r = $this->loanRiskScore($mid, $groupId);
            $counts[$r['level']]++;
            if ($r['level'] !== 'low') {
                $name = DB::table('members')->where('id', $mid)->value('full_name');
                $topRisk[] = array_merge($r, ['member_id' => $mid, 'name' => $name]);
            }
        }

        usort($topRisk, fn($a, $b) => $a['score'] <=> $b['score']);

        return [
            'counts'   => $counts,
            'top_risk' => array_slice($topRisk, 0, 5),
            'total'    => count($members),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE 2 — Cash Flow Forecast (3 months)
    // ═══════════════════════════════════════════════════════════════════════

    public function cashFlowForecast(int $groupId, int $months = 3): array
    {
        $currentBalance = $this->cashPositionAt($groupId, now());

        $avgExpenses = (float) DB::table('cashbook_entries')
            ->where('group_id', $groupId)->where('type', 'expense')->whereNull('deleted_at')
            ->where('occurred_on', '>=', now()->subMonths(6)->startOfMonth()->toDateString())
            ->sum('amount') / 6;

        $avgLoanDisb = (float) DB::table('loans')
            ->where('group_id', $groupId)->whereNotNull('disbursed_on')
            ->where('disbursed_on', '>=', now()->subMonths(6)->toDateString())
            ->sum('principal') / 6;

        $avgLoanRepay = (float) DB::table('loan_repayments')
            ->join('loans', 'loans.id', 'loan_repayments.loan_id')
            ->where('loans.group_id', $groupId)
            ->where('loan_repayments.paid_on', '>=', now()->subMonths(6)->toDateString())
            ->sum('loan_repayments.principal_portion') / 6;

        $forecast = [];
        $balance  = $currentBalance;

        for ($i = 1; $i <= $months; $i++) {
            $month    = now()->addMonths($i)->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $expectedIn = (float) Contribution::where('group_id', $groupId)
                ->whereBetween('period_start', [$month->toDateString(), $monthEnd->toDateString()])
                ->sum('expected_amount');

            $expectedIn  += $avgLoanRepay;
            $expectedOut  = $avgExpenses + $avgLoanDisb;

            $balance += $expectedIn - $expectedOut;

            $forecast[] = [
                'month'             => $month->format('M Y'),
                'month_short'       => $month->format('M'),
                'expected_in'       => round($expectedIn),
                'expected_out'      => round($expectedOut),
                'projected_balance' => round(max(0, $balance)),
                'net'               => round($expectedIn - $expectedOut),
            ];
        }

        return [
            'current_balance' => round($currentBalance),
            'months'          => $forecast,
        ];
    }

    protected function cashPositionAt(int $groupId, Carbon $asOf): float
    {
        $date       = $asOf->toDateString();
        $payments   = (float) DB::table('payments')->where('group_id', $groupId)->where('paid_on', '<=', $date)->sum('amount');
        $cashIn     = (float) DB::table('cashbook_entries')->where('group_id', $groupId)->where('type', 'income')->whereNull('deleted_at')->where('occurred_on', '<=', $date)->sum('amount');
        $cashOut    = (float) DB::table('cashbook_entries')->where('group_id', $groupId)->where('type', 'expense')->whereNull('deleted_at')->where('occurred_on', '<=', $date)->sum('amount');
        $disbursed  = (float) DB::table('loans')->where('group_id', $groupId)->whereNotNull('disbursed_on')->where('disbursed_on', '<=', $date)->sum('principal');
        return $payments + $cashIn - $cashOut - $disbursed;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE 3 — Financial Health Summary (natural language)
    // ═══════════════════════════════════════════════════════════════════════

    public function financialHealthSummary(int $groupId): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();

        $expectedMonth = (float) Contribution::where('group_id', $groupId)
            ->whereBetween('period_start', [$monthStart, $monthEnd])
            ->whereNotIn('status', ['waived'])->sum('expected_amount');

        $collectedMonth = (float) DB::table('payments')
            ->where('group_id', $groupId)
            ->whereBetween('paid_on', [$monthStart, $monthEnd])->sum('amount');

        $rate = $expectedMonth > 0 ? round($collectedMonth / $expectedMonth * 100) : 0;

        $overdueMembers = Contribution::where('group_id', $groupId)->where('status', 'overdue')
            ->distinct('member_id')->count('member_id');

        $activeLoans = (int) DB::table('loans')->where('group_id', $groupId)->whereIn('status', ['disbursed', 'repaying'])->count();
        $openArrears = (float) DB::table('arrears')->where('group_id', $groupId)->where('status', 'open')->sum('outstanding_amount');

        $cashBalance = $this->cashPositionAt($groupId, now());

        $prevMonthBalance = $this->cashPositionAt($groupId, now()->subMonth()->endOfMonth());
        $balanceChange = $prevMonthBalance > 0 ? round(($cashBalance - $prevMonthBalance) / $prevMonthBalance * 100) : 0;

        $lines   = [];
        $score   = 100;
        $status  = 'healthy';

        if ($rate >= 90) {
            $lines[] = "Collection rate this month is excellent at {$rate}%.";
        } elseif ($rate >= 70) {
            $lines[] = "Collection rate this month is {$rate}% — moderate, with room for improvement.";
            $score  -= 10; $status = 'moderate';
        } elseif ($rate > 0) {
            $lines[] = "Collection rate is low at {$rate}% — immediate member follow-up is recommended.";
            $score  -= 30; $status = 'attention';
        } else {
            $lines[] = "No contributions recorded for the current month yet.";
        }

        if ($overdueMembers > 0) {
            $lines[] = "{$overdueMembers} member" . ($overdueMembers > 1 ? 's have' : ' has') . " overdue contributions.";
            $score  -= $overdueMembers * 5;
        }

        if ($activeLoans > 0) {
            $lines[] = "{$activeLoans} active loan" . ($activeLoans > 1 ? 's are' : ' is') . " outstanding.";
        }

        if ($openArrears > 0) {
            $lines[] = "Open arrears total " . number_format($openArrears) . " RWF.";
            $score  -= 10; if ($status === 'healthy') $status = 'moderate';
        }

        if ($balanceChange >= 10) {
            $lines[] = "Group cash balance grew by {$balanceChange}% vs. last month — strong positive trend.";
        } elseif ($balanceChange < 0) {
            $lines[] = "Group cash balance decreased by " . abs($balanceChange) . "% vs. last month.";
            $score  -= 10;
        }

        $score  = max(0, min(100, $score));
        if ($score >= 80) $status = 'healthy';
        elseif ($score >= 50) $status = 'moderate';
        else $status = 'attention';

        $statusLabel = match($status) {
            'healthy'   => ['label' => 'Healthy', 'color' => 'success', 'icon' => 'ti-circle-check'],
            'moderate'  => ['label' => 'Moderate', 'color' => 'warning', 'icon' => 'ti-alert-circle'],
            default     => ['label' => 'Needs Attention', 'color' => 'danger', 'icon' => 'ti-alert-triangle'],
        };

        return [
            'score'        => $score,
            'status'       => $status,
            'status_label' => $statusLabel,
            'summary'      => implode(' ', $lines),
            'lines'        => $lines,
            'metrics'      => compact('rate', 'overdueMembers', 'activeLoans', 'openArrears', 'cashBalance', 'balanceChange'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE 4 — Anomaly Detection
    // ═══════════════════════════════════════════════════════════════════════

    public function detectAnomalies(int $groupId): array
    {
        $anomalies = [];
        $now       = now();

        $avgMonthlyExpense = (float) DB::table('cashbook_entries')
            ->where('group_id', $groupId)->where('type', 'expense')->whereNull('deleted_at')
            ->where('occurred_on', '>=', $now->copy()->subMonths(6)->startOfMonth()->toDateString())
            ->where('occurred_on', '<', $now->copy()->startOfMonth()->toDateString())
            ->sum('amount') / 6;

        $thisMonthExpense = (float) DB::table('cashbook_entries')
            ->where('group_id', $groupId)->where('type', 'expense')->whereNull('deleted_at')
            ->whereYear('occurred_on', $now->year)->whereMonth('occurred_on', $now->month)
            ->sum('amount');

        if ($avgMonthlyExpense > 0 && $thisMonthExpense > $avgMonthlyExpense * 3) {
            $anomalies[] = [
                'type'     => 'expense_spike',
                'severity' => 'high',
                'icon'     => 'ti-alert-triangle',
                'color'    => 'danger',
                'title'    => 'Unusual Expense',
                'message'  => 'This month\'s expenses (' . number_format($thisMonthExpense) . ' RWF) are ' . round($thisMonthExpense / $avgMonthlyExpense, 1) . '× the 6-month average (' . number_format($avgMonthlyExpense) . ' RWF).',
            ];
        }

        $expectedMonth = (float) Contribution::where('group_id', $groupId)
            ->whereBetween('period_start', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->sum('expected_amount');
        $collectedMonth = (float) DB::table('payments')
            ->where('group_id', $groupId)
            ->whereYear('paid_on', $now->year)->whereMonth('paid_on', $now->month)
            ->sum('amount');

        if ($expectedMonth > 0) {
            $collRate = $collectedMonth / $expectedMonth * 100;
            if ($collRate < 50) {
                $anomalies[] = [
                    'type'     => 'low_collection',
                    'severity' => 'high',
                    'icon'     => 'ti-trending-down',
                    'color'    => 'danger',
                    'title'    => 'Low Collection Rate',
                    'message'  => 'Only ' . round($collRate) . '% of expected contributions collected this month (' . number_format($collectedMonth) . ' / ' . number_format($expectedMonth) . ' RWF).',
                ];
            }
        }

        $sameDayLoans = DB::table('loans')
            ->where('group_id', $groupId)
            ->whereNotNull('disbursed_on')
            ->whereColumn('disbursed_on', 'requested_on')
            ->count();
        if ($sameDayLoans > 0) {
            $anomalies[] = [
                'type'     => 'instant_loan',
                'severity' => 'medium',
                'icon'     => 'ti-clock-bolt',
                'color'    => 'warning',
                'title'    => 'Same-Day Loan Approval',
                'message'  => $sameDayLoans . ' loan' . ($sameDayLoans > 1 ? 's were' : ' was') . ' requested and disbursed on the same day.',
            ];
        }

        $dormantCutoff = $now->copy()->subMonths(2)->startOfMonth()->toDateString();
        $dormantCount  = DB::table('group_member as gm')
            ->where('gm.group_id', $groupId)->where('gm.is_active', 1)
            ->whereNotExists(function ($q) use ($groupId, $dormantCutoff) {
                $q->from('contributions')
                  ->whereColumn('contributions.member_id', 'gm.member_id')
                  ->where('contributions.group_id', $groupId)
                  ->where('contributions.status', 'paid')
                  ->where('contributions.paid_on', '>=', $dormantCutoff);
            })->count();

        if ($dormantCount > 0) {
            $anomalies[] = [
                'type'     => 'dormant_members',
                'severity' => 'medium',
                'icon'     => 'ti-user-pause',
                'color'    => 'warning',
                'title'    => 'Dormant Members',
                'message'  => $dormantCount . ' member' . ($dormantCount > 1 ? 's have' : ' has') . ' made no payments in the last 2 months.',
            ];
        }

        $largestExpense = DB::table('cashbook_entries')
            ->where('group_id', $groupId)->where('type', 'expense')->whereNull('deleted_at')
            ->whereYear('occurred_on', $now->year)->whereMonth('occurred_on', $now->month)
            ->orderByDesc('amount')->first();

        if ($largestExpense && $avgMonthlyExpense > 0 && $largestExpense->amount > $avgMonthlyExpense * 2) {
            $anomalies[] = [
                'type'     => 'large_single_expense',
                'severity' => 'medium',
                'icon'     => 'ti-receipt',
                'color'    => 'warning',
                'title'    => 'Large Single Expense',
                'message'  => 'A single expense of ' . number_format($largestExpense->amount) . ' RWF (' . $largestExpense->category . ') exceeds 2× the monthly average.',
            ];
        }

        return $anomalies;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE 5 — Member Insights Chat
    // ═══════════════════════════════════════════════════════════════════════

    public function chat(string $message, int $memberId, int $groupId): array
    {
        $msg = mb_strtolower(trim($message));

        $savings = (float) Contribution::where('member_id', $memberId)->where('group_id', $groupId)->sum('paid_amount');
        $currency = DB::table('groups')->where('id', $groupId)->value('currency') ?? 'RWF';

        $fmt = fn($n) => number_format((float)$n) . ' ' . $currency;

        if ($this->matches($msg, ['savings', 'balance', 'paid in', 'how much have i', 'what i paid', 'contribution balance'])) {
            $totalExpected = (float) Contribution::where('member_id', $memberId)->where('group_id', $groupId)->sum('expected_amount');
            $outstanding   = $totalExpected - $savings;
            return [
                'icon'    => 'ti-piggy-bank',
                'color'   => 'success',
                'answer'  => "Your total savings paid into this group are **{$fmt($savings)}**. Total expected: {$fmt($totalExpected)}." . ($outstanding > 0 ? " Outstanding: {$fmt($outstanding)}." : " You are fully up to date! ✓"),
                'links'   => [['label' => 'View My Passbook', 'route' => 'passbooks.show', 'param' => $memberId]],
            ];
        }

        if ($this->matches($msg, ['loan', 'borrow', 'credit', 'can i get', 'qualify', 'eligible'])) {
            $activeLoan = DB::table('loans')->where('member_id', $memberId)->where('group_id', $groupId)->whereIn('status', ['disbursed', 'repaying'])->first();
            if ($activeLoan) {
                return [
                    'icon'   => 'ti-alert-circle',
                    'color'  => 'warning',
                    'answer' => "You currently have an active loan of **{$fmt($activeLoan->principal)}** with **{$fmt($activeLoan->outstanding)}** still outstanding. You must repay it before requesting a new one.",
                    'links'  => [['label' => 'View My Loans', 'route' => 'loans.index', 'param' => null]],
                ];
            }
            $rule = DB::table('group_rules')->where('group_id', $groupId)->first();
            $maxMultiple = $rule->max_loan_multiple ?? 3;
            $maxLoan = $savings * $maxMultiple;
            return [
                'icon'   => 'ti-hand-holding-dollar',
                'color'  => 'info',
                'answer' => "Based on your savings of **{$fmt($savings)}**, you may be eligible for up to **{$fmt($maxLoan)}** (group rule: {$maxMultiple}× savings). Final approval is at the group admin's discretion.",
                'links'  => [['label' => 'Request a Loan', 'route' => 'loans.create', 'param' => null]],
            ];
        }

        if ($this->matches($msg, ['owe', 'debt', 'arrear', 'overdue', 'outstanding', 'behind', 'late'])) {
            $arrears = (float) DB::table('arrears')->where('member_id', $memberId)->where('group_id', $groupId)->where('status', 'open')->sum('outstanding_amount');
            $unpaid  = (float) Contribution::where('member_id', $memberId)->where('group_id', $groupId)->whereIn('status', ['overdue', 'pending', 'partial'])->sum(DB::raw('expected_amount - paid_amount'));
            if ($arrears == 0 && $unpaid == 0) {
                return ['icon' => 'ti-circle-check', 'color' => 'success', 'answer' => "You have **no outstanding debts** in this group. Great work! ✓", 'links' => []];
            }
            return [
                'icon'   => 'ti-alert-triangle',
                'color'  => 'danger',
                'answer' => "You currently owe **{$fmt($arrears)}** in arrears and **{$fmt($unpaid)}** in unpaid contributions — total: **{$fmt($arrears + $unpaid)}**.",
                'links'  => [['label' => 'View Arrears', 'route' => 'arrears.index', 'param' => null]],
            ];
        }

        if ($this->matches($msg, ['share-out', 'shareout', 'payout', 'equity', 'end of cycle', 'what will i get', 'my share', 'dividend'])) {
            $loanDebt  = (float) DB::table('loans')->where('member_id', $memberId)->where('group_id', $groupId)->whereIn('status', ['disbursed', 'repaying'])->sum('outstanding');
            $arrears   = (float) DB::table('arrears')->where('member_id', $memberId)->where('group_id', $groupId)->where('status', 'open')->sum('outstanding_amount');
            $totalDebt = $loanDebt + $arrears;
            $projected = max(0, $savings - $totalDebt);
            return [
                'icon'   => 'ti-building-bank',
                'color'  => 'primary',
                'answer' => "Your current savings: **{$fmt($savings)}**. Outstanding debts: **{$fmt($totalDebt)}**. Estimated share-out: **{$fmt($projected)}** (before profit share is added).",
                'links'  => [['label' => 'My Equity Details', 'route' => 'treasury.member', 'param' => $memberId]],
            ];
        }

        if ($this->matches($msg, ['next', 'due', 'upcoming', 'when do i', 'next payment', 'when is my'])) {
            $next = Contribution::where('member_id', $memberId)->where('group_id', $groupId)
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('period_start')->first();
            if ($next) {
                $due = $next->due_on ? Carbon::parse($next->due_on)->format('d M Y') : Carbon::parse($next->period_end)->format('d M Y');
                $bal = $next->expected_amount + $next->late_fee_amount - $next->paid_amount;
                return [
                    'icon'   => 'ti-calendar-due',
                    'color'  => 'warning',
                    'answer' => "Your next contribution is **{$fmt($bal)}** for **{$next->period_start->format('F Y')}**, due by **{$due}**.",
                    'links'  => [['label' => 'View Contributions', 'route' => 'contributions.index', 'param' => null]],
                ];
            }
            return ['icon' => 'ti-circle-check', 'color' => 'success', 'answer' => "You have no upcoming contributions due right now.", 'links' => []];
        }

        if ($this->matches($msg, ['profit', 'interest', 'earnings', 'income', 'return', 'profit per share'])) {
            $totalShares = (int) DB::table('group_member')->where('group_id', $groupId)->where('is_active', 1)->sum('share_count');
            $myShares    = (int) DB::table('group_member')->where('group_id', $groupId)->where('member_id', $memberId)->value('share_count') ?? 1;
            $penalties   = (float) Contribution::where('group_id', $groupId)->sum('late_fee_amount');
            $interest    = (float) DB::table('loan_repayments')->join('loans','loans.id','loan_repayments.loan_id')->where('loans.group_id', $groupId)->sum('loan_repayments.interest_portion');
            $cashIncome  = (float) DB::table('cashbook_entries')->where('group_id', $groupId)->where('type','income')->whereNull('deleted_at')->sum('amount');
            $cashExpense = (float) DB::table('cashbook_entries')->where('group_id', $groupId)->where('type','expense')->whereNull('deleted_at')->sum('amount');
            $groupProfit = max(0, $penalties + $interest + $cashIncome - $cashExpense);
            $perShare    = $totalShares > 0 ? $groupProfit / $totalShares : 0;
            $myProfit    = $perShare * $myShares;
            return [
                'icon'   => 'ti-trending-up',
                'color'  => 'success',
                'answer' => "Group profit so far: **{$fmt($groupProfit)}**. With **{$myShares}** share" . ($myShares > 1 ? 's' : '') . " out of {$totalShares}, your estimated profit share is **{$fmt($myProfit)}** ({$fmt($perShare)} per share).",
                'links'  => [],
            ];
        }

        if ($this->matches($msg, ['member', 'group', 'how many', 'members in'])) {
            $count = DB::table('group_member')->where('group_id', $groupId)->where('is_active', 1)->count();
            $groupName = DB::table('groups')->where('id', $groupId)->value('name');
            return ['icon' => 'ti-users', 'color' => 'info', 'answer' => "**{$groupName}** has **{$count} active members**.", 'links' => []];
        }

        if ($this->matches($msg, ['hello', 'hi', 'hey', 'help', 'what can you', 'what do you'])) {
            return [
                'icon'   => 'ti-sparkles',
                'color'  => 'primary',
                'answer' => "Hi! I can answer questions about your account. Try asking:\n• *What is my savings balance?*\n• *Can I borrow 100,000?*\n• *How much do I owe?*\n• *When is my next payment?*\n• *What will I get at share-out?*\n• *What is my profit share?*",
                'links'  => [],
            ];
        }

        return [
            'icon'   => 'ti-help-circle',
            'color'  => 'secondary',
            'answer' => "I didn't understand that. Try asking about: **savings balance**, **loans**, **arrears**, **next payment**, **share-out payout**, or **profit share**.",
            'links'  => [],
        ];
    }

    protected function matches(string $input, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($input, $kw)) return true;
        }
        return false;
    }
}
