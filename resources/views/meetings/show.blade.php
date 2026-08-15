@extends('layouts.app')
@section('title', $meeting->meeting_date->format('M j, Y').' meeting')
@section('content')

@php
    $cur     = $meeting->group->currency;
    $isOpen  = $meeting->isOpen();
    $canEdit = auth()->user()->can('update', $meeting);
    $canPay  = auth()->user()->can('recordPayment', $meeting);
@endphp

<x-page_header
    :pretitle="$meeting->group->name.' · Attendance'"
    :title="$meeting->title ?: $meeting->meeting_date->format('D · M j, Y')">
    <x-slot name="actions">
        @if($canEdit)
            <form method="POST" action="{{ route('meetings.toggle', $meeting) }}" class="d-inline">
                @csrf
                <button class="btn {{ $isOpen ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                    <i class="ti ti-{{ $isOpen ? 'lock' : 'lock-open' }} me-1"></i>
                    {{ $isOpen ? 'Close meeting' : 'Reopen meeting' }}
                </button>
            </form>
        @endif
        @can('delete', $meeting)
            <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" class="d-inline"
                  onsubmit="return confirm('Delete this meeting and its attendance? This cannot be undone.')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i> Delete</button>
            </form>
        @endcan
        <a class="btn btn-link" href="{{ route('meetings.index') }}">
            <i class="ti ti-arrow-left me-1"></i> All meetings
        </a>
    </x-slot>
</x-page_header>

@if(session('status'))<div class="alert alert-success mt-3">{{ session('status') }}</div>@endif
@if(session('error')) <div class="alert alert-danger  mt-3">{{ session('error')  }}</div>@endif

@php
    $present = $rows->where('status','present')->count();
    $late    = $rows->where('status','late')->count();
    $absent  = $rows->where('status','absent')->count();
    $excused = $rows->where('status','excused')->count();
    $finesT  = $rows->sum('fine_amount');
    $finesP  = $rows->sum('paid_amount');
    $finesO  = max(0, $finesT - $finesP);
@endphp

<div class="row row-cards mt-3">
    @foreach([
        ['Present', $present, 'green',  'check'],
        ['Late',    $late,    'orange', 'clock'],
        ['Absent',  $absent,  'red',    'user-x'],
        ['Excused', $excused, 'azure',  'mood-neutral'],
    ] as [$lbl,$val,$color,$icon])
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-{{ $color }}-lt rounded p-3 me-3"><i class="ti ti-{{ $icon }} fs-2 text-{{ $color }}"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">{{ $lbl }}</div>
                    <div class="h2 mb-0">{{ $val }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row row-cards mt-1">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total fines accrued</div>
                <div class="h2 mb-0">{{ number_format($finesT, 0) }} {{ $cur }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Fines paid</div>
                <div class="h2 mb-0 text-success">{{ number_format($finesP, 0) }} {{ $cur }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Outstanding</div>
                <div class="h2 mb-0 {{ $finesO > 0 ? 'text-danger' : 'text-muted' }}">
                    {{ number_format($finesO, 0) }} {{ $cur }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Roll-call</h3>
        <div class="card-subtitle text-muted">
            Late = {{ number_format((float)$meeting->late_fine, 0) }} {{ $cur }} ·
            Absent = {{ number_format((float)$meeting->absent_fine, 0) }} {{ $cur }}
            @if($meeting->agenda)
                · <em>{{ $meeting->agenda }}</em>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('meetings.attendance', $meeting) }}">
        @csrf
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="roll-call-table">
                <thead>
                    <tr>
                        <th style="width:32px">#</th>
                        <th>Member</th>
                        <th style="width:160px">Status</th>
                        <th style="width:160px" class="text-end">Fine ({{ $cur }})</th>
                        <th style="width:140px" class="text-end">Paid</th>
                        <th style="width:140px" class="text-end">Outstanding</th>
                        <th>Notes</th>
                        @if($canPay)<th style="width:120px"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $a)
                    @php
                        $out = max(0, (float)$a->fine_amount - (float)$a->paid_amount);
                        $color = \App\Models\MeetingAttendance::STATUS_COLORS[$a->status] ?? 'secondary';
                    @endphp
                    <tr data-row data-late-fine="{{ (float)$meeting->late_fine }}"
                                  data-absent-fine="{{ (float)$meeting->absent_fine }}"
                                  data-paid="{{ (float)$a->paid_amount }}">
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                @if($a->member->photo_path)
                                    <span class="avatar avatar-sm me-2"
                                          style="background-image: url('{{ \App\Models\SystemSetting::publicUrl('/storage/'.$a->member->photo_path) }}')"></span>
                                @else
                                    <span class="avatar avatar-sm me-2 bg-blue-lt">
                                        {{ strtoupper(substr($a->member->first_name,0,1).substr($a->member->last_name,0,1)) }}
                                    </span>
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $a->member->full_name }}</div>
                                    <div class="text-muted small">
                                        {{ $a->member->member_no }} · {{ $a->member->phone }}
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $a->id }}">
                        </td>
                        <td>
                            <select name="rows[{{ $i }}][status]" class="form-select form-select-sm status-select"
                                    {{ $isOpen && $canEdit ? '' : 'disabled' }}>
                                @foreach(\App\Models\MeetingAttendance::STATUSES as $k => $label)
                                    <option value="{{ $k }}" @selected($a->status === $k)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-end">
                            <input type="number" min="0" step="0.01"
                                   name="rows[{{ $i }}][fine_override]"
                                   class="form-control form-control-sm text-end fine-input"
                                   value="{{ rtrim(rtrim(number_format((float)$a->fine_amount, 2, '.', ''), '0'), '.') ?: '0' }}"
                                   {{ $isOpen && $canEdit ? '' : 'disabled' }}>
                        </td>
                        <td class="text-end text-success">
                            {{ number_format((float)$a->paid_amount, 0) }}
                            @if($a->paid_on)<div class="text-muted small">on {{ $a->paid_on->format('M j') }}</div>@endif
                        </td>
                        <td class="text-end {{ $out > 0 ? 'text-danger fw-semibold' : 'text-muted' }}"
                            data-outstanding>
                            {{ number_format($out, 0) }}
                        </td>
                        <td>
                            <input type="text" name="rows[{{ $i }}][notes]"
                                   class="form-control form-control-sm"
                                   value="{{ $a->notes }}"
                                   placeholder="reason / note"
                                   {{ $isOpen && $canEdit ? '' : 'disabled' }}>
                        </td>
                        @if($canPay)
                        <td class="text-end">
                            @if($out > 0)
                                <button type="button" class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#pay-{{ $a->id }}">
                                    <i class="ti ti-cash"></i> Pay
                                </button>
                            @elseif((float)$a->fine_amount > 0)
                                <span class="badge bg-success-lt">Paid</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($isOpen && $canEdit)
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Switching a row to <span class="badge bg-orange-lt">Late</span> or
                <span class="badge bg-red-lt">Absent</span> auto-fills the fine.
                Edit the amount to override.
            </div>
            <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Save attendance</button>
        </div>
        @else
        <div class="card-footer text-muted small">
            @if(! $isOpen)
                This meeting is closed. Reopen it to edit attendance.
            @else
                You don't have permission to edit attendance for this meeting.
            @endif
        </div>
        @endif
    </form>
