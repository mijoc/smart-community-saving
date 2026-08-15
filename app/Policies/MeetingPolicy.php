<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, Meeting $m): bool
    {
        return $u->canAccessGroup((int) $m->group_id);
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'secretary', 'treasurer']);
    }

    public function update(User $u, Meeting $m): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'secretary', 'treasurer'])
            && $u->canAccessGroup((int) $m->group_id);
    }

    public function delete(User $u, Meeting $m): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin'])
            && $u->canAccessGroup((int) $m->group_id);
    }

    /** Mark fines as paid — limited to admin/treasurer like cashbook deposits. */
    public function recordPayment(User $u, Meeting $m): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])
            && $u->canAccessGroup((int) $m->group_id);
    }
}
