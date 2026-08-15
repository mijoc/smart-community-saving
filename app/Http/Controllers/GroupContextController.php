<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GroupContextController extends Controller
{
    /**
     * "Pick the group you want to work in" page (shown when the user has more
     * than one accessible group and none is active yet, or when they hit the
     * /groups/select link from the topbar).
     */
    public function select(Request $request)
    {
        $accessible = $request->user()->accessibleGroups();

        // Auto-pick if there is only one
        if ($accessible->count() === 1 && ! $request->user()->isSuperAdmin()) {
            $request->session()->put('active_group_id', $accessible->first()->id);
            return redirect()->intended(route('dashboard'));
        }

        return view('groups.select', [
            'groups'  => $accessible,
            'current' => $request->session()->get('active_group_id'),
        ]);
    }

    /**
     * Switch the active group (from the topbar dropdown or the selector page).
     * Empty group_id == "all groups" (super_admin only).
     */
    public function switch(Request $request)
    {
        $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

        $user    = $request->user();
        $groupId = $request->integer('group_id') ?: null;

        if ($groupId === null) {
            if (! $user->isSuperAdmin()) {
                abort(403, 'Only super admins can browse without an active group.');
            }
            $request->session()->forget('active_group_id');
        } else {
            if (! $user->isAssignedToGroup($groupId)) {
                abort(403, 'You do not have access to that group.');
            }
            $request->session()->put('active_group_id', $groupId);
        }

        return redirect()->to($request->input('redirect_to', route('dashboard')));
    }
}
