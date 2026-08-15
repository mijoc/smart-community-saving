@extends('layouts.app')
@section('title','Bulk collection')
@section('content')

<x-page_header title="Bulk collection" pretitle="Payments">
    <x-slot name="actions">
        @if($schedule)
            @php $sheetParams = ['group_id' => $activeId, 'schedule_id' => $schedule->id]; @endphp
            <a href="{{ route('payments.bulk.sheet', $sheetParams) }}" target="_blank" class="btn btn-outline-secondary">
                <i class="ti ti-printer me-1"></i>Printable sheet
            </a>
            <a href="{{ route('payments.bulk.sheet.csv', $sheetParams) }}" class="btn btn-outline-secondary">
                <i class="ti ti-file-spreadsheet me-1"></i>CSV
            </a>
        @endif
        <a href="{{ route('payments.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back to payments</a>
    </x-slot>
</x-page_header>

{{-- ========== Step 1: pick group + schedule ========== --}}
<form method="GET" action="{{ route('payments.bulk') }}" class="card mt-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label required">Group</label>
                <select name="group_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— pick a group —</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected($activeId === $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label required">Contribution schedule</label>
                <select name="schedule_id" class="form-select" onchange="this.form.submit()" @disabled($schedules->isEmpty())>
                    <option value="">
                        @if($activeId && $schedules->isEmpty())
                            — no active schedules in this group —
                        @else
                            — pick a schedule —
                        @endif
                    </option>
                    @foreach($schedules as $s)
                        <option value="{{ $s->id }}" @selected($schedule && $schedule->id === $s->id)>
                            {{ $s->name }} · {{ ucfirst($s->frequency) }} · {{ number_format($s->amount, 0) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline-secondary w-100"><i class="ti ti-refresh me-1"></i>Reload</button>
            </div>
        </div>
    </div>
</form>

@if($schedule && $rows->isNotEmpty())
    @php
        $payable = $rows->filter(fn($r) => $r->contribution && $r->balance > 0);
        $totalDue = $payable->sum('balance');
        $anyContrib = $rows->contains(fn($r) => $r->contribution);
    @endphp

    @if(! $anyContrib)
        {{-- Schedule exists but no contributions have been generated yet --}}
        <div class="alert alert-warning mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4 class="alert-title"><i class="ti ti-alert-triangle me-1"></i>No contributions to collect yet</h4>
                <div>
                    The schedule <strong>{{ $schedule->name }}</strong> has no contributions generated for any member.
                    Contributions are the per-period bills the system uses to track who owes what — without them, there's nothing to bulk-pay.
                </div>
            </div>
            <form method="POST"
                  action="{{ route('groups.schedules.generate', ['group' => $activeId, 'schedule' => $schedule->id]) }}">
                @csrf
                <button class="btn btn-warning"><i class="ti ti-rotate-clockwise me-1"></i>Generate contributions now</button>
            </form>
        </div>
    @elseif($payable->isEmpty())
        <div class="alert alert-success mt-3">
            <h4 class="alert-title"><i class="ti ti-circle-check me-1"></i>Everybody's up to date</h4>
            Every active member has settled their <strong>{{ $schedule->name }}</strong> contribution. Nothing to collect right now.
        </div>
    @endif

    {{-- ========== Step 2: roster ========== --}}
    <form method="POST" action="{{ route('payments.bulk.store') }}" class="card mt-3" id="bulkForm">@csrf
        <input type="hidden" name="group_id" value="{{ $activeId }}">
        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">

        <div class="card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label required">Paid on</label>
                    <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label required">Default method</label>
                    <select name="method" id="defaultMethod" class="form-select">
                        @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                            <option value="{{ $m }}">{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Used for any row without an override.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Channel reference</label>
                    <input name="channel_ref" class="form-control" placeholder="e.g. MM batch ID">
                </div>
                <div class="col-md-3 text-end">
                    <div class="text-muted small">Outstanding total</div>
                    <div class="h2 mb-0" id="totalOutstanding">{{ number_format($totalDue, 0) }}</div>
                </div>
            </div>

            <div class="mt-3 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="fillAll">
                    <i class="ti ti-wand me-1"></i>Fill all with outstanding balance
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">
                    <i class="ti ti-eraser me-1"></i>Clear all amounts
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="skipMissing">
                    <i class="ti ti-user-off me-1"></i>Mark all empty rows as skipped
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Member</th>
                        <th>Period due</th>
                        <th class="text-end">Outstanding</th>
                        <th style="width:160px;">Amount paid</th>
                        <th style="width:170px;">Method</th>
                        <th style="width:90px;" class="text-center">Skip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $r)
                        @php
                            $c = $r->contribution;
                            $hasDue = $c && $r->balance > 0;
                        @endphp
                        <tr class="{{ $hasDue ? '' : 'text-muted' }}">
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($r->member->photo_path)
                                        <span class="avatar avatar-sm me-2"
                                               style="background-image: url('{{ \App\Models\SystemSetting::publicUrl('/storage/'.$r->member->photo_path) }}')"></span>
                                    @else
                                        <span class="avatar avatar-sm me-2">{{ mb_substr($r->member->full_name, 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <div>{{ $r->member->full_name }}</div>
                                        <div class="text-muted small">{{ $r->member->member_no }}</div>
                                    </div>
                                </div>
                                <input type="hidden" name="rows[{{ $i }}][member_id]" value="{{ $r->member->id }}">
                                @if($c)
                                    <input type="hidden" name="rows[{{ $i }}][contribution_id]" value="{{ $c->id }}">
                                @endif
                            </td>
                            <td>
                                @if($c)
                                    <div>{{ $c->period_start?->format('d M') }} – {{ $c->period_end?->format('d M Y') }}</div>
                                    <div class="text-muted small">due {{ $c->due_on?->format('d M Y') }} · {{ ucfirst($c->status) }}</div>
                                @else
                                    <span class="badge bg-success-lt">Up to date</span>
                                @endif
                            </td>
                            <td class="text-end" data-balance="{{ $r->balance }}">
                                {{ number_format($r->balance, 0) }}
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="{{ $r->balance }}"
                                       name="rows[{{ $i }}][amount]"
                                       class="form-control form-control-sm row-amount"
                                       value="{{ $hasDue ? number_format($r->balance, 2, '.', '') : '' }}"
                                       @disabled(! $hasDue)>
                            </td>
                            <td>
                                <select name="rows[{{ $i }}][method]" class="form-select form-select-sm" @disabled(! $hasDue)>
                                    <option value="">— default —</option>
                                    @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                                        <option value="{{ $m }}">{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="rows[{{ $i }}][skip]" value="0">
                                <input type="checkbox" class="form-check-input row-skip"
                                       name="rows[{{ $i }}][skip]" value="1"
                                       @disabled(! $hasDue)>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="4" class="text-end">Selected total</td>
                        <td colspan="3"><span id="liveTotal" class="h3 mb-0">0.00</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card-footer text-end">
            <a href="{{ route('payments.bulk', ['group_id' => $activeId]) }}" class="btn">Cancel</a>
            <button class="btn btn-primary" id="submitBtn" @disabled($payable->isEmpty())>
                <i class="ti ti-circle-check me-1"></i>Record all payments
            </button>
        </div>
    </form>

@elseif($schedule && $rows->isEmpty())
    <div class="alert alert-info mt-3"><i class="ti ti-info-circle me-1"></i>This group has no active members.</div>
@elseif($activeId && $schedules->isEmpty())
    <div class="alert alert-warning mt-3">
        <i class="ti ti-alert-triangle me-1"></i>This group has no active contribution schedules. Create one first under
        <a href="{{ route('groups.schedules.index', ['group' => $activeId]) }}">Schedules</a>.
    </div>
@endif

@push('scripts')
<script>
(function () {
    const form = document.getElementById('bulkForm');
    if (!form) return;

    const totalEl = document.getElementById('liveTotal');
    const rows    = form.querySelectorAll('tbody tr');

    function recalc() {
        let sum = 0;
        rows.forEach(tr => {
            const skip   = tr.querySelector('.row-skip')?.checked;
            const amtInp = tr.querySelector('.row-amount');
            if (!skip && amtInp && !amtInp.disabled) {
                const v = parseFloat(amtInp.value) || 0;
                sum += v;
            }
        });
        totalEl.textContent = sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);

    document.getElementById('fillAll')?.addEventListener('click', () => {
        rows.forEach(tr => {
            const balCell = tr.querySelector('[data-balance]');
            const amt = tr.querySelector('.row-amount');
            const skip = tr.querySelector('.row-skip');
            if (!amt || amt.disabled) return;
            const bal = parseFloat(balCell?.dataset.balance || 0);
            amt.value = bal > 0 ? bal.toFixed(2) : '';
            if (skip) skip.checked = false;
        });
        recalc();
    });

    document.getElementById('clearAll')?.addEventListener('click', () => {
        rows.forEach(tr => {
            const amt = tr.querySelector('.row-amount');
            if (amt && !amt.disabled) amt.value = '';
        });
        recalc();
    });

    document.getElementById('skipMissing')?.addEventListener('click', () => {
        rows.forEach(tr => {
            const amt = tr.querySelector('.row-amount');
            const skip = tr.querySelector('.row-skip');
            if (!amt || amt.disabled || !skip) return;
            const v = parseFloat(amt.value) || 0;
            if (v <= 0) { skip.checked = true; }
        });
        recalc();
    });

    // Block double-submit
    form.addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader me-1"></i>Recording…'; }
    });

    recalc();
})();
</script>
@endpush
@endsection
