<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const GROUP_ADMIN_BLOCKED_ROLES = ['super_admin', 'group_admin'];

    public function index()
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();

        $users = User::with(['roles', 'member'])
            ->when($groupId, fn ($q) => $q->inGroup($groupId))
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();

        return view('users.create', [
            'roles'   => $this->allowedRoles(),
            'members' => $this->allowedMembers($groupId),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')],
            'email'     => ['required', 'email', Rule::unique('users', 'email')],
            'password'  => ['required', 'string', 'min:8'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'member_id' => ['nullable', 'exists:members,id'],
            'roles'     => ['array'],
            'roles.*'   => ['string', 'exists:roles,name'],
        ]);

        $roles = $this->filterRoles($data['roles'] ?? [], $groupId);
        unset($data['roles']);
        $this->ensureMemberInScope($data['member_id'] ?? null, $groupId);

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $user = User::create($data);
        $user->syncRoles($roles);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();
        $this->ensureUserInScope($user, $groupId);

        return view('users.edit', [
            'user'    => $user->load('roles', 'member'),
            'roles'   => $this->allowedRoles(),
            'members' => $this->allowedMembers($groupId),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();
        $this->ensureUserInScope($user, $groupId);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:8'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'member_id' => ['nullable', 'exists:members,id'],
            'roles'     => ['array'],
            'roles.*'   => ['string', 'exists:roles,name'],
        ]);

        $roles = $this->filterRoles($data['roles'] ?? [], $groupId);
        unset($data['roles']);
        $this->ensureMemberInScope($data['member_id'] ?? null, $groupId);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $user->update($data);
        $user->syncRoles($roles);

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user)
    {
        $this->authorizeUserManagement();
        $groupId = $this->activeGroupIdForUsers();
        $this->ensureUserInScope($user, $groupId);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return back()->with('status', 'User removed.');
    }

    private function authorizeUserManagement(): void
    {
        abort_unless(auth()->user()->canManageUsers(), 403);
    }

    private function isGroupScoped(): bool
    {
        $user = auth()->user();

        return $user->isGroupAdmin() && ! $user->isSuperAdmin();
    }

    private function activeGroupIdForUsers(): ?int
    {
        if (! $this->isGroupScoped()) {
            return null;
        }

        $groupId = (int) session('active_group_id');
        abort_unless($groupId, 403, 'Select a group first.');
        abort_unless(auth()->user()->canAccessGroup($groupId), 403);

        return $groupId;
    }

    private function allowedRoles()
    {
        $query = Role::orderBy('name');

        if ($this->isGroupScoped()) {
            $query->whereNotIn('name', self::GROUP_ADMIN_BLOCKED_ROLES);
        }

        return $query->get();
    }

    private function allowedMembers(?int $groupId)
    {
        $query = Member::orderBy('full_name');

        if ($groupId) {
            $query->whereHas('groups', fn ($q) => $q->where('groups.id', $groupId));
        }

        return $query->get();
    }

    private function filterRoles(array $roles, ?int $groupId): array
    {
        $roles = array_values(array_unique($roles));

        if ($groupId) {
            $roles = array_values(array_diff($roles, self::GROUP_ADMIN_BLOCKED_ROLES));
        }

        return $roles;
    }

    private function ensureMemberInScope(?int $memberId, ?int $groupId): void
    {
        if (! $memberId || ! $groupId) {
            return;
        }

        $inGroup = Member::whereKey($memberId)
            ->whereHas('groups', fn ($q) => $q->where('groups.id', $groupId))
            ->exists();

        abort_unless($inGroup, 403);
    }

    private function ensureUserInScope(User $user, ?int $groupId): void
    {
        if (! $groupId) {
            return;
        }

        abort_unless($user->belongsToGroup($groupId), 403);
    }
}
