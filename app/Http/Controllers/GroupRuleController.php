<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupRule;
use Illuminate\Http\Request;

class GroupRuleController extends Controller
{
    public function index(Group $group)
    {
        $this->authorize('view', $group);
        return view('group_rules.index', [
            'group' => $group,
            'rules' => $group->rules()->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'key'         => ['required', 'string', 'max:60'],
            'label'       => ['required', 'string', 'max:120'],
            'value'       => ['nullable', 'string'],
            'type'        => ['required', 'in:numeric,percent,days,string,boolean'],
            'description' => ['nullable', 'string'],
        ]);
        $group->rules()->updateOrCreate(['key' => $data['key']], $data);
        return redirect()->route('groups.rules.index', $group)->with('status', 'Rule saved.');
    }

    public function update(Request $request, Group $group, GroupRule $rule)
    {
        abort_if($rule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        $data = $request->validate([
            'label'       => ['required', 'string', 'max:120'],
            'value'       => ['nullable', 'string'],
            'type'        => ['required', 'in:numeric,percent,days,string,boolean'],
            'description' => ['nullable', 'string'],
        ]);
        $rule->update($data);
        return redirect()->route('groups.rules.index', $group)->with('status', 'Rule updated.');
    }

    public function destroy(Group $group, GroupRule $rule)
    {
        abort_if($rule->group_id !== $group->id, 404);
        $this->authorize('update', $group);
        if ($rule->is_system) return back()->with('error', 'System rules cannot be deleted.');
        $rule->delete();
        return back()->with('status', 'Rule removed.');
    }
}
