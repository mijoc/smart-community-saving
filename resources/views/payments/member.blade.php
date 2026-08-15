@extends('layouts.app')
@section('title', ($member?->full_name ?? 'My') . ' — Payments')
@section('content')

<x-page_header :title="$member?->full_name ?? 'My payments'" pretitle="Payments">
    <x-slot name="actions">
        @if(!($selfView ?? false))
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>All members
        </a>
        @else
        @php $isGroupView = request('view') === 'group'; @endphp
        <a href="{{ route('payments.index') }}" class="btn btn-sm {{ $isGroupView ? 'btn-outline-primary' : 'btn-primary' }}">My payments</a>
        <a href="{{ route('payments.index', ['view' => 'group']) }}" class="btn btn-sm {{ $isGroupView ? 'btn-primary' : 'btn-outline-primary' }}">Group view</a>
        @endif

        @include('partials._report_downloads', ['report' => 'payments', 'params' => array_merge(request()->query(), $member ? ['member_id' => $member->id] : [])])

        @can('create', App\Models\Contribution::class)
        <a href="{{ route('payments.create', $member ? ['member_id' => $member->id] : []) }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i>Record payment
        </a>
        @endcan
    </x-slot>
</x-page_header>

@if(session('status'))
<div class="alert alert-success alert-dismissible mt-3" role="alert">
    {{ session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Member summary header ────────────────────────────────────────────── --}}
@if($member && $stats)
<div class="card mt-3">
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-auto">
                <span class="avatar avatar-lg rounded-circle"
                      style="background-image: url('{{ $member->photo_url }}')"></span>
            </div>
            <div class="col">
                <div class="fw-bold fs-5">{{ $member->full_name }}</div>
                <div class="text-muted small">
                    {{ $member->member_no }}
                    @if($member->phone) · {{ $member->phone }}@endif
                </div>
            </div>
            {{-- KPI tiles --}}
            <div class="col-12 col-md-auto">
                <div class="d-flex flex-wrap gap-2">
                    <div class="text-center px-3 py-2 rounded bg-blue-lt">
                        <div class="fw-bold text-blue fs-5">{{ number_format($stats->total_payments) }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ Str::plural('Payment', $stats->total_payments) }}</div>
                    </div>
                    <div class="text-center px-3 py-2 rounded bg-green-lt">
                        <div class="fw-bold text-green">{{ number_format($stats->total_amount, 0) }}</div>
                        <div class="text-muted" style="font-size:.72rem">Total paid</div>
                    </div>
                    @if($stats->last_paid_on)
                    <div class="text-center px-3 py-2 rounded bg-azure-lt">
                        <div class="fw-bold text-azure" style="font-size:.9rem">
                            {{ \Carbon\Carbon::parse($stats->last_paid_on)->format('d M Y') }}
                        </div>
                        <div class="text-muted" style="font-size:.72rem">Last payment</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Filter bar ───────────────────────────────────────────────────────── --}}
<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            @if($member)
            <input type="hidden" name="member_id" value="{{ $member->id }}">
            @endif
            <div class="col-md-4">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                       placeholder="Search by reference…">
            </div>
            <div class="col-md-3">
                <input type="date" name="from" value="{{ request('from') }}" class="form-control" placeholder="From date">
            </div>
            <div class="col-md-3">
                <input type="date" name="to" value="{{ request('to') }}" class="form-control" placeholder="To date">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100">
                    <i class="ti ti-search me-1"></i>Filter
                </button>
            </div>
            @if(request()->hasAny(['search','from','to']))
            <div class="col-12">
                <a href="{{ route('payments.index', $member ? ['member_id' => $member->id] : []) }}"
                   class="text-muted small">
                    <i class="ti ti-x me-1"></i>Clear filters
                </a>
            </div>
            @endif
        </form>
    </div>

    {{-- ── Payments table ───────────────────────────────────────────────── --}}
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Applies to</th>
                    <th>Method</th>
                    <th class="text-end">Amount</th>
                    <th>Received by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td>
                        <a href="{{ route('payments.show', $p) }}" class="fw-semibold text-reset">
                            {{ $p->reference }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ $p->paid_on->format('d M Y') }}</td>
                    <td class="text-muted small">
                        @if($p->contribution)
                            {{ ucfirst(str_replace('_',' ',$p->contribution->type)) }}
                            <span class="text-muted">
                                · {{ $p->contribution->period_start?->format('M Y') }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-blue-lt">{{ ucfirst(str_replace('_',' ',$p->method)) }}</span>
                    </td>
                    <td class="text-end fw-semibold text-green">
                        {{ number_format($p->amount, 0) }}
                    </td>
                    <td class="text-muted small">{{ $p->receiver?->name ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('payments.show', $p) }}" class="btn btn-sm btn-outline-secondary" title="View">
                            <i class="ti ti-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="ti ti-receipt-off" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem"></i>
                        No payments match the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>

            {{-- ── Totals footer (all matching rows across all pages) ──────── --}}
            @if($filteredTotals && $filteredTotals->row_count > 0)
            <tfoot>
                <tr style="border-top:2px solid #dee2e6; background:#f8fafc;">
                    <td colspan="4" class="fw-semibold text-muted py-3 ps-3">
                        <i class="ti ti-sum me-1 text-primary"></i>
                        Totals
                        @if($payments->hasPages())
                            <span class="fw-normal" style="font-size:.78rem">
                                — all {{ number_format($filteredTotals->row_count) }} payments
                                (page {{ $payments->currentPage() }} of {{ $payments->lastPage() }})
                            </span>
                        @else
                            <span class="fw-normal" style="font-size:.78rem">
                                — {{ $filteredTotals->row_count }} {{ Str::plural('payment', $filteredTotals->row_count) }}
                            </span>
                        @endif
                    </td>
                    <td class="text-end fw-bold text-green py-3">
                        {{ number_format($filteredTotals->sum_amount, 0) }}
                    </td>
                    <td colspan="2"></td>
                </tr>
                <tr style="background:#f0fdf4;">
                    <td colspan="7" class="py-2 px-3" style="font-size:.8rem">
                        <span class="text-green fw-semibold">
                            <i class="ti ti-circle-check me-1"></i>
                            {{ number_format($filteredTotals->row_count) }} {{ Str::plural('payment', $filteredTotals->row_count) }}
                            totalling {{ number_format($filteredTotals->sum_amount, 0) }} collected
                        </span>
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    <div class="card-footer">{{ $payments->links() }}</div>
</div>

@endsection
