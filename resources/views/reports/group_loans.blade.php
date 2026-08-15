@extends('layouts.app')
@section('title','Group Loans Report')
@section('content')

<x-page_header title="Group Loans Report" pretitle="Reports">
    <x-slot name="actions">
        @if($byMember->isNotEmpty())
        <div class="btn-group">
            <a href="{{ route('reports.export', array_merge(['report'=>'group_loans','format'=>'pdf'], request()->only(['group_id','status']))) }}"
               class="btn btn-danger btn-sm"><i class="ti ti-file-type-pdf me-1"></i>PDF</a>
            <a href="{{ route('reports.export', array_merge(['report'=>'group_loans','format'=>'xlsx'], request()->only(['group_id','status']))) }}"
               class="btn btn-success btn-sm"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
            <a href="{{ route('reports.export', array_merge(['report'=>'group_loans','format'=>'csv'], request()->only(['group_id','status']))) }}"
               class="btn btn-outline-secondary btn-sm"><i class="ti ti-file-text me-1"></i>CSV</a>
        </div>
        @endif
    </x-slot>
</x-page_header>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            @if($groupOptions->count() > 1)
            <div class="col-md-4">
                <label class="form-label fw-semibold">Group</label>
                <select name="group_id" class="form-select">
                    <option value="">— All accessible groups —</option>
                    @foreach($groupOptions as $g)
                        <option value="{{ $g->id }}" @selected(request('group_id') == $g->id || ($group && $g->id == $group->id))>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-3">
                <label class="form-label fw-semibold">Loan status</label>
                <select name="status" class="form-select">
                    <option value="active"   @selected($statuses === 'active')>Active (disbursed + repaying)</option>
                    <option value="all"      @selected($statuses === 'all')>All statuses</option>
                    <option value="disbursed"  @selected($statuses === 'disbursed')>Disbursed</option>
                    <option value="repaying"   @selected($statuses === 'repaying')>Repaying</option>
                    <option value="paid"       @selected($statuses === 'paid')>Paid off</option>
                    <option value="defaulted"  @selected($statuses === 'defaulted')>Defaulted</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="ti ti-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

@if($byMember->isEmpty())
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="ti ti-report-off fs-1 d-block mb-2"></i>
        No loans match the selected filters.
    </div></div>
@else

{{-- KPI summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-muted small">Total loans</div>
                <div class="h2 mb-0">{{ $totals['count'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-muted small">Total principal</div>
                <div class="h2 mb-0">{{ number_format($totals['principal'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm border-danger">
            <div class="card-body">
                <div class="text-muted small">Total outstanding</div>
                <div class="h2 mb-0 text-danger">{{ number_format($totals['outstanding'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-sm border-success">
            <div class="card-body">
                <div class="text-muted small">Total repaid</div>
                <div class="h2 mb-0 text-success">{{ number_format($totals['repaid'], 0) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Per-member loan tables --}}
@foreach($byMember as $memberId => $memberLoans)
@php
    $member   = $memberLoans->first()?->member;
    $subTotal = [
        'principal'   => $memberLoans->sum('principal'),
        'outstanding' => $memberLoans->sum('outstanding'),
        'repaid'      => $memberLoans->sum('amount_repaid'),
        'interest'    => $memberLoans->sum('total_interest'),
    ];
@endphp
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        @if($member)
        <span class="avatar avatar-sm"
              style="background-image:url('{{ $member->photo_url ?? '' }}')">
            @if(!$member->photo_url){{ substr($member->full_name,0,1) }}@endif
        </span>
        @endif
        <div>
            <strong class="me-2">{{ $member?->full_name ?? 'Unknown Member' }}</strong>
            <span class="text-muted small">{{ $member?->member_no }}</span>
        </div>
        <div class="ms-auto text-end">
            <span class="badge bg-blue-lt me-1">{{ $memberLoans->count() }} loan{{ $memberLoans->count() !== 1 ? 's' : '' }}</span>
            <span class="badge bg-red-lt">Outstanding: {{ number_format($subTotal['outstanding'], 0) }}</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-vcenter mb-0">
            <thead class="table-light">
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Rate %</th>
                    <th>Disbursed</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Repaid</th>
                    <th class="text-end text-danger">Outstanding</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($memberLoans as $l)
            <tr>
                <td class="fw-semibold">
                    <a href="{{ route('loans.show', $l) }}" class="text-reset">{{ $l->reference }}</a>
                    @if($l->prior_outstanding > 0)
                        <span class="badge bg-info-lt ms-1" title="Combined loan">merged</span>
                    @endif
                </td>
                <td>
                    @if($l->isCompound())
                        <span class="badge bg-purple-lt">Compound</span>
                    @else
                        <span class="badge bg-azure-lt">Flat</span>
                    @endif
                </td>
                <td><span class="badge bg-{{ $l->statusBadge() }}-lt">{{ ucfirst(str_replace('_',' ',$l->status)) }}</span></td>
                <td class="text-end">{{ number_format((float)$l->principal, 0) }}</td>
                <td class="text-end">{{ rtrim(rtrim(number_format((float)$l->interest_rate_pct,3),'0'),'.') }}%</td>
                <td class="text-muted small">{{ $l->disbursed_on?->format('d M Y') ?? '—' }}</td>
                <td class="text-end text-orange">{{ number_format((float)$l->total_interest, 0) }}</td>
                <td class="text-end text-success">{{ number_format((float)$l->amount_repaid, 0) }}</td>
                <td class="text-end fw-bold text-{{ (float)$l->outstanding > 0 ? 'danger' : 'muted' }}">
                    {{ number_format((float)$l->outstanding, 0) }}
                </td>
                <td><a href="{{ route('loans.show', $l) }}" class="btn btn-sm btn-ghost-secondary"><i class="ti ti-eye"></i></a></td>
            </tr>
            @endforeach
            </tbody>
            <tfoot class="table-secondary fw-semibold">
                <tr>
                    <td colspan="3" class="text-muted">Member subtotal</td>
                    <td class="text-end">{{ number_format($subTotal['principal'], 0) }}</td>
                    <td></td>
                    <td></td>
                    <td class="text-end text-orange">{{ number_format($subTotal['interest'], 0) }}</td>
                    <td class="text-end text-success">{{ number_format($subTotal['repaid'], 0) }}</td>
                    <td class="text-end text-danger">{{ number_format($subTotal['outstanding'], 0) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endforeach

{{-- Grand total --}}
<div class="card bg-dark text-white mb-4">
    <div class="card-body">
        <div class="row text-center g-3">
            <div class="col-6 col-md-3">
                <div class="small text-white-50">Total loans</div>
                <div class="h3 mb-0">{{ $totals['count'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-white-50">Total principal</div>
                <div class="h3 mb-0">{{ number_format($totals['principal'], 0) }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-white-50">Total outstanding</div>
                <div class="h3 mb-0 text-danger">{{ number_format($totals['outstanding'], 0) }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small text-white-50">Total repaid</div>
                <div class="h3 mb-0 text-success">{{ number_format($totals['repaid'], 0) }}</div>
            </div>
        </div>
    </div>
</div>

@endif

@endsection
