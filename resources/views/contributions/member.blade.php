@extends('layouts.app')
@section('title', ($member?->full_name ?? 'My') . ' — Contributions')
@section('content')

<x-page_header :title="$member?->full_name ?? 'My contributions'" pretitle="Contributions">
    <x-slot name="actions">
        @if(!($selfView ?? false))
        <a href="{{ route('contributions.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>All members
        </a>
        @else
        {{-- Member toggle --}}
        @php $isGroupView = request('view') === 'group'; @endphp
        <a href="{{ route('contributions.index') }}" class="btn btn-sm {{ $isGroupView ? 'btn-outline-primary' : 'btn-primary' }}">My contributions</a>
        <a href="{{ route('contributions.index', ['view' => 'group']) }}" class="btn btn-sm {{ $isGroupView ? 'btn-primary' : 'btn-outline-primary' }}">Group view</a>
        @endif

        @include('partials._report_downloads', ['report' => 'contributions', 'params' => array_merge(request()->query(), $member ? ['member_id' => $member->id] : [])])

        @hasanyrole('super_admin|group_admin|treasurer')
        <form method="POST" action="{{ route('arrears.run') }}" class="d-inline">
            @csrf
            @if(session('active_group_id'))
            <input type="hidden" name="group_id" value="{{ session('active_group_id') }}">
            @endif
            <button type="submit" class="btn btn-outline-orange" title="Recalculate late fees and compound interest for the active group">
                <i class="ti ti-calculator me-1"></i>Calculate interest
            </button>
        </form>
        @endhasanyrole

        @can('create', App\Models\Contribution::class)
        @if($member)
        <a href="{{ route('payments.create', ['member_id' => $member->id]) }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i>Record payment
        </a>
        @else
        <a href="{{ route('payments.create') }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i>Record payment
        </a>
        @endif
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
@php
    $pct      = $stats->total_expected > 0 ? min(100, round($stats->total_paid / $stats->total_expected * 100)) : 0;
    $barColor = $stats->overdue_count > 0 ? 'red' : ($pct >= 100 ? 'green' : 'blue');
