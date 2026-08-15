<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Services\PassbookService;
use Illuminate\Http\Request;

class PassbookController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Member::class);

        $u = auth()->user();
        $activeId = session('active_group_id');

        $q = Member::query();

        // Constrain to the active group. Non-super-admins without an active
        // group see nothing (the middleware sends them to the switcher).
        if ($activeId) {
            $q->whereHas('groups', fn ($g) => $g->where('groups.id', $activeId));
        } elseif (! $u->isSuperAdmin()) {
            $q->whereRaw('1 = 0');
        }

        $members = $q->orderBy('full_name')->paginate(20);
        return view('passbooks.index', compact('members'));
    }

    public function show(Request $request, Member $member, PassbookService $svc)
    {
        $this->authorize('view', $member);

        $u = auth()->user();
        // Members may now view any group-mate's passbook (the policy already
        // enforces the shared-group rule).

        // Active group context drives which group's passbook we show.
        // Non-super-admins are pinned to the group they have currently
        // switched into — even if they share other groups with this member,
        // those are hidden until they switch.
        $activeId = (int) session('active_group_id');
        if ($u->isSuperAdmin()) {
            $sharedGroups = $member->groups->values();
        } else {
            $sharedGroups = $activeId
                ? $member->groups->where('id', $activeId)->values()
                : collect();
        }

        $groupId = $u->isSuperAdmin()
            ? ($request->integer('group_id') ?: $activeId)
            : ($activeId ?: null);

        if ($groupId && ! $sharedGroups->contains('id', $groupId) && ! $u->isSuperAdmin()) {
            $groupId = null;
        }
        if (! $groupId && $sharedGroups->isNotEmpty()) {
            $groupId = $sharedGroups->first()->id;
        }

        $entries = collect();
        $balance = 0;
        if ($groupId) {
            $entries = PassbookEntry::where('member_id', $member->id)
                ->where('group_id', $groupId)
                ->orderBy('entry_date')->orderBy('id')->get();
            $balance = $svc->balance($groupId, $member->id);
        }

        return view('passbooks.show', [
            'member'       => $member,
            'groups'       => $sharedGroups,
            'currentGroup' => $groupId ? Group::find($groupId) : null,
            'entries'      => $entries,
            'balance'      => $balance,
        ]);
    }
}
