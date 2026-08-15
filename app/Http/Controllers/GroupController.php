<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\District;
use App\Models\Group;
use App\Models\Member;
use App\Models\Sector;
use App\Models\User;
use App\Models\Village;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Group::class, 'group');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $q = Group::query()
            ->withCount('activeMembers as members_count')
            ->with(['staffUsers' => fn ($s) => $s->wherePivot('role_in_group', 'group_admin')]);

        if (! $user->isSuperAdmin()) {
            // Non-super-admins only see the group they are currently switched
            // into. Other assigned groups stay hidden until they switch via
            // the topbar group switcher.
            $activeId = session('active_group_id');
            $q->where('id', $activeId ?: 0);
        }

        if ($s = $request->string('search')->toString()) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"));
        }
        $groups = $q->orderBy('name')->paginate(15)->withQueryString();
        return view('groups.index', compact('groups'));
    }

    public function create()
    {
        return view('groups.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->applyLocationNames($data);
        $data['code']       = $data['code']       ?? $this->nextGroupCode();
        $data['created_by'] = auth()->id();
        $group = Group::create($data);
        $this->seedDefaultRules($group);

        return redirect()->route('groups.show', $group)->with('status', 'Group created.');
    }

    public function show(Group $group)
    {
        $this->ensureGroupAccess($group);
        $group->load(['rules', 'schedules', 'activeMembers', 'staffUsers']);

        $stats = [
            'members'       => $group->activeMembers->count(),
            'savings_total' => (float) $group->payments()
                ->whereHas('contribution', fn ($q) => $q->where('type', 'savings'))->sum('amount'),
            'arrears_total' => (float) $group->arrears()->where('status', 'open')->sum('outstanding_amount'),
            'collected_30d' => (float) $group->payments()->where('paid_on', '>=', now()->subDays(30))->sum('amount'),
        ];

        return view('groups.show', compact('group', 'stats'));
    }

    public function edit(Group $group)
    {
        $this->ensureGroupAccess($group);
        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $this->ensureGroupAccess($group);
        $data = $this->validateData($request, $group->id);
        $data = $this->applyLocationNames($data);
        $group->update($data);
        return redirect()->route('groups.show', $group)->with('status', 'Group updated.');
    }

    public function destroy(Group $group)
    {
        $group->delete();
        return redirect()->route('groups.index')->with('status', 'Group archived.');
    }

    public function members(Group $group)
    {
        $this->authorize('view', $group);
        $this->ensureGroupAccess($group);

        $user = auth()->user();
        $group->load(['members', 'staffUsers']);

        // Build the candidate-members list.
        //
        // - Super admins see every active member (they manage the whole org
        //   and may onboard members from any group into this one).
        // - Everyone else (group_admin / treasurer / secretary) only sees
        //   the members that are *already in this group*. They edit
        //   positions / shares / active flag here. To onboard a brand-new
        //   member they use the Members page (which attaches the new member
        //   to the active group), keeping this screen focused on the people
        //   who actually belong to this group.
        if ($user->isSuperAdmin()) {
            $allMembers = Member::where('status', 'active')->orderBy('full_name')->get();
        } else {
            $allMembers = $group->members()
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get();
        }

        // Staff list — same rule: a non-super-admin only sees staff that are
        // already attached to *this* group. Super admins see every staff
        // user so they can attach new staff from anywhere.
        if ($user->isSuperAdmin()) {
            $staffUsers = User::with('roles')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['group_admin', 'treasurer', 'secretary']))
                ->orderBy('name')
                ->get();
        } else {
            $staffUsers = $group->staffUsers()
                ->with('roles')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['group_admin', 'treasurer', 'secretary']))
                ->orderBy('name')
                ->get();
        }

        return view('groups.members', compact('group', 'allMembers', 'staffUsers'));
    }

    public function syncMembers(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $this->ensureGroupAccess($group);

        $payload = $request->validate([
            'members'                => ['array'],
            'members.*.member_id'    => ['required', 'exists:members,id'],
            'members.*.position'     => ['required', 'in:chairperson,secretary,treasurer,member'],
            'members.*.share_count'  => ['nullable', 'integer', 'min:0'],
            'members.*.is_active'    => ['nullable', 'boolean'],
        ]);

        $sync = [];
        foreach ($payload['members'] ?? [] as $row) {
            $sync[$row['member_id']] = [
                'position'    => $row['position'],
                'share_count' => $row['share_count'] ?? 0,
                'is_active'   => (bool) ($row['is_active'] ?? true),
                'joined_at'   => now()->toDateString(),
            ];
        }
        $group->members()->sync($sync);
        return redirect()->route('groups.members', $group)->with('status', 'Group membership updated.');
    }

    /**
     * Assign staff users (group_admin / treasurer / secretary) to this group.
     * Only super_admin or an existing group_admin of the group may use this.
     */
    public function syncStaff(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $this->ensureGroupAccess($group);

        $payload = $request->validate([
            'staff'                  => ['array'],
            'staff.*.user_id'        => ['required', 'exists:users,id'],
            'staff.*.role_in_group'  => ['nullable', 'in:group_admin,treasurer,secretary'],
        ]);

        $isSuper = auth()->user()->isSuperAdmin();

        // Only super_admin may assign / revoke the group_admin role.
        // Non-super-admins keep the existing group_admin assignments untouched
        // and can only manage treasurer / secretary slots.
        $existingAdmins = $isSuper
            ? collect()
            : $group->staffUsers()->wherePivot('role_in_group', 'group_admin')->pluck('users.id');

        $sync = [];
        foreach ($payload['staff'] ?? [] as $row) {
            $role = $row['role_in_group'] ?? null;
            if (! $isSuper && $role === 'group_admin') {
                // Silently drop attempts by non-super-admins to grant group_admin.
                $role = null;
            }
            $sync[(int) $row['user_id']] = ['role_in_group' => $role];
        }

        // Re-add any group_admin rows the non-super-admin would have dropped.
        if (! $isSuper) {
            foreach ($existingAdmins as $uid) {
                $sync[$uid] = ['role_in_group' => 'group_admin'];
            }
        }

        $group->staffUsers()->sync($sync);
        return redirect()->route('groups.members', $group)->with('status', 'Staff assignments updated.');
    }

    protected function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code'             => ['nullable', 'string', 'max:30'],
            'name'             => ['required', 'string', 'max:160'],
            'description'      => ['nullable', 'string'],
            'province_code'    => ['nullable', 'string', 'exists:provinces,code'],
            'district_code'    => ['nullable', 'string', 'exists:districts,code'],
            'sector_code'      => ['nullable', 'string', 'exists:sectors,code'],
            'cell_code'        => ['nullable', 'string', 'exists:cells,code'],
            'village_code'     => ['nullable', 'string', 'exists:villages,code'],
            'region'           => ['nullable', 'string', 'max:120'],
            'country'          => ['nullable', 'string', 'max:120'],
            'currency'         => ['required', 'string', 'max:8'],
            'formed_on'        => ['nullable', 'date'],
            'cycle_starts_on'  => ['nullable', 'date'],
            'cycle_ends_on'    => ['nullable', 'date'],
            'status'           => ['required', 'in:active,paused,closed'],
        ]);
    }

    protected function nextGroupCode(): string
    {
        $n = (int) Group::max('id') + 1;
        return 'G-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mirror chosen district / sector / cell / village codes into the
     * legacy text columns so the existing list / show pages display
     * readable place names.
     */
    protected function applyLocationNames(array $data): array
    {
        if (! empty($data['district_code'])) {
            $data['district'] = optional(District::find($data['district_code']))->name;
        }
        if (! empty($data['sector_code'])) {
            $data['sector'] = optional(Sector::find($data['sector_code']))->name;
        }
        if (! empty($data['cell_code'])) {
            $data['cell'] = optional(Cell::find($data['cell_code']))->name;
        }
        if (! empty($data['village_code'])) {
            $data['village'] = optional(Village::find($data['village_code']))->name;
        }
        return $data;
    }

    protected function ensureGroupAccess(Group $group): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) return;

        // Non-super-admins may only inspect the group they are currently
        // switched into. Other assigned groups are off-limits until the
        // user uses the topbar group switcher.
        $activeId = (int) session('active_group_id');
        if (! $activeId || $group->id !== $activeId) {
            abort(403, 'You do not have access to this group.');
        }
    }

    protected function seedDefaultRules(Group $group): void
    {
        $defaults = [
            ['key' => 'share_value',     'label' => 'Share value',          'value' => 1000, 'type' => 'numeric', 'is_system' => true],
            ['key' => 'max_shares',      'label' => 'Max shares per meeting','value' => 5,    'type' => 'numeric', 'is_system' => true],
            ['key' => 'social_fund_pct', 'label' => 'Social fund %',         'value' => 10,   'type' => 'percent', 'is_system' => true],
            ['key' => 'late_fee_pct',       'label' => 'Late fee % (contributions)', 'value' => config('vsla.late_fee_pct'), 'type' => 'percent', 'is_system' => true],
            ['key' => 'grace_days',         'label' => 'Grace days',                 'value' => config('vsla.grace_days'),   'type' => 'days',    'is_system' => true],
            ['key' => 'loan_late_fee_pct',  'label' => 'Loan late fee % / period',   'value' => '0', 'type' => 'percent', 'is_system' => true,
             'description' => 'Late penalty % charged per month on overdue flat loans (past due_on with balance remaining). Set to 0 to disable.'],
            ['key' => 'penalty_on_penalty', 'label' => 'Penalty on penalty (compound)', 'value' => '0', 'type' => 'boolean', 'is_system' => true,
             'description' => 'Compound "interest on interest": each period\'s fee is charged on (original amount + all prior unpaid fees). E.g. 50,000 at 5%: Apr +2,500 → May +2,625 → Jun +2,756.25. Cap = E×((1+r)^N−1). Applies to both contributions and loans.'],
            ['key' => 'meeting_day',     'label' => 'Meeting day',           'value' => 'Saturday', 'type' => 'string', 'is_system' => true],
            ['key' => 'loan_max_multiplier','label' => 'Loan max × savings', 'value' => 3,    'type' => 'numeric', 'is_system' => true],
            ['key' => 'loan_interest_pct','label' => 'Loan interest % / mo', 'value' => 5,    'type' => 'percent', 'is_system' => true],
        ];
        foreach ($defaults as $r) $group->rules()->create($r);
    }
}
