<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $u): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']); }
    public function view(User $u, Group $g): bool { return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']); }
    public function create(User $u): bool { return $u->hasRole('super_admin'); }
    public function update(User $u, Group $g): bool { return $u->hasAnyRole(['super_admin', 'group_admin']); }
    public function delete(User $u, Group $g): bool { return $u->hasRole('super_admin'); }
}
