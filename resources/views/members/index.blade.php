@extends('layouts.app')
@section('title','Members')
@section('content')

@php $isAdminish = auth()->user()->hasAnyRole(['super_admin', 'group_admin']); @endphp

<x-page_header title="Members" pretitle="Directory">
    <x-slot name="actions">
        @if($isAdminish)
        {{-- Carries the active filters through so admins can print only
             the rows they're currently looking at. --}}
        <a href="{{ route('members.cards', request()->only(['search','status','group_id'])) }}"
           class="btn" target="_blank" rel="noopener">
            <i class="ti ti-id me-1"></i>{{ __('Print ID cards') }}
        </a>
        @endif
        @can('create', App\Models\Member::class)
        <a href="{{ route('members.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('New member') }}</a>
        @endcan
    </x-slot>
</x-page_header>

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            <div class="col-md-5">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                       placeholder="Search name, member #, phone or NIN…">
            </div>
            <div class="col-md-3">
                <select name="group_id" class="form-select">
                    <option value="">All groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(request('group_id') == $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Any status</option>
                    @foreach(['active','inactive','suspended','exited'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary"><i class="ti ti-search me-1"></i>Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Groups</th><th>Status</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @forelse($members as $m)
                <tr>
                    <td><span class="text-muted">{{ $m->member_no }}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="avatar avatar-sm me-2" style="background-image:url('{{ $m->photo_url }}')"></span>
                            <div>
                                <a href="{{ route('members.show', $m) }}" class="text-reset fw-semibold">{{ $m->full_name }}</a>
                                <div class="text-muted small">{{ $m->village }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $m->phone }}</td>
                    <td>
                        @foreach($m->groups as $g)<span class="badge bg-blue-lt me-1">{{ $g->name }}</span>@endforeach
                    </td>
                    <td><span class="badge bg-{{ ['active'=>'green','inactive'=>'secondary','suspended'=>'red','exited'=>'dark'][$m->status] ?? 'secondary' }}-lt">{{ ucfirst($m->status) }}</span></td>
                    <td class="text-muted">{{ $m->joined_on?->format('Y-m-d') }}</td>
                    <td class="text-end">
                        @if($isAdminish)
                        <div class="btn-list">
                            <a href="{{ route('members.show', $m) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('View') }}"><i class="ti ti-eye"></i></a>
                            <a href="{{ route('passbooks.show', $m) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Passbook') }}"><i class="ti ti-book"></i></a>
                            <a href="{{ route('members.card', $m) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="{{ __('Print ID card') }}"><i class="ti ti-id"></i></a>
                            @can('update', $m)
                            <a href="{{ route('members.edit', $m) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-edit"></i></a>
                            @endcan
                        </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No members found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $members->links() }}</div>
</div>

@endsection
