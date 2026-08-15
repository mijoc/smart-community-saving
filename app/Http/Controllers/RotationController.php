<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Rotation;
use App\Models\RotationTurn;
use App\Services\ActivityLogger;
use App\Services\RotationService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;

class RotationController extends Controller
{
    public function __construct(
        protected RotationService $service,
        protected TreasuryService $treasury,
    ) {
        $this->authorizeResource(Rotation::class, 'rotation');
    }

    public function index(Request $request)
    {
        $this->blockIfRotationsDisabled();

        $q = Rotation::query()
            ->with(['group:id,name,currency', 'members'])
            ->withCount(['turns as paid_turns_count' => fn ($t) => $t->where('status', 'paid')]);

        $this->scopeToActiveGroup($q);

        if ($s = $request->string('status')->toString()) {
            $q->where('status', $s);
        }

        $rotations = $q->orderByDesc('status')->orderByDesc('starts_on')->paginate(15)->withQueryString();

        return view('rotations.index', [
            'rotations' => $rotations,
        ]);
    }

    public function create(Request $request)
    {
        $this->blockIfRotationsDisabled();
        $activeId = (int) session('active_group_id');
        $groups   = $this->accessibleGroupOptions();

        $members = Member::query()
            ->when($activeId, fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('groups.id', $activeId)))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'member_no']);

        return view('rotations.create', [
            'groups'  => $groups,
            'members' => $members,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id'             => ['required', 'exists:groups,id'],
            'name'                 => ['required', 'string', 'max:160'],
            'frequency'            => ['required', 'in:daily,weekly,monthly'],
            'recipients_per_turn'  => ['required', 'integer', 'min:1', 'max:50'],
            'disbursement_method'  => ['required', 'in:full,percentage,fixed'],
            'disbursement_pct'     => ['nullable', 'required_if:disbursement_method,percentage', 'numeric', 'min:0.01', 'max:100'],
            'disbursement_amount'  => ['nullable', 'required_if:disbursement_method,fixed', 'numeric', 'min:0.01'],
            'starts_on'            => ['required', 'date'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'member_ids'           => ['required', 'array', 'min:1'],
            'member_ids.*'         => ['integer', 'exists:members,id'],
        ]);

        if (! auth()->user()->canAccessGroup((int) $data['group_id'])) abort(403);

        if ((int) $data['recipients_per_turn'] > count($data['member_ids'])) {
            return back()->withInput()->withErrors([
                'recipients_per_turn' => 'Recipients per turn cannot exceed the number of members in the rotation.',
            ]);
        }

        $rotation = $this->service->create(
            collect($data)->except('member_ids')->toArray(),
            $data['member_ids'],
            (int) auth()->id(),
        );

        ActivityLogger::log(
            groupId: $rotation->group_id,
            type: 'rotation.created',
            description: "Rotation '{$rotation->name}' created",
            subject: $rotation,
            icon: 'rotate-clockwise',
            color: 'cyan',
            data: [
                'frequency' => $rotation->frequency,
                'members'   => count($data['member_ids']),
                'rule'      => $rotation->disbursementLabel(),
            ],
        );

        return redirect()->route('rotations.show', $rotation)
            ->with('status', "Rotation '{$rotation->name}' created.");
    }

    public function show(Rotation $rotation)
    {
        $rotation->load([
            'group',
            'members.member:id,full_name,member_no',
            'turns.payouts.member:id,full_name,member_no',
            'turns.executor:id,name',
        ]);

        $nextTurn        = $rotation->turns->firstWhere('status', 'scheduled');
        $plannedAmount   = $nextTurn ? $this->service->plannedAmount($rotation) : 0;
        $nextRecipients  = $nextTurn ? $this->service->nextRecipients($rotation) : collect();
        $cashOnHand      = (float) $this->treasury->groupSummary((int) $rotation->group_id, null)['cash_on_hand'];

        return view('rotations.show', [
            'rotation'       => $rotation,
            'nextTurn'       => $nextTurn,
            'plannedAmount'  => $plannedAmount,
            'nextRecipients' => $nextRecipients,
            'cashOnHand'     => $cashOnHand,
        ]);
    }

    public function destroy(Rotation $rotation)
    {
        $reason = request()->string('reason')->toString() ?: null;
        $this->service->cancel($rotation, $reason);

        ActivityLogger::log(
            groupId: $rotation->group_id,
            type: 'rotation.cancelled',
            description: "Rotation '{$rotation->name}' cancelled",
            subject: $rotation,
            icon: 'rotate-clockwise',
            color: 'red',
        );

        return redirect()->route('rotations.index')->with('status', 'Rotation cancelled.');
    }

    /** POST /rotations/{rotation}/turns/{turn}/execute */
    public function executeTurn(Request $request, Rotation $rotation, RotationTurn $turn)
    {
        $this->authorize('execute', $rotation);
        abort_if($turn->rotation_id !== $rotation->id, 404);

        $data = $request->validate([
            'amount'  => ['nullable', 'numeric', 'min:0.01'],
            'paid_on' => ['nullable', 'date'],
            'method'  => ['nullable', 'in:cash,mobile_money,bank,cheque,other'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ]);

        $turn = $this->service->executeTurn(
            $turn,
            (int) auth()->id(),
            $data['amount']  ?? null,
            $data['paid_on'] ?? null,
            $data['method']  ?? 'cash',
            $data['notes']   ?? null,
        );

        $names = $turn->payouts->pluck('member.full_name')->filter()->implode(', ');
        ActivityLogger::log(
            groupId: $rotation->group_id,
            type: 'rotation.turn.paid',
            description: "Rotation '{$rotation->name}' turn #{$turn->sequence_no} paid out to {$names}",
            subject: $rotation,
            icon: 'cash-banknote',
            color: 'green',
            data: [
                'total'      => number_format((float) $turn->disbursement_total, 2),
                'recipients' => $turn->payouts->count(),
            ],
        );

        return redirect()->route('rotations.show', $rotation)
            ->with('status', "Turn #{$turn->sequence_no} disbursed.");
    }

    /** POST /rotations/{rotation}/turns/{turn}/skip */
    public function skipTurn(Request $request, Rotation $rotation, RotationTurn $turn)
    {
        $this->authorize('execute', $rotation);
        abort_if($turn->rotation_id !== $rotation->id, 404);

        $reason = $request->string('reason')->toString() ?: null;
        $this->service->skipTurn($turn, (int) auth()->id(), $reason);

        ActivityLogger::log(
            groupId: $rotation->group_id,
            type: 'rotation.turn.skipped',
            description: "Rotation '{$rotation->name}' turn #{$turn->sequence_no} skipped",
            subject: $rotation,
            icon: 'player-skip-forward',
            color: 'yellow',
        );

        return redirect()->route('rotations.show', $rotation)
            ->with('status', "Turn #{$turn->sequence_no} skipped.");
    }

    /**
     * Block access to rotation pages when the active group has switched
     * the rotation feature off in its rules. Super admins browsing
     * globally (no active group) are not blocked.
     */
    protected function blockIfRotationsDisabled(): void
    {
        $group = $this->activeGroup();
        if (! $group) return;

        $enabled = (bool) $group->rule('rotation_enabled', true);
        abort_if(! $enabled, 404, 'Rotations are disabled for this group.');
    }
}
