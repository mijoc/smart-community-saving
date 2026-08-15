<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\District;
use App\Models\Group;
use App\Models\Member;
use App\Models\Sector;
use App\Models\User;
use App\Models\Village;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Member::class, 'member');
    }

    public function index(Request $request)
    {
        $accessible = $this->accessibleGroupOptions();
        $activeId = session('active_group_id');

        // Non-super-admins should only see their currently active group in
        // the group filter dropdown — never the other groups they happen to
        // be assigned to.
        if (! auth()->user()->isSuperAdmin()) {
            $accessible = $activeId
                ? $accessible->where('id', (int) $activeId)->values()
                : collect();
        }

        $q = Member::query()->with(['groups:id,name']);

        // Scope: members must be in the currently active group. Super admins
        // browsing without an active group see every member; non-super-admins
        // without an active group see nothing (the EnsureActiveGroup
        // middleware normally redirects them to the group switcher first).
        if ($activeId) {
            $q->whereHas('groups', fn ($g) => $g->where('groups.id', $activeId));
        } elseif (! auth()->user()->isSuperAdmin()) {
            $q->whereRaw('1 = 0');
        }

        if ($s = $request->string('search')->toString()) {
            $q->where(function ($w) use ($s) {
                $w->where('full_name', 'like', "%$s%")
                  ->orWhere('member_no', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('national_id', 'like', "%$s%");
            });
        }

        if ($status = $request->string('status')->toString()) $q->where('status', $status);
        if ($groupId = $request->integer('group_id')) {
            $q->whereHas('groups', fn ($g) => $g->where('groups.id', $groupId));
        }

        $members = $q->orderBy('full_name')->paginate(20)->withQueryString();

        return view('members.index', ['members' => $members, 'groups' => $accessible]);
    }

    public function create()
    {
        return view('members.create', [
            'groups'        => $this->accessibleGroupOptions(),
            'defaultGroup'  => session('active_group_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->applyLocationNames($data);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('members', 'public');
        }
        $data['member_no'] = $data['member_no'] ?? $this->nextMemberNumber();
        $data['joined_on'] = $data['joined_on'] ?? now()->toDateString();

        $member = Member::create($data);

        // Optionally create a login account for this member.
        $this->maybeCreateLoginFromRequest($request, $member);

        // Restrict the groups a non-super-admin can attach to.
        $allowedIds = $this->accessibleGroupOptions()->pluck('id')->all();
        $groupIds = collect($request->input('group_ids', []))
            ->filter(fn ($id) => auth()->user()->isSuperAdmin() || in_array((int) $id, $allowedIds))
            ->all();

        // Ensure the active group is added at least.
        if (empty($groupIds) && ($g = session('active_group_id'))) {
            $groupIds = [$g];
        }

        if ($groupIds) {
            $sync = collect($groupIds)->mapWithKeys(fn ($id) => [
                $id => ['position' => 'member', 'joined_at' => now()->toDateString(), 'is_active' => true],
            ])->all();
            $member->groups()->sync($sync);
        }

        foreach ($groupIds as $gid) {
            ActivityLogger::log(
                groupId: (int) $gid,
                type: 'member.created',
                description: "added new member {$member->full_name} ({$member->member_no})",
                subject: $member,
                icon: 'user-plus',
                color: 'green',
                data: ['member_no' => $member->member_no, 'phone' => $member->phone],
            );
        }

        return redirect()->route('members.show', $member)->with('status', 'Member created.');
    }

    /**
     * Printable ID cards.
     *
     *  - When `$member` is provided, renders a single card for that member.
     *  - Otherwise, renders cards for every member matching the current
     *    filters (group, status, search) — same scoping rules as `index()`.
     *
     * The view itself is print-optimised (A4, 2 cards per row).
     */
    public function cards(Request $request, ?Member $member = null)
    {
        $activeId    = session('active_group_id');
        $activeGroup = $activeId ? Group::find($activeId) : null;

        // Single-member mode -------------------------------------------------
        if ($member && $member->exists) {
            $this->authorize('view', $member);
            if ($activeId && ! $member->groups()->where('groups.id', $activeId)->exists()) {
                abort(404, 'Member not found in the current group.');
            }
            $member->load(['groups' => fn ($q) => $activeId ? $q->where('groups.id', $activeId) : $q]);

            return view('members.cards', [
                'members'     => collect([$member]),
                'activeGroup' => $activeGroup,
                'single'      => true,
            ]);
        }

        // Bulk mode — mirror index() scoping ---------------------------------
        $this->authorize('viewAny', Member::class);

        $q = Member::query()->with(['groups:id,name,code']);

        if ($activeId) {
            $q->whereHas('groups', fn ($g) => $g->where('groups.id', $activeId));
        } elseif (! auth()->user()->isSuperAdmin()) {
            $q->whereRaw('1 = 0');
        }

        if ($s = $request->string('search')->toString()) {
            $q->where(function ($w) use ($s) {
                $w->where('full_name', 'like', "%$s%")
                  ->orWhere('member_no', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('national_id', 'like', "%$s%");
            });
        }
        if ($status = $request->string('status')->toString()) $q->where('status', $status);
        if ($groupId = $request->integer('group_id')) {
            $q->whereHas('groups', fn ($g) => $g->where('groups.id', $groupId));
        }

        return view('members.cards', [
            'members'     => $q->orderBy('full_name')->limit(500)->get(),
            'activeGroup' => $activeGroup,
            'single'      => false,
        ]);
    }

    public function show(Member $member)
    {
        $activeId = session('active_group_id');

        // If the viewer is scoped to a single group, the member must belong
        // to it — otherwise we treat it as not-found for this context.
        if ($activeId && ! $member->groups()->where('groups.id', $activeId)->exists()) {
            abort(404, 'Member not found in the current group.');
        }

        // Eager-load only the data tied to the active group (or everything
        // when a super-admin is browsing globally without an active group).
        $member->load([
            'groups' => fn ($q)        => $activeId ? $q->where('groups.id', $activeId) : $q,
            'contributions'            => fn ($q) => $activeId ? $q->where('group_id', $activeId) : $q,
            'contributions.group',
        ]);

        return view('members.show', [
            'member'      => $member,
            'activeGroup' => $activeId,
        ]);
    }

    public function edit(Member $member)
    {
        $activeId = session('active_group_id');

        if ($activeId && ! $member->groups()->where('groups.id', $activeId)->exists()) {
            abort(404, 'Member not found in the current group.');
        }

        // Only expose the active group in the edit form (so a group_admin
        // cannot see/touch this member's other group memberships).
        $groups = $activeId
            ? $this->accessibleGroupOptions()->where('id', $activeId)->values()
            : $this->accessibleGroupOptions();

        return view('members.edit', [
            'member' => $member->load(['groups' => fn ($q) => $activeId ? $q->where('groups.id', $activeId) : $q]),
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $data = $this->validateData($request, $member->id);
        $data = $this->applyLocationNames($data);
        if ($request->hasFile('photo')) {
            if ($member->photo_path) Storage::disk('public')->delete($member->photo_path);
            $data['photo_path'] = $request->file('photo')->store('members', 'public');
        }
        $member->update($data);
        return redirect()->route('members.show', $member)->with('status', 'Member updated.');
    }

    public function destroy(Member $member)
    {
        $name = $member->full_name;
        $no   = $member->member_no;
        $gids = $member->groups()->pluck('groups.id')->all();

        $member->delete();

        foreach ($gids as $gid) {
            ActivityLogger::log(
                groupId: (int) $gid,
                type: 'member.removed',
                description: "removed member {$name} ({$no})",
                icon: 'user-minus',
                color: 'red',
            );
        }

        return redirect()->route('members.index')->with('status', 'Member archived.');
    }

    /**
     * Group admin / super admin creates a login account for an existing member.
     */
    public function createLogin(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $this->ensureMemberInActiveGroup($member);

        if (User::where('member_id', $member->id)->exists()) {
            return back()->with('status', 'Member already has a login account.');
        }

        $data = $request->validate([
            'login_username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')],
            'login_email'    => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'login_password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = ($data['login_password'] ?? null) ?: Str::random(12);
        $user = User::create([
            'name'      => $member->full_name,
            'username'  => $data['login_username'],
            'email'     => $data['login_email'],
            'password'  => Hash::make($password),
            'phone'     => $member->phone,
            'is_active' => true,
            'member_id' => $member->id,
        ]);
        $user->syncRoles(['member']);

        foreach ($member->groups()->pluck('groups.id') as $gid) {
            ActivityLogger::log(
                groupId: (int) $gid,
                type: 'member.login.created',
                description: "issued a login for {$member->full_name}",
                subject: $member,
                icon: 'key',
                color: 'azure',
                data: ['username' => $user->username, 'email' => $user->email],
            );
        }

        return back()->with('status', "Login created for {$member->full_name}. Username: {$user->username} / temporary password: {$password}");
    }

    /**
     * Group admin / super admin resets a member's login password.
     */
    public function resetPassword(Request $request, Member $member)
    {
        $this->authorize('update', $member);
        $this->ensureMemberInActiveGroup($member);

        $user = User::where('member_id', $member->id)->first();
        if (! $user) abort(404, 'This member does not have a login account.');

        $data = $request->validate([
            'new_password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = ($data['new_password'] ?? null) ?: Str::random(12);
        $user->update(['password' => Hash::make($password)]);

        foreach ($member->groups()->pluck('groups.id') as $gid) {
            ActivityLogger::log(
                groupId: (int) $gid,
                type: 'member.password.reset',
                description: "reset the password for {$member->full_name}",
                subject: $member,
                icon: 'lock-square',
                color: 'orange',
            );
        }

        return back()->with('status', "Password reset for {$member->full_name}. New password: {$password}");
    }

    /** Internal: create login during member creation if the form requested it. */
    protected function maybeCreateLoginFromRequest(Request $request, Member $member): void
    {
        if (! $request->boolean('create_login')) return;

        $request->validate([
            'login_username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')],
            'login_email'    => ['required', 'email', 'max:160', Rule::unique('users', 'email')],
            'login_password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = $request->input('login_password') ?: Str::random(12); // input() already returns null when missing
        $user = User::create([
            'name'      => $member->full_name,
            'username'  => $request->input('login_username'),
            'email'     => $request->input('login_email'),
            'password'  => Hash::make($password),
            'phone'     => $member->phone,
            'is_active' => true,
            'member_id' => $member->id,
        ]);
        $user->syncRoles(['member']);

        // Surface the credentials to the admin (one-time).
        session()->flash('credentials', "Login created — Username: {$user->username} · Password: {$password}");
    }

    protected function ensureMemberInActiveGroup(Member $member): void
    {
        $activeId = session('active_group_id');
        if ($activeId && ! $member->groups()->where('groups.id', $activeId)->exists()) {
            abort(404, 'Member not found in the current group.');
        }
    }

    protected function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'member_no'         => ['nullable', 'string', 'max:50'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'gender'            => ['required', 'in:male,female,other'],
            'date_of_birth'     => ['nullable', 'date'],
            'national_id'       => ['nullable', 'string', 'max:50'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:255'],
            'photo'             => ['nullable', 'image', 'max:4096'],
            'province_code'     => ['nullable', 'string', 'exists:provinces,code'],
            'district_code'     => ['nullable', 'string', 'exists:districts,code'],
            'sector_code'       => ['nullable', 'string', 'exists:sectors,code'],
            'cell_code'         => ['nullable', 'string', 'exists:cells,code'],
            'village_code'      => ['nullable', 'string', 'exists:villages,code'],
            'address'           => ['nullable', 'string', 'max:255'],
            'next_of_kin_name'  => ['nullable', 'string', 'max:160'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],
            'occupation'        => ['nullable', 'string', 'max:120'],
            'status'            => ['required', 'in:active,inactive,suspended,exited'],
            'joined_on'         => ['nullable', 'date'],
            'notes'             => ['nullable', 'string'],
        ]);
    }

    protected function nextMemberNumber(): string
    {
        $n = (int) Member::max('id') + 1;
        return 'M-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Mirror chosen district / village codes back into the legacy text columns
     * so list / show pages keep displaying readable place names.
     */
    protected function applyLocationNames(array $data): array
    {
        if (! empty($data['district_code'])) {
            $data['district'] = optional(District::find($data['district_code']))->name ?? ($data['district'] ?? null);
        }
        if (! empty($data['village_code'])) {
            $data['village'] = optional(Village::find($data['village_code']))->name ?? ($data['village'] ?? null);
        }
        return $data;
    }
}
