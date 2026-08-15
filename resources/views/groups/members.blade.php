@extends('layouts.app')
@section('title','Group membership')
@section('content')

<x-page_header :title="$group->name.' · Members & staff'" pretitle="Groups"></x-page_header>

@php $existingMembers = $group->members->keyBy('id'); @endphp
@php $existingStaff   = $group->staffUsers->keyBy('id'); @endphp

<div class="row row-cards mt-3">

    {{-- ─────────── Members ─────────── --}}
    <div class="col-12">
        <form method="POST" action="{{ route('groups.members.sync', $group) }}" class="card">@csrf
            <div class="card-header">
                <h3 class="card-title">Members</h3>
                <div class="card-subtitle text-muted">
                    @if(auth()->user()->isSuperAdmin())
                        A member can belong to multiple groups. Tick the members to include in <strong>{{ $group->name }}</strong>.
                    @else
                        These are the members currently in <strong>{{ $group->name }}</strong>. Untick a row to remove that member from this group, or edit their position, shares and active status. To add a brand-new person, use the <a href="{{ route('members.index') }}">Members</a> page.
                    @endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr>
                        <th style="width:40px"></th>
                        <th>Member</th>
                        <th style="width:160px">Position</th>
                        <th style="width:120px">Shares</th>
                        <th style="width:100px">Active</th>
                    </tr></thead>
                    <tbody>
                    @foreach($allMembers as $i => $m)
                    @php $p = $existingMembers->get($m->id)?->pivot; @endphp
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="members[{{ $i }}][_enabled]" value="1" @checked($p)></td>
                        <td>
                            <input type="hidden" name="members[{{ $i }}][member_id]" value="{{ $m->id }}">
                            <strong>{{ $m->full_name }}</strong>
                            <span class="text-muted small">· {{ $m->member_no }}</span>
                        </td>
                        <td>
                            <select name="members[{{ $i }}][position]" class="form-select form-select-sm">
                                @foreach(config('vsla.positions') as $k => $v)
                                    <option value="{{ $k }}" @selected(($p->position ?? 'member') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" name="members[{{ $i }}][share_count]" value="{{ $p->share_count ?? 0 }}" class="form-control form-control-sm" min="0"></td>
                        <td><input type="checkbox" name="members[{{ $i }}][is_active]" value="1" class="form-check-input" @checked($p->is_active ?? true)></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('groups.show', $group) }}" class="btn">Cancel</a>
                <button class="btn btn-primary">Save members</button>
            </div>
        </form>
    </div>

    {{-- ─────────── Staff (group_admin / treasurer / secretary) ─────────── --}}
    @if(auth()->user()->isSuperAdmin() || auth()->user()->groups->contains('id', $group->id))
    <div class="col-12">
        <form method="POST" action="{{ route('groups.staff.sync', $group) }}" class="card">@csrf
            <div class="card-header">
                <h3 class="card-title">Group staff</h3>
                <div class="card-subtitle text-muted">Assign which user accounts can manage this group.</div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr>
                        <th style="width:40px"></th>
                        <th>User</th>
                        <th>Roles</th>
                        <th style="width:200px">Role in this group</th>
                    </tr></thead>
                    <tbody>
                    @foreach($staffUsers as $i => $u)
                    @php $sp = $existingStaff->get($u->id)?->pivot; @endphp
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="staff[{{ $i }}][_enabled]" value="1" @checked($sp)></td>
                        <td>
                            <input type="hidden" name="staff[{{ $i }}][user_id]" value="{{ $u->id }}">
                            <strong>{{ $u->name }}</strong>
                            <div class="text-muted small">{{ $u->email }}</div>
                        </td>
                        <td>
                            @foreach($u->roles as $r)<span class="badge bg-blue-lt me-1">{{ str_replace('_',' ',$r->name) }}</span>@endforeach
                        </td>
                        <td>
                            @php
                                $assignableRoles = auth()->user()->isSuperAdmin()
                                    ? ['group_admin','treasurer','secretary']
                                    : ['treasurer','secretary'];
                            @endphp
                            <select name="staff[{{ $i }}][role_in_group]" class="form-select form-select-sm"
                                    @disabled(($sp->role_in_group ?? null) === 'group_admin' && ! auth()->user()->isSuperAdmin())>
                                <option value="">—</option>
                                @foreach($assignableRoles as $r)
                                    <option value="{{ $r }}" @selected(($sp->role_in_group ?? null) === $r)>{{ str_replace('_',' ',$r) }}</option>
                                @endforeach
                                @if(($sp->role_in_group ?? null) === 'group_admin' && ! auth()->user()->isSuperAdmin())
                                    {{-- show the existing group_admin (read-only) --}}
                                    <option value="group_admin" selected>group admin (set by super admin)</option>
                                @endif
                            </select>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary">Save staff assignments</button>
            </div>
        </form>
    </div>
    @endif
</div>

@push('scripts')
<script>
// Drop rows whose checkbox is unticked before submit
document.querySelectorAll('form.card').forEach(form => {
    form.addEventListener('submit', function() {
        this.querySelectorAll('tbody tr').forEach((tr) => {
            const cb = tr.querySelector('input[type=checkbox][name$="[_enabled]"]');
            if (cb && !cb.checked) tr.querySelectorAll('input,select').forEach((el) => el.disabled = true);
        });
    });
});
</script>
@endpush
@endsection
