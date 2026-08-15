<?php

namespace App\Policies;

use App\Models\Contribution;
use App\Models\User;

class ContributionPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Contribution $c): bool
    {
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary'])) return true;
        if ($u->member_id === $c->member_id) return true;
        // Members may view contributions for any group they belong to.
        if ($u->hasRole('member')) return $u->canAccessGroup($c->group_id);
        return false;
    }
    public function create(User $u): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']); }
    public function update(User $u, Contribution $c): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']); }
    public function delete(User $u, Contribution $c): bool { return $u->hasAnyRole(['super_admin', 'group_admin']); }
}
