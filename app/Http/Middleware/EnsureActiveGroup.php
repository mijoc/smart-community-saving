<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees that operational pages are scoped to an "active group".
 *
 * - super_admin can browse globally (no active_group required); operational
 *   pages will show data across every group unless they pick one.
 * - Every other role MUST work inside a group context. If none is set, we
 *   either auto-select their single accessible group or send them to the
 *   group selector.
 */
class EnsureActiveGroup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return $next($request);

        // Super admin can always proceed without an active group.
        if ($user->isSuperAdmin()) return $next($request);

        $activeId = $request->session()->get('active_group_id');
        $accessible = $user->accessibleGroups();

        if ($accessible->isEmpty()) {
            abort(403, 'You are not assigned to any group. Please contact a super admin.');
        }

        // Validate the active group is still accessible
        if ($activeId && ! $accessible->contains('id', $activeId)) {
            $request->session()->forget('active_group_id');
            $activeId = null;
        }

        if (! $activeId) {
            if ($accessible->count() === 1) {
                $request->session()->put('active_group_id', $accessible->first()->id);
            } else {
                return redirect()->route('groups.select');
            }
        }

        return $next($request);
    }
}
