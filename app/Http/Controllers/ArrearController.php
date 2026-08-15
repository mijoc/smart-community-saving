<?php

namespace App\Http\Controllers;

use App\Models\Arrear;
use App\Services\ArrearsService;
use Illuminate\Http\Request;

class ArrearController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', \App\Models\Contribution::class);

        $q = Arrear::query()->with([
            'group:id,name', 'member:id,full_name,member_no',
            'contribution:id,type,due_on,period_start',
        ]);
        $this->scopeToActiveGroup($q);

        // Members default to their own arrears, but can opt into the group-wide
        // view via `?view=group`.
        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }

        if ($g = $request->integer('group_id')) $q->where('group_id', $g);
        if ($s = $request->string('status')->toString()) $q->where('status', $s);

        $arrears = $q->orderByDesc('outstanding_amount')->paginate(25)->withQueryString();
        return view('arrears.index', [
            'arrears' => $arrears,
            'groups'  => $this->accessibleGroupOptions(),
        ]);
    }

    public function runEngine(Request $request, ArrearsService $svc)
    {
        $this->authorize('viewAny', \App\Models\Contribution::class);
        $groupId = $request->integer('group_id') ?: session('active_group_id') ?: null;
        if ($groupId && ! auth()->user()->canAccessGroup($groupId)) abort(403);
        $r = $svc->run($groupId);
        $l = $svc->runLoanLateFees($groupId);
        return back()->with('status',
            "Contributions: evaluated {$r['evaluated']}, applied {$r['fees_applied']} fees. " .
            "Loans: evaluated {$l['evaluated']}, applied {$l['fees_applied']} fees."
        );
    }

    public function waive(Arrear $arrear)
    {
        $this->authorize('viewAny', \App\Models\Contribution::class);
        if (! auth()->user()->canAccessGroup($arrear->group_id)) abort(403);
        $arrear->update(['status' => 'waived', 'outstanding_amount' => 0]);
        $arrear->contribution->update(['status' => 'waived']);
        return back()->with('status', 'Arrear waived.');
    }
}
