<?php

namespace App\Http\Controllers;

use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Payment;
use App\Services\TreasuryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(protected TreasuryService $treasury) {}

    /**
     * Group wealth dashboard — consolidated view of cash on hand, loans
     * outstanding, interest earned and the overall group fund.
     */
    public function index(Request $request)
    {
        $user      = auth()->user();
        $activeId  = session('active_group_id');

        // Non-super-admins are pinned to the currently active group.
        // Super admins (with no active group) get a global view; super
        // admins with an active group get that group's view.
        if ($user->isSuperAdmin()) {
            $accessIds = null;
        } else {
            $accessIds = $activeId ? collect([(int) $activeId]) : collect([0]);
        }

        $summary = $this->treasury->groupSummary($activeId, $accessIds);

        $activeGroup = $activeId ? Group::find($activeId) : null;
        $currency    = $activeGroup?->currency ?? 'RWF';

        // Open-loans table for context.
        $openLoans = Loan::query()
            ->with(['member:id,full_name,member_no', 'group:id,name,currency'])
            ->whereIn('status', ['disbursed', 'repaying']);
        $this->scopeToActiveGroup($openLoans);
        $openLoans = $openLoans->orderByDesc('outstanding')->limit(10)->get();

        // Recent cashbook movements (income & expense).
        $recentCashbook = CashbookEntry::query()
            ->with(['group:id,name'])
            ->orderByDesc('occurred_on')->orderByDesc('id');
        $this->scopeToActiveGroup($recentCashbook);
        if (! $user->hasAnyRole(['super_admin', 'group_admin'])) {
            $recentCashbook->where('category', '!=', CashbookEntry::REGULARIZATION_CATEGORY);
        }
        $recentCashbook = $recentCashbook->limit(8)->get();

        // Recent member payments (cash inflow).
        $recentPayments = Payment::query()
            ->with(['member:id,full_name,member_no', 'group:id,name'])
            ->orderByDesc('paid_on')->orderByDesc('id');
        $this->scopeToActiveGroup($recentPayments);
        $recentPayments = $recentPayments->limit(8)->get();

        // Per-member equity & debt table — only meaningful when scoped to a
        // single group. Skip for the super-admin global view.
        $memberRows   = [];
        $memberTotals = null;
        if ($activeGroup) {
            $members = $activeGroup->members()
                ->orderBy('members.full_name')
                ->get(['members.id', 'members.full_name', 'members.member_no']);

            $totalEquity = 0.0;
            $totalDebt   = 0.0;
            $totalNet    = 0.0;

            foreach ($members as $m) {
                $s         = $this->treasury->memberSummary($m, (int) $activeGroup->id);
                $equity    = (float) ($s['savings_paid'] ?? 0);
                $other     = (float) ($s['social_fund_paid'] ?? 0) + (float) ($s['fines_paid'] ?? 0);
                $loanPrin  = (float) ($s['loan_principal_due'] ?? 0);
                $loanInt   = (float) ($s['loan_interest_due'] ?? 0);
                $otherDue  = (float) ($s['contributions_due'] ?? 0)
                           + (float) ($s['attendance_fines_due'] ?? 0);
                $debt      = (float) ($s['total_debt'] ?? 0);
                $net       = round($equity - $debt, 2);

                $memberRows[] = [
                    'member'         => $m,
                    'savings'        => round($equity, 2),
                    'other_equity'   => round($other, 2),
                    'loan_principal' => round($loanPrin, 2),
                    'loan_interest'  => round($loanInt, 2),
                    'other_due'      => round($otherDue, 2),
                    'total_debt'     => round($debt, 2),
                    'net_position'   => $net,
                ];

                $totalEquity += $equity;
                $totalDebt   += $debt;
                $totalNet    += $net;
            }

            $memberTotals = [
                'savings'      => round($totalEquity, 2),
                'total_debt'   => round($totalDebt, 2),
                'net_position' => round($totalNet, 2),
            ];
        }

        return view('treasury.index', [
            'summary'        => $summary,
            'currency'       => $currency,
            'activeGroup'    => $activeGroup,
            'openLoans'      => $openLoans,
            'recentCashbook' => $recentCashbook,
            'recentPayments' => $recentPayments,
            'memberRows'     => $memberRows,
            'memberTotals'   => $memberTotals,
        ]);
    }

    /**
     * HTML preview of the full treasury report — allows review before downloading.
     */
    public function reportPreview(Request $request)
    {
        $activeId = session('active_group_id');
        if (! $activeId) abort(400, 'Select a group first before previewing the report.');

        $group = Group::findOrFail($activeId);
        $this->authorize('view', $group);

        $data = $this->buildReportData((int) $activeId);
        $data['preview'] = true;

        return response(view('reports.treasury_pdf', $data)->render())
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Full treasury PDF download — group summary + per-member contributions & loans.
     */
    public function fullReport(Request $request)
    {
        $activeId = session('active_group_id');
        if (! $activeId) abort(400, 'Select a group first before generating the report.');

        $group = Group::findOrFail($activeId);
        $this->authorize('view', $group);

        $data = $this->buildReportData((int) $activeId);
        $data['preview'] = false;

        $pdf = Pdf::loadView('reports.treasury_pdf', $data)->setPaper('a4', 'landscape');

        $filename = 'treasury-report-'.preg_replace('/\s+/', '_', $group->name).'-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Shared data loader for both the preview and the PDF download.
     * Authorization must be performed by the calling public method.
     */
    private function buildReportData(int $activeId): array
    {
        $group    = Group::findOrFail($activeId);
        $currency = $group->currency ?? 'RWF';
        $summary  = $this->treasury->groupSummary($activeId, null);

        $members = $group->members()
            ->orderBy('members.full_name')
            ->get(['members.id', 'members.full_name', 'members.member_no', 'members.phone']);

        $memberIds = $members->pluck('id');

        // Two bulk queries instead of N — contributions and loans for all members.
        $allContributions = Contribution::where('group_id', $activeId)
            ->whereIn('member_id', $memberIds)
            ->orderBy('due_on')
            ->get()
            ->groupBy('member_id');

        $allLoans = Loan::where('group_id', $activeId)
            ->whereIn('member_id', $memberIds)
            ->with(['repayments' => fn ($q) => $q->where('status', 'approved')->orderBy('paid_on')])
            ->orderByDesc('disbursed_on')
            ->get()
            ->groupBy('member_id');

        $memberData = [];
        foreach ($members as $member) {
            // Pre-computed group summary prevents memberSummary() re-running groupSummary() per member.
            $ms            = $this->treasury->memberSummary($member, $activeId, $summary);
            $contributions = $allContributions->get($member->id, collect());
            $loans         = $allLoans->get($member->id, collect());

            $contribStats = [
                'expected'      => (float) $contributions->where('status', '!=', 'waived')->sum('expected_amount'),
                'paid'          => (float) $contributions->sum('paid_amount'),
                'paid_count'    => $contributions->where('status', 'paid')->count(),
                'pending'       => (float) $contributions->whereIn('status', ['pending'])->sum('expected_amount'),
                'pending_count' => $contributions->where('status', 'pending')->count(),
                'overdue_amount'=> (float) $contributions->where('status', 'overdue')
                    ->sum(fn ($c) => max(0, (float)$c->expected_amount + (float)$c->late_fee_amount - (float)$c->paid_amount)),
                'overdue_count' => $contributions->where('status', 'overdue')->count(),
                'partial_amount'=> (float) $contributions->where('status', 'partial')
                    ->sum(fn ($c) => max(0, (float)$c->expected_amount - (float)$c->paid_amount)),
                'partial_count' => $contributions->where('status', 'partial')->count(),
            ];

            $memberData[] = [
                'member'            => $member,
                'summary'           => $ms,
                'contributions'     => $contributions,
                'contribution_stats'=> $contribStats,
                'loans'             => $loans,
            ];
        }

        return compact('group', 'currency', 'summary', 'memberData');
    }

    /**
     * Per-member equity, debt and projected share-out.
     * Members can view themselves; staff can view any member they share a
     * group with — same rules as the passbook page.
     */
    public function member(Request $request, Member $member)
    {
        $this->authorize('view', $member);

        $user = auth()->user();

        // Pick which group this member is being viewed inside.
        // Defaults to the active group (if the member belongs to it),
        // otherwise the first group they share with the viewer.
        $memberGroups = $member->groups()->get(['groups.id', 'groups.name', 'groups.currency']);

        // Non-super-admins are pinned to the active group — even for the
        // per-member treasury view they don't get to peek into the other
        // groups they happen to share with this member.
        $activeIdForMember = (int) session('active_group_id');
        if ($user->isSuperAdmin()) {
            $candidateGroups = $memberGroups;
        } else {
            $candidateGroups = $activeIdForMember
                ? $memberGroups->where('id', $activeIdForMember)->values()
                : collect();
        }

        if ($candidateGroups->isEmpty()) {
            abort(404, 'No accessible group is shared with this member.');
        }

        $requestedGroupId = (int) $request->integer('group_id');
        $activeId         = (int) session('active_group_id');

        $currentGroup = $candidateGroups->firstWhere('id', $requestedGroupId)
            ?? $candidateGroups->firstWhere('id', $activeId)
            ?? $candidateGroups->first();

        $summary = $this->treasury->memberSummary($member, (int) $currentGroup->id);

        return view('treasury.member', [
            'member'         => $member,
            'summary'        => $summary,
            'currentGroup'   => $currentGroup,
            'memberGroups'   => $candidateGroups,
            'currency'       => $currentGroup->currency ?? 'RWF',
        ]);
    }
}
