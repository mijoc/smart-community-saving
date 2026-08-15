<?php

namespace App\Http\Controllers;

use App\Models\Arrear;
use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\LoanInterestAccrual;
use App\Models\LoanRepayment;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use App\Services\AiInsightsService;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(TreasuryService $treasury, AiInsightsService $ai)
    {
        $user = auth()->user();

        // Members get a personal summary panel rendered on top of the
        // (read-only) group dashboard.
        $personal = null;
        if ($user->hasRole('member') && $user->member_id) {
            $personal = $this->personalSnapshot($user);
        }

        // Group-scoped dashboard. Non-super-admins are always pinned to
        // their active group — they never see aggregated info from the
        // other groups they happen to be assigned to.
        $activeId = session('active_group_id');
        $isSuper  = $user->isSuperAdmin();

        $scope = function ($q, string $col = 'group_id') use ($activeId, $isSuper) {
            if ($activeId)    return $q->where($col, $activeId);
            if (! $isSuper)   return $q->whereRaw('1 = 0');
            return $q;
        };

        // Treasury picture for the active scope:
        //  - current_balance = cash on hand (everything paid in — savings,
        //    loan repayments, donations, attendance fines, ... — minus
        //    money out: loans disbursed, expenses).
        //  - group_amount    = current balance + ALL outstanding money the
        //    group is owed (loan principal still out, interest still due,
        //    open arrears). Represents the group's total wealth.
        // For non-super-admins, the route is gated by an active group, so
        // $activeId is always set. Super-admins with no active group see
        // the global picture (every group consolidated).
        $treasuryData = $treasury->groupSummary($activeId ?: null, null);

        $currentBalance = (float) $treasuryData['cash_on_hand'];

        // Attendance fines that have been charged but not paid yet — these
        // are money the group is owed but the cashbook hasn't seen yet
        // (paid fines go into the cashbook as `attendance_fine` income).
        $attendanceOutstandingQ = MeetingAttendance::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_attendances.meeting_id')
            ->whereRaw('meeting_attendances.fine_amount > meeting_attendances.paid_amount');
        if ($activeId) {
            $attendanceOutstandingQ->where('meetings.group_id', $activeId);
        } elseif (! $isSuper) {
            $attendanceOutstandingQ->whereRaw('1 = 0');
        }
        $attendanceOutstanding = (float) $attendanceOutstandingQ
            ->sum(DB::raw('meeting_attendances.fine_amount - meeting_attendances.paid_amount'));

        $groupAmount    = round(
            $currentBalance
            + (float) $treasuryData['principal_outstanding']
            + (float) $treasuryData['interest_receivable']
            + (float) $treasuryData['open_arrears']
            + $attendanceOutstanding,
            2
        );

        // Group profit = everything the group EARNED on top of member capital.
        // Matches the Monthly Report formula exactly:
        //   penalties accrued (late fees on contributions)
        //   + loan interest accrued (from LoanInterestAccrual)
        //   + cashbook income (donations, bank interest, grants, …)
        // Member contributions (savings) are capital — returned at share-out —
        // so they are NOT profit. Using total_wealth − paid_savings would
        // conflate unreturned principal with earnings.
        $penaltiesAccrued = (float) (function () use ($scope) {
            return $scope(Contribution::query())->sum('late_fee_amount');
        })();

        $interestAccruedQ = LoanInterestAccrual::query()
            ->whereHas('loan', function ($q) use ($activeId, $isSuper) {
                if ($activeId)  $q->where('group_id', $activeId);
                elseif (! $isSuper) $q->whereRaw('1 = 0');
            });
        $interestAccrued = (float) $interestAccruedQ->sum('interest_amount');

        $memberContributions = (float) $treasuryData['member_equity_total'];
        $groupProfit         = round($penaltiesAccrued + $interestAccrued + (float) $treasuryData['cashbook_income'], 2);

        // "Total expected" = every shilling the group has booked as an
        // inflow, whether already collected or still pending. Covers:
        //   - all non-waived contributions (savings, social fund, fines,
        //     late-fee contributions, "other") — excluding loan-repayment
        //     installments to avoid double-counting their interest portion,
        //   - late-fee penalties stacked on top of contributions when
        //     they go overdue,
        //   - all loan interest the group expects to earn,
        //   - cashbook income (donations, bank interest, grants, …),
        //   - attendance fines charged at meetings.
        $totalExpected = (float) $treasuryData['total_expected_all'];

        $stats = [
            'total_expected'         => $totalExpected,
            'group_profit'           => $groupProfit,
            'member_contributions'   => round($memberContributions, 2),
            'current_balance'        => $currentBalance,
            // "Pending" loans = loans with money still owed to the group
            // (disbursed but not fully paid back yet). Loans awaiting
            // approval are tracked separately as `awaiting_approval`.
            'pending_loans'          => $scope(Loan::query()->whereIn('status', ['disbursed', 'repaying']))->count(),
            // Total money still owed across those open loans (principal +
            // interest still receivable). Comes straight from the
            // TreasuryService so it always agrees with /treasury.
            'pending_loans_amount'   => round(
                (float) $treasuryData['principal_outstanding']
                + (float) $treasuryData['interest_receivable'],
                2
            ),
            'pending_loans_principal' => round((float) $treasuryData['principal_outstanding'], 2),
            'pending_loans_interest'  => round((float) $treasuryData['interest_receivable'], 2),
            'awaiting_approval'      => $scope(Loan::query()->whereIn('status', ['requested', 'approved']))->count(),
            'attendance_outstanding' => round($attendanceOutstanding, 2),
            // "Pending" = booked but not yet due. We exclude rows whose
            // due date has already passed because those are effectively
            // overdue and counted separately below — even if the arrears
            // engine hasn't been run yet to flip their status.
            'contributions_pending'  => $scope(Contribution::query()
                ->whereIn('status', ['pending', 'partial'])
                ->where(function ($q) {
                    $q->whereNull('due_on')
                      ->orWhereDate('due_on', '>=', now()->toDateString());
                }))->count(),
            // Money still owed by members for those pending / partial
            // contributions (expected + any stacked late fees − whatever
            // they've already paid). Rendered as the big number on the
            // "Pending contributions" card.
            'contributions_pending_amount' => (float) $scope(Contribution::query()
                ->whereIn('status', ['pending', 'partial'])
                ->where(function ($q) {
                    $q->whereNull('due_on')
                      ->orWhereDate('due_on', '>=', now()->toDateString());
                }))->sum(DB::raw('expected_amount + late_fee_amount - paid_amount')),
            'groups_count'           => $activeId ? 1 :
                ($isSuper ? Group::where('status', 'active')->count() : 0),
            'members_count'          => $activeId
                ? Member::whereHas('groups', fn ($g) => $g->where('groups.id', $activeId))->where('status', 'active')->count()
                : ($isSuper
                    ? Member::where('status', 'active')->count()
                    : 0),
            // Overdue = explicitly marked overdue OR (still pending/partial
            // AND due_on already past). Catches the case where the arrears
            // engine hasn't been run yet — past-due rows are accurately
            // surfaced as overdue without waiting for a manual sweep.
            'contributions_overdue'  => $scope(Contribution::query()
                ->where(function ($q) {
                    $q->where('status', 'overdue')
                      ->orWhere(function ($qq) {
                          $qq->whereIn('status', ['pending', 'partial'])
                             ->whereDate('due_on', '<', now()->toDateString());
                      });
                }))->count(),
            // Outstanding balance across every overdue contribution row
            // (expected + stacked late fees − whatever has been paid).
            // Rendered as the big number on the "Overdue contributions" card.
            'contributions_overdue_amount' => (float) $scope(Contribution::query()
                ->where(function ($q) {
                    $q->where('status', 'overdue')
                      ->orWhere(function ($qq) {
                          $qq->whereIn('status', ['pending', 'partial'])
                             ->whereDate('due_on', '<', now()->toDateString());
                      });
                }))->sum(DB::raw('expected_amount + late_fee_amount - paid_amount')),
            'open_arrears_amount'    => (float) $treasuryData['open_arrears'],
            'contributions_expected' => (float) $scope(Contribution::query()
                ->whereNotIn('status', ['waived'])
            )->sum('expected_amount'),
            'unpaid_this_month'      => (float) $scope(Contribution::query()
                ->whereYear('period_start', now()->year)
                ->whereMonth('period_start', now()->month)
                ->whereNotIn('status', ['paid', 'waived'])
            )->sum(DB::raw('expected_amount - paid_amount')),
            'collected_this_month'   => (float) $scope(Payment::query()
                ->whereMonth('paid_on', now()->month)
                ->whereYear('paid_on', now()->year))->sum('amount'),
            'other_income_month'     => (float) $scope(CashbookEntry::query()
                ->where('type', 'income')
                ->whereMonth('occurred_on', now()->month)
                ->whereYear('occurred_on', now()->year))->sum('amount'),
            'expenses_month'         => (float) $scope(CashbookEntry::query()
                ->where('type', 'expense')
                ->whereMonth('occurred_on', now()->month)
                ->whereYear('occurred_on', now()->year))->sum('amount'),
        ];

        // ── This-month financial summary (mirrors Monthly Report Section C) ─────
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();
        $prevEnd    = now()->subMonth()->endOfMonth();

        $monthlyFinancial = $this->buildMonthSummary($activeId, $isSuper, $monthStart, $monthEnd, $prevEnd);

        $recentPayments = $scope(Payment::query())
            ->with(['member:id,full_name,member_no', 'group:id,name'])
            ->latest('paid_on')->limit(8)->get();

        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', paid_on)",
            'pgsql'  => "to_char(paid_on, 'YYYY-MM')",
            default  => "DATE_FORMAT(paid_on, '%Y-%m')",
        };
        $monthly = $scope(Payment::query())
            ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(amount) as total'))
            ->where('paid_on', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(DB::raw($monthExpr))->orderBy('month')->get();

        $topGroupsQuery = Group::query()
            ->withCount(['activeMembers as members_count'])
            ->withSum(['payments as collected_total' => function ($q) {
                $q->where('paid_on', '>=', now()->subMonths(3));
            }], 'amount')
            ->orderByDesc('collected_total')
            ->limit(6);
        if ($activeId)        $topGroupsQuery->where('id', $activeId);
        elseif (! $isSuper)   $topGroupsQuery->whereRaw('1 = 0');
        $topGroups = $topGroupsQuery->get();

        $arrears = $scope(Arrear::query()->where('status', 'open'))
            ->with(['member:id,full_name,member_no', 'group:id,name'])
            ->orderByDesc('outstanding_amount')
            ->limit(8)->get();

        // Members get their own dedicated dashboard view
        if ($user->hasRole('member') && $user->member_id) {
            $recent = Contribution::where('member_id', $user->member_id)
                ->when($activeId, fn($q) => $q->where('group_id', $activeId))
                ->with('group:id,name')
                ->latest('period_start')->limit(6)->get();

            // Group-level repayment rate: paid contributions vs total
            $rateTotal = $scope(Contribution::query()->whereNotIn('status', ['waived']))->sum('expected_amount');
            $ratePaid  = $scope(Contribution::query())->sum('paid_amount');
            $repaymentRate = $rateTotal > 0 ? min(100, round($ratePaid / $rateTotal * 100)) : 0;

            // Recent group activities for the member dashboard content area
            $memberActivities = $activeId
                ? \App\Models\Activity::where('group_id', $activeId)
                    ->orderByDesc('created_at')->limit(6)->get()
                : collect();

            // Extra stats for the Group Overview donut
            $memberExpContrib = (float) Contribution::where('member_id', $user->member_id)
                ->when($activeId, fn($q) => $q->where('group_id', $activeId))
                ->whereNotIn('status', ['waived'])
                ->sum('expected_amount');
            $totalGroupShares = max(1, (int) DB::table('group_member')
                ->when($activeId, fn($q) => $q->where('group_id', $activeId))
                ->where('is_active', 1)
                ->sum('share_count'));
            $stats['member_contributions_expected'] = $memberExpContrib;
            $stats['total_group_shares']            = $totalGroupShares;
            $stats['profit_per_share']              = round($groupProfit / $totalGroupShares, 2);

            // AI Insights for member dashboard
            $aiRisk     = $activeId ? $ai->loanRiskScore($user->member_id, $activeId) : null;
            $aiForecast = $activeId ? $ai->cashFlowForecast($activeId, 3) : null;
            $aiHealth   = $activeId ? $ai->financialHealthSummary($activeId) : null;

            return view('dashboard.member', compact(
                'personal', 'stats', 'recent', 'monthlyFinancial', 'repaymentRate', 'memberActivities',
                'aiRisk', 'aiForecast', 'aiHealth'
            ));
        }

        // AI Insights for admin/treasurer dashboard
        $aiAnomalies  = $activeId ? $ai->detectAnomalies($activeId) : [];
        $aiHealth     = $activeId ? $ai->financialHealthSummary($activeId) : null;
        $aiRiskSummary= $activeId ? $ai->groupRiskSummary($activeId) : null;

        return view('dashboard.index', compact('stats', 'recentPayments', 'monthly', 'topGroups', 'arrears', 'personal', 'monthlyFinancial', 'aiAnomalies', 'aiHealth', 'aiRiskSummary'));
    }

    /** Monthly financial summary — mirrors Monthly Report Section C. */
    protected function buildMonthSummary(?int $activeId, bool $isSuper, Carbon $start, Carbon $end, Carbon $prevEnd): array
    {
        // No active group and not super-admin → return zeros
        if (! $activeId && ! $isSuper) {
            return array_fill_keys(['opening_balance','contributions','disbursements','repayments','interest_earned','withdrawals','other_income','expenses','closing_balance'], 0.0);
        }

        $gWhere = fn ($q, string $col = 'group_id') => $activeId ? $q->where($col, $activeId) : $q;

        // Opening balance = cash position at end of previous month
        $payBefore    = (float) $gWhere(Payment::query())->where('paid_on', '<=', $prevEnd->toDateString())->sum('amount');
        $cashInBefore = (float) $gWhere(CashbookEntry::query()->where('type', 'income'))->where('occurred_on', '<=', $prevEnd->toDateString())->sum('amount');
        $cashOutBefore= (float) $gWhere(CashbookEntry::query()->where('type', 'expense'))->where('occurred_on', '<=', $prevEnd->toDateString())->sum('amount');
        $disBefore    = (float) $gWhere(Loan::query()->whereIn('status', ['disbursed','repaying','paid'])->whereNotNull('disbursed_on'))->where('disbursed_on', '<=', $prevEnd->toDateString())->sum('principal');
        $wdBefore     = (float) $gWhere(PassbookEntry::query()->where('category', 'withdrawal'))->where('entry_date', '<=', $prevEnd->toDateString())->sum('credit');
        $opening      = $payBefore + $cashInBefore - $cashOutBefore - $disBefore - $wdBefore;

        // This-month flows
        $contributions = (float) $gWhere(Payment::query())->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])->sum('amount');

        $disbursements = (float) $gWhere(Loan::query()->whereIn('status', ['disbursed','repaying','paid'])->whereNotNull('disbursed_on'))->whereBetween('disbursed_on', [$start->toDateString(), $end->toDateString()])->sum('principal');

        $repRow = LoanRepayment::query()
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->selectRaw('SUM(loan_repayments.principal_portion) AS principal, SUM(loan_repayments.interest_portion) AS interest')
            ->when($activeId, fn ($q) => $q->where('loans.group_id', $activeId))
            ->whereBetween('loan_repayments.paid_on', [$start->toDateString(), $end->toDateString()])
            ->first();
        $repayments    = (float) ($repRow->principal ?? 0);
        $interestEarned= (float) ($repRow->interest  ?? 0);

        $withdrawals  = (float) $gWhere(PassbookEntry::query()->where('category', 'withdrawal'))->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])->sum('credit');
        $otherIncome  = (float) $gWhere(CashbookEntry::query()->where('type', 'income'))->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])->sum('amount');
        $expenses     = (float) $gWhere(CashbookEntry::query()->where('type', 'expense'))->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])->sum('amount');

        $closing = $opening + $contributions + $repayments + $interestEarned - $disbursements - $withdrawals + ($otherIncome - $expenses);

        return [
            'opening_balance'  => round($opening, 2),
            'contributions'    => round($contributions, 2),
            'disbursements'    => round($disbursements, 2),
            'repayments'       => round($repayments, 2),
            'interest_earned'  => round($interestEarned, 2),
            'withdrawals'      => round($withdrawals, 2),
            'other_income'     => round($otherIncome, 2),
            'expenses'         => round($expenses, 2),
            'closing_balance'  => round($closing, 2),
        ];
    }

    /** Personal stats shown to a member at the top of the group dashboard. */
    protected function personalSnapshot($user): array
    {
        $member   = $user->member;
        $activeId = session('active_group_id');

        $base = Contribution::where('member_id', $member->id);
        if ($activeId) $base->where('group_id', $activeId);

        $arrearBase = Arrear::where('member_id', $member->id);
        if ($activeId) $arrearBase->where('group_id', $activeId);

        $stats = [
            'pending' => (clone $base)->whereIn('status', ['pending', 'partial'])->count(),
            'overdue' => (clone $base)->where('status', 'overdue')->count(),
            'paid'    => (clone $base)->where('status', 'paid')->count(),
            'arrears' => (float) (clone $arrearBase)->where('status', 'open')->sum('outstanding_amount'),
        ];

        return [
            'member' => $member,
            'stats'  => $stats,
        ];
    }
}
