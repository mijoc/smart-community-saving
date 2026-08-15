<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Member $m): bool
    {
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary'])) return true;
        if ($u->member_id === $m->id) return true;

        // A regular member may view any other member they share a group with.
        if ($u->hasRole('member')) {
            $accessibleIds = $u->accessibleGroups()->pluck('id')->all();
            if (! $accessibleIds) return false;
            return $m->groups()->whereIn('groups.id', $accessibleIds)->exists();
        }

        return false;
    }
    public function create(User $u): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'secretary']); }
    public function update(User $u, Member $m): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'secretary']); }
    public function delete(User $u, Member $m): bool { return $u->hasRole('super_admin'); }
}