</div>

{{-- Fine-payment modals --}}
@if($canPay)
    @foreach($rows as $a)
        @php $out = max(0, (float)$a->fine_amount - (float)$a->paid_amount); @endphp
        @if($out > 0)
        <div class="modal modal-blur fade" id="pay-{{ $a->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('meetings.attendance.pay', [$meeting, $a]) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Pay attendance fine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="text-muted small">Member</div>
                            <div class="fw-semibold">{{ $a->member->full_name }} ({{ $a->member->member_no }})</div>
                        </div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label required">Amount ({{ $cur }})</label>
                                <input type="number" min="0.01" step="0.01" name="amount" class="form-control"
                                       value="{{ rtrim(rtrim(number_format($out, 2, '.', ''), '0'), '.') ?: '0' }}"
                                       max="{{ $out }}" required>
                                <div class="form-hint">Outstanding: {{ number_format($out, 0) }} {{ $cur }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Method</label>
                                <select name="method" class="form-select" required>
                                    <option value="cash">Cash</option>
                                    <option value="mobile_money">Mobile money</option>
                                    <option value="bank">Bank</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Paid on</label>
                                <input type="date" name="paid_on" class="form-control"
                                       value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" maxlength="255">
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0 small">
                            This payment is also recorded in the cashbook as
                            <strong>income · attendance fine</strong>, so it shows up in treasury.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-success"><i class="ti ti-cash me-1"></i> Record payment</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endforeach
@endif

<script>
// When the user changes a member's status in the roll-call, auto-fill the
// fine field with the meeting default (late or absent) but never below
// what the member has already paid.
document.querySelectorAll('#roll-call-table tr[data-row]').forEach(function (row) {
    var sel  = row.querySelector('.status-select');
    var fee  = row.querySelector('.fine-input');
    var paid = parseFloat(row.dataset.paid || '0');
    var lateFine   = parseFloat(row.dataset.lateFine || '0');
    var absentFine = parseFloat(row.dataset.absentFine || '0');
    if (! sel || ! fee) return;
    sel.addEventListener('change', function () {
        var val = 0;
        if (sel.value === 'late')   val = lateFine;
        if (sel.value === 'absent') val = absentFine;
        if (val < paid) val = paid;
        fee.value = val;
        var outCell = row.querySelector('[data-outstanding]');
        if (outCell) {
            var out = Math.max(0, val - paid);
            outCell.textContent = out.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    });
});
</script>
@endsection
