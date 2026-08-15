@extends('layouts.app')

@section('title', 'Monthly Financial Report')

@section('content')
<div class="page-header d-print-none">
    <div class="row align-items-center">
        <div class="col">
            <div class="page-pretitle">Reports</div>
            <h2 class="page-title">Monthly VSLA Financial Report</h2>
        </div>
        @if($report)
        <div class="col-auto ms-auto d-print-none">
            <div class="btn-list">
                <a href="{{ route('reports.monthly.print', request()->only(['group_id','month'])) }}"
                   target="_blank" class="btn btn-outline-secondary">
                    <i class="ti ti-printer me-1"></i> Print
                </a>
                <a href="{{ route('reports.monthly.export', array_merge(['format' => 'xlsx'], request()->only(['group_id','month']))) }}"
                   class="btn btn-success">
                    <i class="ti ti-file-spreadsheet me-1"></i> Excel
                </a>
                <a href="{{ route('reports.monthly.export', array_merge(['format' => 'pdf'], request()->only(['group_id','month']))) }}"
                   class="btn btn-danger">
                    <i class="ti ti-file-type-pdf me-1"></i> PDF
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

<div class="card mb-3 d-print-none">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Group</label>
                <select name="group_id" class="form-select">
                    @foreach($groupOptions as $g)
                        <option value="{{ $g->id }}" @selected($group && $g->id === $group->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reporting Month</label>
                <input type="month" name="month" class="form-control"
                       value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-filter me-1"></i> Generate
                </button>
            </div>
            @if($group)
            <div class="col-md-2 text-end">
                <a href="{{ route('reports.monthly') }}" class="btn btn-link">Reset</a>
            </div>
            @endif
        </form>
    </div>
</div>

@if($report)
    @php $currency = $report['header']['currency']; @endphp

    {{-- Tab switcher --}}
    <ul class="nav nav-tabs mb-3 d-print-none" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="full-tab" data-bs-toggle="tab"
                    data-bs-target="#full-report" type="button" role="tab">
                <i class="ti ti-report me-1"></i> Full Report
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sheet-tab" data-bs-toggle="tab"
                    data-bs-target="#sheet-report" type="button" role="tab">
                <i class="ti ti-table me-1"></i> Consolidated Sheet
            </button>
        </li>
    </ul>

    <div class="tab-content" id="reportTabsContent">
        {{-- Full professional report --}}
        <div class="tab-pane fade show active" id="full-report" role="tabpanel">
            @include('reports.monthly._ledger', ['report' => $report, 'currency' => $currency])
        </div>
        {{-- Kinyarwanda single-sheet view --}}
        <div class="tab-pane fade" id="sheet-report" role="tabpanel">
            @include('reports.monthly._sheet', ['report' => $report, 'currency' => $currency])
        </div>
    </div>

@else
    <div class="empty">
        <div class="empty-icon"><i class="ti ti-report-money" style="font-size:3rem;opacity:.3"></i></div>
        <p class="empty-title">Pick a group and month to generate the report.</p>
        <p class="empty-subtitle text-muted">Use the filter above to choose what you want to see.</p>
    </div>
@endif
@endsection
