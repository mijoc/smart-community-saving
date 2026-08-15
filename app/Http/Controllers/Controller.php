<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Constrain a query by group context:
     *  - super_admin browsing globally → no constraint
     *  - active group set              → exact match
     *  - non-super-admin without active → empty result set
     *
     * Non-super-admins must always work inside the active group they are
     * currently switched into. The only way they can see another group's
     * data is to switch into it via the group switcher.
     */
    protected function scopeToActiveGroup(Builder $q, string $column = 'group_id'): Builder
    {
        $user      = auth()->user();
        $activeId  = session('active_group_id');

        if ($activeId) {
            return $q->where($column, $activeId);
        }

        if ($user && ! $user->isSuperAdmin()) {
            // No active group + not a super admin → show nothing.
            return $q->whereRaw('1 = 0');
        }

        return $q;
    }

    /**
     * IDs the current user is allowed to query for the *current view*.
     *  - super_admin → null   (no constraint, sees every group)
     *  - everyone else → [active_group_id] (exactly the group they are
     *    currently switched into, never any other assigned group)
     */
    protected function currentScopeGroupIds(): ?array
    {
        $user = auth()->user();
        if ($user && $user->isSuperAdmin()) return null;

        $activeId = session('active_group_id');
        return $activeId ? [(int) $activeId] : [];
    }

    /**
     * Drop-down options the user is allowed to see in the *current view*.
     *  - super_admin → every accessible group
     *  - everyone else → just the currently active group
     *    (the only group they're working inside right now)
     */
    protected function accessibleGroupOptions(): Collection
    {
        $user = auth()->user();
        $all  = $user->accessibleGroups();
        if ($user->isSuperAdmin()) return $all;

        $activeId = (int) session('active_group_id');
        return $activeId
            ? $all->where('id', $activeId)->values()
            : collect();
    }

    protected function activeGroup(): ?Group
    {
        $id = session('active_group_id');
        return $id ? Group::find($id) : null;
    }
}
