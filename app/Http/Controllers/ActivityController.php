<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $activeId = session('active_group_id');

        // Non-super-admins only ever see the activity of the group they
        // are currently switched into. Super admins see every group, or
        // can pin themselves to one via the active-group switcher.
        $scopeIds = $u->isSuperAdmin()
            ? $u->accessibleGroups()->pluck('id')->all()
            : ($activeId ? [(int) $activeId] : []);

        $groupOptions = $u->isSuperAdmin()
            ? $u->accessibleGroups()
            : ($activeId
                ? $u->accessibleGroups()->where('id', (int) $activeId)->values()
                : collect());

        $q = Activity::query()
            ->with(['actor:id,name,avatar_path', 'group:id,name,code']);

        if (! empty($scopeIds)) {
            $q->whereIn('group_id', $scopeIds);
        } else {
            $q->whereRaw('1 = 0');
        }

        if ($g = $request->integer('group_id')) {
            if (in_array($g, $scopeIds, true)) $q->where('group_id', $g);
        }
        if ($t = $request->string('type')->toString()) $q->where('type', $t);

        $activities = $q->orderByDesc('created_at')->paginate(30)->withQueryString();

        $u->forceFill(['activities_last_seen_at' => now()])->save();

        return view('activity.index', [
            'activities' => $activities,
            'groups'     => $groupOptions,
        ]);
    }

    public function markAllRead(Request $request)
    {
        auth()->user()->forceFill(['activities_last_seen_at' => now()])->save();
        return back()->with('status', 'Notifications marked as read.');
    }
}
