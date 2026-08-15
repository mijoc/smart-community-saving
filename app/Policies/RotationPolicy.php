<?php

namespace App\Policies;

use App\Models\Rotation;
use App\Models\User;

class RotationPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Rotation $r): bool
    {
        return $u->canAccessGroup((int) $r->group_id);
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }

    public function update(User $u, Rotation $r): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])
            && $u->canAccessGroup((int) $r->group_id);
    }

    public function delete(User $u, Rotation $r): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin'])
            && $u->canAccessGroup((int) $r->group_id);
    }

    /** Execute (or skip) a turn — same gate as update. */
    public function execute(User $u, Rotation $r): bool
    {
        return $this->update($u, $r);
    }
}
