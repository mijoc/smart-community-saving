@extends('layouts.app')
@section('title','Groups')
@section('content')

<x-page_header title="Groups" pretitle="VSLA">
    <x-slot name="actions">
        @can('create', App\Models\Group::class)
        <a href="{{ route('groups.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New group</a>
        @endcan
    </x-slot>
</x-page_header>

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            <div class="col-md-6"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Search by name or code"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-search me-1"></i>Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Code</th><th>Name</th><th>Location</th><th>Currency</th><th>Members</th><th>Group admin(s)</th><th>Cycle</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($groups as $g)
            <tr>
                <td class="text-muted">{{ $g->code }}</td>
                <td><a href="{{ route('groups.show',$g) }}" class="fw-semibold text-reset">{{ $g->name }}</a></td>
                <td class="text-muted small">{{ $g->village }}, {{ $g->district }}</td>
                <td>{{ $g->currency }}</td>
                <td><span class="badge bg-blue-lt">{{ $g->members_count }}</span></td>
                <td class="small">
                    @forelse($g->staffUsers as $admin)
                        <span class="badge bg-purple-lt me-1" title="{{ $admin->email }}">
                            <i class="ti ti-user-shield me-1"></i>{{ $admin->name }}
                        </span>
                    @empty
                        @can('update', $g)
                            <a href="{{ route('groups.members', $g) }}" class="text-muted">
                                <i class="ti ti-user-plus me-1"></i>Assign
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endcan
                    @endforelse
                </td>
                <td class="text-muted small">
                    @if($g->cycle_starts_on){{ $g->cycle_starts_on->format('Y-m-d') }} → {{ $g->cycle_ends_on?->format('Y-m-d') ?? '—' }}@else—@endif
                </td>
                <td><span class="badge bg-{{ ['active'=>'green','paused'=>'yellow','closed'=>'secondary'][$g->status] ?? 'secondary' }}-lt">{{ ucfirst($g->status) }}</span></td>
                <td class="text-end">
                    <div class="btn-list">
                        <a href="{{ route('groups.show',$g) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-eye"></i></a>
                        <a href="{{ route('groups.members',$g) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-users"></i></a>
                        <a href="{{ route('groups.rules.index',$g) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-settings"></i></a>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No groups yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $groups->links() }}</div>
</div>
@endsection
