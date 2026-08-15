<?php

namespace App\Policies;

use App\Models\CashbookEntry;
use App\Models\User;

class CashbookEntryPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary', 'member']);
    }

    public function view(User $u, CashbookEntry $entry): bool
    {
        if ($entry->isRegularization() && ! $this->isAdmin($u)) {
            return false;
        }

        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary'])) {
            return $u->canAccessGroup($entry->group_id);
        }
        if ($u->hasRole('member')) {
            return $u->canAccessGroup($entry->group_id);
        }
        return false;
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']);
    }

    public function update(User $u, CashbookEntry $entry): bool
    {
        if ($entry->isRegularization() && ! $this->isAdmin($u)) {
            return false;
        }

        return $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])
            && $u->canAccessGroup($entry->group_id);
    }

    public function delete(User $u, CashbookEntry $entry): bool
    {
        if ($entry->isRegularization() && ! $this->isAdmin($u)) {
            return false;
        }

        return $u->hasAnyRole(['super_admin', 'group_admin'])
            && $u->canAccessGroup($entry->group_id);
    }

    /**
     * Only administrators may create private regularization entries.
     */
    public function regularize(User $u): bool
    {
        return $this->isAdmin($u);
    }

    private function isAdmin(User $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'group_admin']);
    }
}
