@extends('layouts.app')
@section('title','Loans')
@section('content')

<x-page_header title="Loans" pretitle="Credit">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'loans'])
        @if(auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
        <form method="POST" action="{{ route('loans.apply.interest') }}" class="d-inline"
              onsubmit="return confirm('Apply interest penalties to all overdue flat loans in the active group?')">
            @csrf
            @if(session('active_group_id'))
                <input type="hidden" name="group_id" value="{{ session('active_group_id') }}">
            @endif
            <button type="submit" class="btn btn-outline-warning">
                <i class="ti ti-percentage me-1"></i>Apply interest penalties
            </button>
        </form>
        @endif
        @can('create', App\Models\Loan::class)
        <a href="{{ route('loans.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>
            @if(auth()->user()->hasRole('member')) Request a loan @else New loan @endif
        </a>
        @endcan
    </x-slot>
</x-page_header>

@if(auth()->user()->hasRole('member'))
    @php $isGroupView = request('view') === 'group'; @endphp
    <div class="btn-group mt-3" role="group">
        <a href="{{ route('loans.index') }}" class="btn btn-sm {{ $isGroupView ? 'btn-outline-primary' : 'btn-primary' }}">My loans</a>
        <a href="{{ route('loans.index', ['view' => 'group']) }}" class="btn btn-sm {{ $isGroupView ? 'btn-primary' : 'btn-outline-primary' }}">Group loans</a>
    </div>
@endif

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            @unless(auth()->user()->hasRole('member'))
            <div class="col-md-4">
                <select name="group_id" class="form-select">
                    <option value="">All accessible groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(request('group_id') == $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Any status</option>
                    @foreach(['requested','approved','rejected','disbursed','repaying','paid','defaulted','written_off'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary"><i class="ti ti-filter me-1"></i>Filter</button></div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr>
                <th>Ref</th><th>Member</th><th>Group</th>
                <th class="text-end">Principal</th>
                <th class="text-end">Repayable</th>
                <th class="text-end">Outstanding</th>
                <th>Status</th><th>Requested</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($loans as $l)
                <tr>
                    <td><a href="{{ route('loans.show', $l) }}" class="text-reset fw-semibold">{{ $l->reference }}</a></td>
                    <td>{{ $l->member->full_name }}</td>
                    <td>{{ $l->group->name }}</td>
                    <td class="text-end">{{ number_format($l->principal, 0) }}</td>
                    <td class="text-end">{{ number_format($l->total_repayable, 0) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($l->outstanding, 0) }}</td>
                    <td><span class="badge bg-{{ $l->statusBadge() }}-lt">{{ ucfirst(str_replace('_',' ',$l->status)) }}</span></td>
                    <td class="text-muted">{{ $l->requested_on?->format('Y-m-d') }}</td>
                    <td class="text-end"><a href="{{ route('loans.show', $l) }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No loans yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $loans->links() }}</div>
</div>

@endsection