@endphp
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
            {{-- KPI pills --}}
            <div class="col-12 col-md-auto">
                <div class="d-flex flex-wrap gap-2">
                    <div class="text-center px-3 py-2 rounded bg-green-lt">
                        <div class="fw-bold text-green">{{ $stats->paid_count }}</div>
                        <div class="text-muted" style="font-size:.72rem">Paid</div>
                    </div>
                    <div class="text-center px-3 py-2 rounded bg-yellow-lt">
                        <div class="fw-bold text-yellow">{{ $stats->pending_count }}</div>
                        <div class="text-muted" style="font-size:.72rem">Pending</div>
                    </div>
                    <div class="text-center px-3 py-2 rounded bg-red-lt">
                        <div class="fw-bold text-red">{{ $stats->overdue_count }}</div>
                        <div class="text-muted" style="font-size:.72rem">Overdue</div>
                    </div>
                    <div class="text-center px-3 py-2 rounded bg-blue-lt">
                        <div class="fw-bold text-blue">{{ number_format($stats->total_paid, 0) }}</div>
                        <div class="text-muted" style="font-size:.72rem">Total paid</div>
                    </div>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="col-12">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Savings progress</span>
                    <span>{{ number_format($stats->total_paid, 0) }} / {{ number_format($stats->total_expected, 0) }} ({{ $pct }}%)</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar bg-{{ $barColor }}" style="width:{{ $pct }}%"></div>
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
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Any status</option>
                    @foreach(['pending','partial','paid','overdue','waived'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">Any type</option>
                    @foreach(['savings','social_fund','loan_repayment','fine','late_fee','other'] as $t)
                        <option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst(str_replace('_',' ',$t)) }}</option>
                    @endforeach
                </select>
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
            @if(request()->hasAny(['status','type','from','to']))
            <div class="col-12">
                <a href="{{ route('contributions.index', $member ? ['member_id' => $member->id] : []) }}"
                   class="text-muted small">
                    <i class="ti ti-x me-1"></i>Clear filters
                </a>
            </div>
            @endif
        </form>
    </div>

    {{-- ── Contributions table ─────────────────────────────────────────── --}}
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Type</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th class="text-end">Expected</th>
                    <th class="text-end text-orange">Penalty</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contributions as $c)
                @php $hasPenaltySchedule = isset($penaltySchedules[$c->id]) && !empty($penaltySchedules[$c->id]); @endphp
                <tr>
                    <td class="text-muted small">
                        {{ $c->period_start->format('M Y') }}
                        @if($c->period_start->format('Y-m') !== $c->period_end->format('Y-m'))
                            → {{ $c->period_end->format('M Y') }}
                        @endif
                    </td>
                    <td>{{ ucfirst(str_replace('_',' ',$c->type)) }}</td>
                    <td class="text-muted small">{{ $c->due_on->format('d M Y') }}</td>
                    <td>@include('contributions._status', ['status' => $c->status])</td>
                    <td class="text-end">{{ number_format($c->expected_amount, 0) }}</td>
                    <td class="text-end">
                        @if((float)$c->late_fee_amount > 0)
                            <span class="text-orange fw-semibold" title="Late penalty">
                                +{{ number_format($c->late_fee_amount, 0) }}
                            </span>
                            @if($hasPenaltySchedule)
                            @php
                                $currentRow    = collect($penaltySchedules[$c->id])->firstWhere('is_current', true);
                                $compoundExtra = $currentRow ? (float)($currentRow['compound_extra'] ?? 0) : 0;
                            @endphp
                            @if($compoundExtra > 0)
                            <div style="font-size:.7rem; line-height:1.3; margin-top:1px">
                                <span class="text-orange" style="opacity:.75"
                                      title="Extra charged due to compound (interest on interest)">
                                    ↑ +{{ number_format($compoundExtra, 0) }} compound
                                </span>
                            </div>
                            @endif
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end text-green fw-semibold">
                        {{ $c->paid_amount > 0 ? number_format($c->paid_amount, 0) : '—' }}
                    </td>
                    <td class="text-end">
                        @php $balance = (float)$c->expected_amount + (float)$c->late_fee_amount - (float)$c->paid_amount; @endphp
                        @if($balance > 0)
                            <span class="text-red fw-semibold">{{ number_format($balance, 0) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($hasPenaltySchedule)
                        <button type="button"
                                class="btn btn-sm btn-outline-orange penalty-toggle"
                                data-target="penalty-row-{{ $c->id }}"
                                title="Penalty schedule">
                            <i class="ti ti-trending-up"></i>
                        </button>
                        @endif
                        @if(($selfView ?? false) || !auth()->user()->hasRole('member'))
                        <a href="{{ route('contributions.show', $c) }}"
                           class="btn btn-sm btn-outline-secondary" title="View details">
                            <i class="ti ti-eye"></i>
                        </a>
                        @endif
                        @can('create', App\Models\Contribution::class)
                            @if(in_array($c->status, ['pending','partial','overdue']))
                            <a href="{{ route('payments.create', ['contribution_id' => $c->id]) }}"
                               class="btn btn-sm btn-primary" title="Record payment">
                                <i class="ti ti-cash"></i>
                            </a>
                            @endif
                        @endcan
                    </td>
                </tr>
                {{-- Expandable penalty schedule row --}}
                @if($hasPenaltySchedule)
                @php
                    $psRows     = $penaltySchedules[$c->id];
                    $isCompound = (bool) $c->group->rule('penalty_on_penalty', false);
                @endphp
                <tr id="penalty-row-{{ $c->id }}" class="d-none bg-orange-lt" style="border-left:3px solid #f76707">
                    <td colspan="9" class="py-0 px-0">
                        <div class="px-3 pt-2 pb-1">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fw-semibold text-orange small">
                                    <i class="ti ti-trending-up me-1"></i>Penalty schedule
                                </span>
                                @if($isCompound)
                                    <span class="badge bg-orange text-white" style="font-size:.65rem">Compound — interest on interest</span>
                                @else
                                    <span class="badge bg-secondary" style="font-size:.65rem">Standard — flat per period</span>
                                @endif
                            </div>
                            <table class="table table-sm mb-1" style="font-size:.8rem">
                                <thead>
                                    <tr class="text-muted">
                                        <th class="py-1">#</th>
                                        <th class="py-1">Applies from</th>
                                        <th class="text-end py-1">Fee (compound)</th>
                                        @if($isCompound)<th class="text-end py-1">Fee (flat equiv.)</th>
                                        <th class="text-end py-1 text-orange">Extra</th>@endif
                                        <th class="text-end py-1">Cumulative</th>
                                        <th class="text-end py-1">Total owed</th>
                                        <th class="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($psRows as $row)
                                <tr class="{{ $row['is_current'] ? 'fw-semibold' : '' }} {{ $row['is_future'] ? 'text-muted' : '' }}">
                                    <td class="py-1 text-muted">{{ $row['n'] }}</td>
                                    <td class="py-1">{{ $row['from']->format('d M Y') }}</td>
                                    <td class="text-end py-1 {{ $row['is_future'] ? 'text-muted' : 'text-orange' }}">
                                        +{{ number_format($row['fee'], 0) }}
                                    </td>
                                    @if($isCompound)
                                    <td class="text-end py-1 text-muted">
                                        +{{ number_format($row['flat_fee'], 0) }}
                                    </td>
                                    <td class="text-end py-1">
                                        @if($row['compound_extra'] > 0)
                                            <span class="text-orange fw-semibold">+{{ number_format($row['compound_extra'], 0) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    @endif
                                    <td class="text-end py-1">{{ number_format($row['total_fee'], 0) }}</td>
                                    <td class="text-end py-1 {{ $row['is_future'] ? 'text-muted' : 'fw-semibold' }}">
                                        {{ number_format($row['total_owed'], 0) }}
                                    </td>
                                    <td class="py-1">
                                        @if($row['is_future'])
                                            <span class="badge bg-blue-lt text-blue" style="font-size:.6rem">projected</span>
                                        @elseif($row['is_charged'])
                                            <span class="badge bg-green-lt text-green" style="font-size:.6rem">charged</span>
                                        @else
                                            <span class="badge bg-orange-lt text-orange" style="font-size:.6rem">pending</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @if($isCompound)
                            <p class="text-muted mb-2" style="font-size:.72rem">
                                <i class="ti ti-info-circle me-1"></i>
                                Each period's fee compounds on original + all prior unpaid penalties. Pay sooner to stop the growth.
                            </p>
                            @endif
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="ti ti-clipboard-off" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem"></i>
                        No contributions match the selected filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
            {{-- ── Totals footer (reflects current filters across ALL pages) ──── --}}
            @if($filteredTotals && $filteredTotals->row_count > 0)
            @php
                $sumExpected  = (float) $filteredTotals->sum_expected;
                $sumLateFee   = (float) $filteredTotals->sum_late_fee;
                $sumPaid      = (float) $filteredTotals->sum_paid;
                $sumBalance   = (float) $filteredTotals->sum_balance;
                $allPaid      = $sumBalance <= 0;
            @endphp
            <tfoot>
                <tr style="border-top:2px solid #dee2e6; background:#f8fafc;">
                    <td colspan="4" class="fw-semibold text-muted py-3 ps-3">
                        <i class="ti ti-sum me-1 text-primary"></i>
                        Totals
                        @if($contributions->hasPages())
                            <span class="text-muted fw-normal" style="font-size:.78rem">
                                — all {{ number_format($filteredTotals->row_count) }} rows
                                (page {{ $contributions->currentPage() }} of {{ $contributions->lastPage() }})
                            </span>
                        @else
                            <span class="text-muted fw-normal" style="font-size:.78rem">
                                — {{ $filteredTotals->row_count }} {{ Str::plural('row', $filteredTotals->row_count) }}
                            </span>
                        @endif
                    </td>
                    <td class="text-end fw-bold py-3">
                        {{ number_format($sumExpected, 0) }}
                    </td>
                    <td class="text-end fw-bold text-orange py-3">
                        {{ $sumLateFee > 0 ? '+'.number_format($sumLateFee, 0) : '—' }}
                    </td>
                    <td class="text-end fw-bold text-green py-3">
                        {{ number_format($sumPaid, 0) }}
                    </td>
                    <td class="text-end fw-bold py-3 {{ $allPaid ? 'text-muted' : 'text-red' }}">
                        {{ $allPaid ? '—' : number_format($sumBalance, 0) }}
                    </td>
                    <td></td>
                </tr>
                @if(!$allPaid)
                <tr style="background:#fff5f5;">
                    <td colspan="9" class="py-2 px-3" style="font-size:.8rem">
                        <span class="text-red fw-semibold">
                            <i class="ti ti-alert-triangle me-1"></i>
                            Outstanding balance: {{ number_format($sumBalance, 0) }}
                        </span>
                        @if($sumLateFee > 0)
                            <span class="text-orange ms-2">
                                (incl. {{ number_format($sumLateFee, 0) }} in penalties)
                            </span>
                        @endif
                        <span class="text-muted ms-2">
                            ({{ $sumExpected > 0 ? round($sumPaid / $sumExpected * 100) : 0 }}% of base collected)
                        </span>
                    </td>
                </tr>
                @else
                <tr style="background:#f0fdf4;">
                    <td colspan="9" class="py-2 px-3" style="font-size:.8rem">
                        <span class="text-green fw-semibold">
                            <i class="ti ti-circle-check me-1"></i>
                            Fully paid — all {{ number_format($sumExpected, 0) }} collected
                        </span>
                    </td>
                </tr>
                @endif
            </tfoot>
            @endif
        </table>
    </div>
    <div class="card-footer">{{ $contributions->links() }}</div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.penalty-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var targetId = btn.getAttribute('data-target');
        var row = document.getElementById(targetId);
        if (!row) return;
        var isHidden = row.classList.contains('d-none');
        row.classList.toggle('d-none', !isHidden);
        // Rotate the icon to indicate open/closed state
        btn.classList.toggle('btn-outline-orange', isHidden);
        btn.classList.toggle('btn-orange',          !isHidden);
        var icon = btn.querySelector('i');
        if (icon) icon.style.transform = isHidden ? 'rotate(0deg)' : '';
    });
});
</script>
@endpush

@endsection
