@extends('layouts.app')
@section('title', $rotation->name.' · Rotation')
@section('content')

@php $fmt = fn ($v) => number_format((float) $v, 0); $cur = $rotation->group?->currency ?? 'RWF'; @endphp

<x-page_header :title="$rotation->name" :pretitle="'Rotation · '.$rotation->group?->name">
    <x-slot name="actions">
        <a href="{{ route('rotations.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Back
        </a>
        @can('delete', $rotation)
            @if($rotation->status === 'active')
                <form method="POST" action="{{ route('rotations.destroy', $rotation) }}"
                      onsubmit="return confirm('Cancel this rotation? Scheduled turns will be marked skipped.');"
                      class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="ti ti-x me-1"></i> Cancel rotation</button>
                </form>
            @endif
        @endcan
    </x-slot>
</x-page_header>

@if(session('status'))
    <div class="alert alert-success mt-3">{{ session('status') }}</div>
@endif

{{-- ===== Headline ===== --}}
<div class="row row-cards mt-3">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center">
            <div class="bg-cyan-lt rounded p-3 me-3"><i class="ti ti-rotate-clockwise fs-2 text-cyan"></i></div>
            <div>
                <div class="text-muted small text-uppercase">Cadence</div>
                <div class="h3 mb-0">{{ $rotation->frequencyLabel() }}</div>
                <div class="small text-muted">{{ $rotation->recipients_per_turn }} recipient(s) / turn</div>
            </div>
        </div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center">
            <div class="bg-blue-lt rounded p-3 me-3"><i class="ti ti-percentage fs-2 text-blue"></i></div>
            <div>
                <div class="text-muted small text-uppercase">Disbursement rule</div>
                <div class="h3 mb-0">{{ $rotation->disbursementLabel() }}</div>
            </div>
        </div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center">
            <div class="bg-green-lt rounded p-3 me-3"><i class="ti ti-cash fs-2 text-green"></i></div>
            <div>
                <div class="text-muted small text-uppercase">Cash on hand</div>
                <div class="h3 mb-0">{{ $fmt($cashOnHand) }} {{ $cur }}</div>
            </div>
        </div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card"><div class="card-body d-flex align-items-center">
            <div class="bg-yellow-lt rounded p-3 me-3"><i class="ti ti-calendar-event fs-2 text-yellow"></i></div>
            <div>
                <div class="text-muted small text-uppercase">Status</div>
                <div class="h3 mb-0">
                    <span class="badge bg-{{ $rotation->statusBadge() }}-lt">{{ ucfirst($rotation->status) }}</span>
                </div>
                <div class="small text-muted">Next: {{ $rotation->next_turn_on?->format('Y-m-d') ?: '—' }}</div>
            </div>
        </div></div>
    </div>
</div>

{{-- ===== Next turn execution panel ===== --}}
@if($nextTurn)
<div class="card mt-3 border-primary">
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-player-play me-1 text-primary"></i>
            Next turn — #{{ $nextTurn->sequence_no }} · scheduled {{ $nextTurn->scheduled_on->format('Y-m-d') }}
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-7">
                <h4 class="mb-2">Up next</h4>
                <ol class="list-group list-group-flush border rounded">
                    @forelse($nextRecipients as $rm)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <span class="badge bg-azure-lt me-2">#{{ $rm->position }}</span>
                                {{ $rm->member?->full_name }}
                                <span class="text-muted small ms-2">{{ $rm->member?->member_no }}</span>
                            </span>
                            <span class="text-muted small">received {{ $rm->received_count }}×</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">All members have already received this cycle.</li>
                    @endforelse
                </ol>
            </div>
            <div class="col-md-5">
                @can('execute', $rotation)
                <form method="POST" action="{{ route('rotations.turns.execute', [$rotation, $nextTurn]) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Total to disburse</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="amount" class="form-control"
                                   value="{{ number_format($plannedAmount, 2, '.', '') }}" min="0.01" required>
                            <span class="input-group-text">{{ $cur }}</span>
                        </div>
                        <div class="form-hint small">
                            Suggested by rule: <strong>{{ $fmt($plannedAmount) }}</strong> ·
                            split equally across <strong>{{ $nextRecipients->count() }}</strong> recipient(s).
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Paid on</label>
                            <input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Method</label>
                            <select name="method" class="form-select">
                                @foreach(['cash'=>'Cash','mobile_money'=>'Mobile money','bank'=>'Bank','cheque'=>'Cheque','other'=>'Other'] as $k=>$v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary flex-grow-1" type="submit"
                                onclick="return confirm('Disburse this turn now?');">
                            <i class="ti ti-check me-1"></i> Execute turn
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('rotations.turns.skip', [$rotation, $nextTurn]) }}" class="mt-2">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="reason" class="form-control" placeholder="Reason for skipping (optional)">
                        <button class="btn btn-outline-secondary" type="submit"
                                onclick="return confirm('Skip this turn?');">
                            <i class="ti ti-player-skip-forward me-1"></i> Skip
                        </button>
                    </div>
                </form>
                @else
                <div class="alert alert-info mb-0">Only group admins or the treasurer can execute turns.</div>
                @endcan
            </div>
        </div>
    </div>
</div>
@elseif($rotation->status === 'completed')
<div class="alert alert-success mt-3">
    <h4 class="mb-1"><i class="ti ti-flag-check me-1"></i> Rotation complete</h4>
    Every member has received a payout in this cycle.
</div>
@endif

{{-- ===== Recipient list ===== --}}
<div class="card mt-3">
    <div class="card-header"><h3 class="card-title">Recipient list</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>Member</th><th class="text-end">Times received</th><th>Last received</th></tr></thead>
            <tbody>
                @foreach($rotation->members as $rm)
                    <tr class="{{ $rm->received_count > 0 ? '' : 'table-warning' }}">
                        <td>{{ $rm->position }}</td>
                        <td>{{ $rm->member?->full_name }} <span class="text-muted small">{{ $rm->member?->member_no }}</span></td>
                        <td class="text-end">{{ $rm->received_count }}</td>
                        <td class="text-muted">{{ $rm->last_received_on?->format('Y-m-d') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ===== Turn history ===== --}}
<div class="card mt-3">
    <div class="card-header"><h3 class="card-title">Turns</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th class="text-end">Disbursed</th>
                    <th>Executed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rotation->turns as $turn)
                    <tr>
                        <td>{{ $turn->sequence_no }}</td>
                        <td class="text-muted">{{ $turn->scheduled_on->format('Y-m-d') }}</td>
                        <td><span class="badge bg-{{ $turn->statusBadge() }}-lt">{{ ucfirst($turn->status) }}</span></td>
                        <td>
                            @if($turn->payouts->count())
                                @foreach($turn->payouts as $p)
                                    <div class="small">
                                        <i class="ti ti-user me-1 text-muted"></i>
                                        {{ $p->member?->full_name }}
                                        <span class="text-muted">— {{ $fmt($p->amount) }} {{ $cur }}</span>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $fmt($turn->disbursement_total) }}</td>
                        <td class="small text-muted">
                            {{ $turn->executed_on?->format('Y-m-d') ?: '—' }}
                            @if($turn->executor) · {{ $turn->executor->name }}@endif
                            @if($turn->notes)<div class="text-muted">“{{ $turn->notes }}”</div>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($rotation->notes)
<div class="card mt-3"><div class="card-body">
    <div class="text-muted small text-uppercase mb-1">Notes</div>
    <div>{{ $rotation->notes }}</div>
</div></div>
@endif

@endsection
