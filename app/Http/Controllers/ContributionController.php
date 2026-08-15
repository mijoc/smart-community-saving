<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Member;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ContributionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Contribution::class, 'contribution');
    }

    public function index(Request $request)
    {
        $u             = auth()->user();
        $activeGroupId = session('active_group_id');

        // ── Members default to their own ledger (unless they tapped a specific
        //    member card on the picker grid, in which case let them through) ──
        if ($u->hasRole('member') && $u->member_id
            && $request->string('view')->toString() !== 'group'
            && ! $request->integer('member_id')
        ) {
            return $this->memberLedger($request, Member::find($u->member_id), selfView: true);
        }

        // ── A specific member has been selected → show their ledger ──────────
        if ($memberId = $request->integer('member_id')) {
            $member = Member::findOrFail($memberId);
            if ($activeGroupId && ! $member->groups()->where('groups.id', $activeGroupId)->exists()) {
                abort(403);
            }
            return $this->memberLedger($request, $member, selfView: false);
        }

        // ── Default: member-picker grid ───────────────────────────────────────
        $memberQuery = Member::query()
            ->with(['contributions' => function ($q) use ($activeGroupId) {
                $q->when($activeGroupId, fn ($q) => $q->where('group_id', $activeGroupId))
                  ->select('id', 'member_id', 'status', 'expected_amount', 'paid_amount', 'late_fee_amount');
            }]);

        if ($activeGroupId) {
            $memberQuery->whereHas('groups', fn ($g) => $g->where('groups.id', $activeGroupId));
        } elseif (! $u->isSuperAdmin()) {
            $memberQuery->whereHas('groups', fn ($g) =>
                $g->whereIn('groups.id', $this->accessibleGroupOptions()->pluck('id'))
            );
        }

        $members = $memberQuery->orderBy('member_no')->get();

        return view('contributions.index', [
            'members'       => $members,
            'groups'        => $this->accessibleGroupOptions(),
            'activeGroupId' => $activeGroupId,
        ]);
    }

    // ── Shared helper: build + return a single member's contribution ledger ──
    private function memberLedger(Request $request, ?Member $member, bool $selfView)
    {
        $activeGroupId = session('active_group_id');

        // Build the base filtered query (shared by both paginated list and totals)
        $base = Contribution::query();
        $this->scopeToActiveGroup($base);

        if ($member) {
            $base->where('member_id', $member->id);
        }

        if ($s    = $request->string('status')->toString()) $base->where('status', $s);
        if ($t    = $request->string('type')->toString())   $base->where('type', $t);
        if ($from = $request->string('from')->toString())   $base->whereDate('due_on', '>=', $from);
        if ($to   = $request->string('to')->toString())     $base->whereDate('due_on', '<=', $to);

        // Filtered totals (all rows matching current filters, not just current page)
        $filteredTotals = (clone $base)
            ->selectRaw("
                COUNT(*) as row_count,
                COALESCE(SUM(expected_amount), 0)                              as sum_expected,
                COALESCE(SUM(late_fee_amount), 0)                              as sum_late_fee,
                COALESCE(SUM(paid_amount), 0)                                  as sum_paid,
                COALESCE(SUM(expected_amount + late_fee_amount - paid_amount), 0) as sum_balance
            ")
            ->first();

        // Paginated list with relationships (group.rules + schedule needed for penalty schedule)
        $contributions = (clone $base)
            ->with(['group.rules', 'schedule'])
            ->orderByDesc('due_on')
            ->paginate(25)
            ->withQueryString();

        // Pre-compute penalty schedules for overdue/partial rows on this page
        $penaltySchedules = [];
        foreach ($contributions as $c) {
            if (in_array($c->status, ['overdue', 'partial']) && $c->due_on?->isPast()) {
                $sched = $this->buildPenaltySchedule($c);
                if (! empty($sched)) {
                    $penaltySchedules[$c->id] = $sched;
                }
            }
        }

        // Header-card stats (all-time for this member in this group, unfiltered)
        $stats = null;
        if ($member && $activeGroupId) {
            $stats = Contribution::where('member_id', $member->id)
                ->where('group_id', $activeGroupId)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status='paid'                    THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN status='overdue'                 THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN status IN ('pending','partial')  THEN 1 ELSE 0 END) as pending_count,
                    COALESCE(SUM(expected_amount), 0) as total_expected,
                    COALESCE(SUM(paid_amount), 0)     as total_paid
                ")
                ->first();
        }

        return view('contributions.member', [
            'member'           => $member,
            'contributions'    => $contributions,
            'filteredTotals'   => $filteredTotals,
            'groups'           => $this->accessibleGroupOptions(),
            'selfView'         => $selfView,
            'stats'            => $stats,
            'penaltySchedules' => $penaltySchedules,
        ]);
    }

    public function create(Request $request)
    {
        $groups   = $this->accessibleGroupOptions();
        $activeId = session('active_group_id');

        $members = Member::query()
            ->when(! auth()->user()->isSuperAdmin() || $activeId, function ($q) use ($groups, $activeId) {
                $ids = $activeId ? [$activeId] : $groups->pluck('id')->all();
                $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $ids));
            })
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'member_no']);

        return view('contributions.create', [
            'groups'  => $groups,
            'members' => $members,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id'        => ['required', 'exists:groups,id'],
            'member_id'       => ['required', 'exists:members,id'],
            'type'            => ['required', 'in:savings,social_fund,loan_repayment,fine,late_fee,other'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'period_start'    => ['required', 'date'],
            'period_end'      => ['required', 'date', 'after_or_equal:period_start'],
            'due_on'          => ['required', 'date'],
            'notes'           => ['nullable', 'string'],
        ]);

        if (! auth()->user()->canAccessGroup((int) $data['group_id'])) {
            abort(403, 'You cannot create contributions in that group.');
        }

        $data['created_by'] = auth()->id();
        $data['status']     = 'pending';
        $c = Contribution::create($data);

        $member = Member::find($c->member_id);
        ActivityLogger::log(
            groupId: $c->group_id,
            type: 'contribution.created',
            description: "scheduled a {$c->type} contribution for {$member?->full_name}",
            subject: $c,
            icon: 'clipboard-list',
            color: 'blue',
            data: ['amount' => number_format((float) $c->expected_amount, 2), 'due_on' => $c->due_on->format('Y-m-d')],
        );

        return redirect()->route('contributions.show', $c)->with('status', 'Contribution recorded.');
    }

    public function show(Contribution $contribution)
    {
        if (! auth()->user()->canAccessGroup($contribution->group_id)) {
            abort(403);
        }
        $contribution->load(['group.rules', 'member', 'schedule', 'payments.receiver', 'arrear', 'paymentRequests']);

        $penaltySchedule = $this->buildPenaltySchedule($contribution);

        return view('contributions.show', ['c' => $contribution, 'penaltySchedule' => $penaltySchedule]);
    }

    /**
     * Build a per-period penalty projection table for the contribution detail view.
     *
     * Shows every elapsed period (past/charged) plus 4 upcoming projected periods,
     * so members can see exactly how compound fees will grow if they don't pay.
     */
    protected function buildPenaltySchedule(Contribution $c): array
    {
        if (! $c->due_on || in_array($c->status, ['paid', 'waived'])) {
            return [];
        }
        if (! $c->due_on->isPast()) {
            return [];
        }

        $E    = (float) $c->expected_amount;
        $pct  = (float) ($c->schedule?->late_fee_pct  ?: $c->group->rule('late_fee_pct',  config('vsla.late_fee_pct')));
        $flat = (float) ($c->schedule?->late_fee_flat ?: $c->group->rule('late_fee_flat', 0));

        if ($pct <= 0 && $flat <= 0) {
            return [];
        }

        $compounding    = (bool) $c->group->rule('penalty_on_penalty', false);
        $frequency      = $c->schedule?->frequency ?? 'monthly';
        $alreadyCharged = (float) $c->late_fee_amount;
        $dueOn          = $c->due_on->copy();
        $today          = now()->startOfDay();

        $curPeriods = match ($frequency) {
            'weekly'      => (int) $dueOn->diffInWeeks($today) + 1,
            'fortnightly' => (int) floor($dueOn->diffInDays($today) / 14) + 1,
            'quarterly'   => (int) floor($dueOn->diffInMonths($today) / 3) + 1,
            default       => (int) $dueOn->diffInMonths($today) + 1,
        };
        $curPeriods  = max(1, $curPeriods);
        $showPeriods = min($curPeriods + 4, 24);

        $schedule = [];
        for ($n = 1; $n <= $showPeriods; $n++) {
            // The date from which period N penalty applies
            $periodFrom = match ($frequency) {
                'weekly'      => $dueOn->copy()->addWeeks($n - 1)->addDay(),
                'fortnightly' => $dueOn->copy()->addDays(($n - 1) * 14)->addDay(),
                'quarterly'   => $dueOn->copy()->addMonths(($n - 1) * 3)->addDay(),
                default       => $dueOn->copy()->addMonths($n - 1)->addDay(),
            };

            // Flat equivalent — always computed for comparison purposes
            $flatFeePerPeriod = round($flat + ($E * $pct / 100), 2);
            $flatTotalAtN     = round($flatFeePerPeriod * $n, 2);

            if ($compounding && $pct > 0) {
                $r             = $pct / 100;
                $totalFeeAtN   = round($flat * $n + $E * (pow(1 + $r, $n) - 1), 2);
                $totalFeeAtN1  = $n > 1 ? round($flat * ($n - 1) + $E * (pow(1 + $r, $n - 1) - 1), 2) : 0;
                $feeThisPeriod = round($totalFeeAtN - $totalFeeAtN1, 2);
            } else {
                $feeThisPeriod = $flatFeePerPeriod;
                $totalFeeAtN   = $flatTotalAtN;
            }

            $compoundExtra = round($totalFeeAtN - $flatTotalAtN, 2);  // 0 when not compounding

            $schedule[] = [
                'n'              => $n,
                'from'           => $periodFrom,
                'fee'            => $feeThisPeriod,
                'flat_fee'       => $flatFeePerPeriod,
                'total_fee'      => $totalFeeAtN,
                'flat_total'     => $flatTotalAtN,
                'compound_extra' => $compoundExtra,
                'total_owed'     => max(0, round($E + $totalFeeAtN - (float) $c->paid_amount, 2)),
                'is_future'      => $n > $curPeriods,
                'is_current'     => $n === $curPeriods,
                'is_charged'     => !($n > $curPeriods) && $alreadyCharged >= $totalFeeAtN - 0.01,
            ];
        }

        return $schedule;
    }

    public function destroy(Contribution $contribution)
    {
        $gid    = $contribution->group_id;
        $member = $contribution->member?->full_name;
        $contribution->delete();
        ActivityLogger::log(
            groupId: $gid,
            type: 'contribution.removed',
            description: "removed a contribution for {$member}",
            icon: 'trash',
            color: 'red',
        );
        return redirect()->route('contributions.index')->with('status', 'Contribution removed.');
    }

    public function waive(Contribution $contribution)
    {
        $this->authorize('update', $contribution);
        $contribution->update(['status' => 'waived']);
        ActivityLogger::log(
            groupId: $contribution->group_id,
            type: 'contribution.waived',
            description: "waived a {$contribution->type} contribution for {$contribution->member?->full_name}",
            subject: $contribution,
            icon: 'discount-2',
            color: 'yellow',
            data: ['amount' => number_format((float) $contribution->expected_amount, 2)],
        );
        return back()->with('status', 'Contribution waived.');
    }
}
