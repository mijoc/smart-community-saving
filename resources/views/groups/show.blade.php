@extends('layouts.app')
@section('title', $group->name)
@section('content')

<x-page_header :title="$group->name" :pretitle="'Group · '.$group->code">
    <x-slot name="actions">
        <a href="{{ route('groups.members', $group) }}" class="btn"><i class="ti ti-users me-1"></i>Members</a>
        <a href="{{ route('groups.rules.index', $group) }}" class="btn"><i class="ti ti-settings me-1"></i>Rules</a>
        <a href="{{ route('groups.schedules.index', $group) }}" class="btn"><i class="ti ti-calendar me-1"></i>Schedules</a>
        @can('update', $group)
        <a href="{{ route('groups.edit', $group) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Edit</a>
        @endcan
    </x-slot>
</x-page_header>

<div class="row row-cards mt-3">
    @foreach([
        ['Members',    $stats['members'], 'azure',  'ti-users'],
        ['Savings',    number_format($stats['savings_total'], 0).' '.$group->currency, 'green',  'ti-pig-money'],
        ['Open arrears', number_format($stats['arrears_total'], 0).' '.$group->currency, 'red',    'ti-alert-triangle'],
        ['Last 30 days', number_format($stats['collected_30d'], 0).' '.$group->currency, 'primary','ti-cash'],
    ] as [$l,$v,$c,$i])
    <div class="col-sm-6 col-lg-3"><div class="card stat-card"><div class="card-body d-flex align-items-center">
        <div class="bg-{{ $c }}-lt rounded p-3 me-3"><i class="ti {{ $i }} fs-2 text-{{ $c }}"></i></div>
        <div><div class="text-muted small text-uppercase">{{ $l }}</div><div class="h2 mb-0">{{ $v }}</div></div>
    </div></div></div>
    @endforeach
</div>

<div class="row row-cards mt-2">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Active members</h3>
                <div class="card-actions"><a href="{{ route('groups.members', $group) }}" class="btn btn-sm">Manage</a></div>
            </div>
            <div class="table-responsive"><table class="table card-table table-vcenter">
                <thead><tr><th>#</th><th>Name</th><th>Position</th><th>Shares</th></tr></thead>
                <tbody>
                @forelse($group->activeMembers as $m)
                <tr>
                    <td class="text-muted">{{ $m->member_no }}</td>
                    <td><a href="{{ route('members.show',$m) }}" class="text-reset">{{ $m->full_name }}</a></td>
                    <td>{{ ucfirst($m->pivot->position) }}</td>
                    <td>{{ $m->pivot->share_count }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted">No members yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Rules</h3>
                <div class="card-actions"><a href="{{ route('groups.rules.index',$group) }}" class="btn btn-sm">Manage</a></div>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($group->rules->take(8) as $r)
                <li class="list-group-item d-flex justify-content-between"><span>{{ $r->label }}</span>
                    <strong>{{ $r->value }}{{ $r->type === 'percent' ? '%' : '' }}</strong></li>
                @empty
                <li class="list-group-item text-muted">No rules defined.</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Schedules</h3>
                <div class="card-actions"><a href="{{ route('groups.schedules.index',$group) }}" class="btn btn-sm">Manage</a></div>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($group->schedules as $s)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>{{ $s->name }}</strong>
                            <div class="text-muted small">{{ ucfirst($s->frequency) }} · {{ ucfirst(str_replace('_',' ',$s->type)) }}</div>
                        </div>
                        <span class="badge bg-{{ $s->is_active ? 'green' : 'secondary' }}-lt">{{ number_format($s->amount, 0) }} {{ $group->currency }}</span>
                    </div>
                </li>
                @empty
                <li class="list-group-item text-muted">No schedules defined.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
