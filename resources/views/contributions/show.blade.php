@extends('layouts.app')
@section('title', 'Contribution #'.$c->id)
@section('content')

<x-page_header :title="'Contribution #'.$c->id" :pretitle="$c->group?->name">
    <x-slot name="actions">
        @hasanyrole('super_admin|group_admin|treasurer')
        <form method="POST" action="{{ route('arrears.run') }}" class="d-inline">
            @csrf
            <input type="hidden" name="group_id" value="{{ $c->group_id }}">
            <button type="submit" class="btn btn-outline-orange" title="Recalculate late fees and interest for this group">
                <i class="ti ti-calculator me-1"></i>Calculate interest
            </button>
        </form>
        @endhasanyrole
        @can('create', App\Models\Contribution::class)
            @if(in_array($c->status, ['pending','partial','overdue']))
            <a href="{{ route('payments.create', ['contribution_id' => $c->id]) }}" class="btn btn-primary"><i class="ti ti-cash me-1"></i>Record payment</a>
            @endif
        @endcan
        @can('update', $c)
        <form method="POST" action="{{ route('contributions.waive', $c) }}" class="d-inline" onsubmit="return confirm('Waive this contribution?')">@csrf
            <button class="btn btn-outline-warning"><i class="ti ti-circle-off me-1"></i>Waive</button>
        </form>
        @endcan
    </x-slot>
</x-page_header>

<div class="row row-cards mt-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">{{ $c->member?->full_name }} <small class="text-muted">{{ $c->member?->member_no }}</small></h3>
                <div class="datagrid">
                    <div class="datagrid-item"><div class="datagrid-title">Type</div><div class="datagrid-content">{{ ucfirst(str_replace('_',' ',$c->type)) }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Status</div><div class="datagrid-content">@include('contributions._status', ['status' => $c->status])</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Period</div><div class="datagrid-content">{{ $c->period_start->format('Y-m-d') }} → {{ $c->period_end->format('Y-m-d') }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Due on</div><div class="datagrid-content">{{ $c->due_on->format('Y-m-d') }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Expected</div><div class="datagrid-content">{{ number_format($c->expected_amount, 0) }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Paid</div><div class="datagrid-content">{{ number_format($c->paid_amount, 0) }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Late fee</div><div class="datagrid-content">{{ number_format($c->late_fee_amount, 0) }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Balance</div><div class="datagrid-content"><strong>{{ number_format($c->balance(), 0) }}</strong></div></div>
                </div>
                @if($c->notes)<hr><div class="text-muted small">{{ $c->notes }}</div>@endif
            </div>
        </div>

        @if($c->arrear)
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title text-red">Arrear</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item"><div class="datagrid-title">Days overdue</div><div class="datagrid-content">{{ $c->arrear->days_overdue }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Outstanding</div><div class="datagrid-content"><strong>{{ number_format($c->arrear->outstanding_amount, 0) }}</strong></div></div>
                    <div class="datagrid-item"><div class="datagrid-title">Late fees applied</div><div class="datagrid-content">{{ number_format($c->arrear->late_fee_applied, 0) }}</div></div>
                    <div class="datagrid-item"><div class="datagrid-title">First overdue</div><div class="datagrid-content">{{ $c->arrear->first_overdue_on?->format('Y-m-d') }}</div></div>
                </div>
            </div>
        </div>
        @endif

        {{-- Penalty schedule: past charges + projected future growth --}}
        @if(!empty($penaltySchedule))
        @php $isCompound = (bool) $c->group->rule('penalty_on_penalty', false); @endphp
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-chart-line me-1 text-orange"></i>Penalty schedule
                </h3>
                <div class="card-options">
                    @if($isCompound)
                        <span class="badge bg-orange text-white">Compound — interest on interest</span>
                    @else
                        <span class="badge bg-secondary">Standard — flat per period</span>
                    @endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter table-sm">
                    <thead>
                        <tr>
                            <th class="text-muted w-8">#</th>
                            <th>Applies from</th>
                            <th class="text-end">Fee (compound)</th>
                            @if($isCompound)
                            <th class="text-end text-muted">Fee (flat equiv.)</th>
                            <th class="text-end text-orange">Extra</th>
                            @endif
                            <th class="text-end">Cumulative</th>
                            <th class="text-end">Total owed</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($penaltySchedule as $row)
                    <tr class="{{ $row['is_current'] ? 'table-warning' : ($row['is_future'] ? 'text-muted' : '') }}">
                        <td class="text-muted">{{ $row['n'] }}</td>
                        <td>{{ $row['from']->format('d M Y') }}</td>
                        <td class="text-end {{ $row['is_future'] ? 'text-muted' : 'text-orange fw-semibold' }}">
                            +{{ number_format($row['fee'], 0) }}
                        </td>
                        @if($isCompound)
                        <td class="text-end text-muted">
                            +{{ number_format($row['flat_fee'], 0) }}
                        </td>
                        <td class="text-end">
                            @if(($row['compound_extra'] ?? 0) > 0)
                                <span class="fw-semibold text-orange">+{{ number_format($row['compound_extra'], 0) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endif
                        <td class="text-end">{{ number_format($row['total_fee'], 0) }}</td>
                        <td class="text-end fw-bold {{ $row['is_future'] ? 'text-muted' : '' }}">
                            {{ number_format($row['total_owed'], 0) }}
                        </td>
                        <td>
                            @if($row['is_future'])
                                <span class="badge bg-blue-lt text-blue">projected</span>
                            @elseif($row['is_charged'])
                                <span class="badge bg-green-lt text-green">charged</span>
                            @else
                                <span class="badge bg-orange-lt text-orange">pending</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($isCompound)
            <div class="card-footer text-muted small">
                <i class="ti ti-info-circle me-1"></i>
                Each period's fee is charged on the original amount <strong>plus all prior unpaid penalties</strong>,
                compounding at {{ $c->group->rule('late_fee_pct', config('vsla.late_fee_pct')) }}% per period.
                Pay sooner to avoid the growing balance.
            </div>
            @endif
        </div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Payments</h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th class="text-end">Amount</th><th>Received by</th></tr></thead>
                    <tbody>
                    @forelse($c->payments as $p)
                    <tr>
                        <td><a href="{{ route('payments.show', $p) }}">{{ $p->reference }}</a></td>
                        <td>{{ $p->paid_on->format('Y-m-d') }}</td>
                        <td>{{ str_replace('_',' ',$p->method) }}</td>
                        <td class="text-end">{{ number_format($p->amount, 0) }}</td>
                        <td class="text-muted">{{ $p->receiver?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted text-center">No payments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payment request form (members) or quick-pay form (admins) --}}
        @if(in_array($c->status, ['pending','partial','overdue']))
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">
                    @if(auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
                        <i class="ti ti-cash me-1 text-green"></i>Record Payment for this Contribution
                    @else
                        <i class="ti ti-clock me-1 text-yellow"></i>Submit Payment Request
                    @endif
                </h3>
            </div>
            <div class="card-body">
                @if(auth()->user()->hasRole('member'))
                    @php $hasPending = $c->paymentRequests->where('status','pending_review')->count() > 0; @endphp
                    @if($hasPending)
                        <div class="alert alert-warning">
                            <i class="ti ti-clock me-1"></i>You already have a pending payment request for this contribution. Please wait for approval.
                        </div>
                    @else
                    <p class="text-muted small mb-3">Submit your payment details and the group admin will verify and approve it.</p>
                    @endif
                @endif

                @if(!auth()->user()->hasRole('member') || !isset($hasPending) || !$hasPending)
                <form method="POST" action="{{ route('payment-requests.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="contribution_id" value="{{ $c->id }}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Amount <span class="text-red">*</span>
                                <small class="text-muted">(balance: {{ number_format($c->balance(), 0) }})</small>
                            </label>
                            <input type="number" name="amount" class="form-control" step="0.01"
                                   min="0.01" max="{{ $c->balance() }}"
                                   value="{{ old('amount', number_format($c->balance(), 2, '.', '')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment method <span class="text-red">*</span></label>
                            <select name="method" class="form-select" required>
                                @foreach(['cash'=>'Cash','mobile_money'=>'Mobile Money','bank'=>'Bank','cheque'=>'Cheque','other'=>'Other'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('method','mobile_money')===$val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Payment date <span class="text-red">*</span></label>
                            <input type="date" name="paid_on" class="form-control"
                                   value="{{ old('paid_on', date('Y-m-d')) }}"
                                   max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Transaction ref</label>
                            <input type="text" name="channel_ref" class="form-control"
                                   placeholder="e.g. MOMO-XXXX"
                                   value="{{ old('channel_ref') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control"
                                   placeholder="Optional note"
                                   value="{{ old('notes') }}">
                        </div>

                        {{-- Proof of payment upload --}}
                        <div class="col-12">
                            <label class="form-label">
                                Proof of payment
                                <span class="text-muted small ms-1">(photo of receipt, screenshot, or PDF — max 5 MB)</span>
                            </label>
                            <div id="proofDropzone"
                                 class="border rounded p-3 text-center position-relative"
                                 style="border-style:dashed!important; cursor:pointer; min-height:90px; transition:background .15s"
                                 onclick="document.getElementById('proofFileInput').click()">
                                <i class="ti ti-upload text-muted" style="font-size:1.8rem"></i>
                                <div class="text-muted small mt-1" id="proofLabel">Click or drag &amp; drop a file here</div>
                                <img id="proofPreviewImg" src="" alt="preview"
                                     class="mt-2 rounded d-none"
                                     style="max-height:160px; max-width:100%; object-fit:contain">
                                <div id="proofPreviewFile" class="mt-2 d-none">
                                    <i class="ti ti-file-text text-blue" style="font-size:2rem"></i>
                                    <div id="proofFileName" class="text-muted small mt-1"></div>
                                </div>
                            </div>
                            <input type="file" id="proofFileInput" name="proof_file"
                                   accept="image/*,.pdf" class="d-none">
                            @error('proof_file')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary">
                                @if(auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
                                    <i class="ti ti-cash me-1"></i>Record Payment
                                @else
                                    <i class="ti ti-send me-1"></i>Submit for Approval
                                @endif
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                (function(){
                    const input   = document.getElementById('proofFileInput');
                    const zone    = document.getElementById('proofDropzone');
                    const label   = document.getElementById('proofLabel');
                    const prevImg = document.getElementById('proofPreviewImg');
                    const prevFile= document.getElementById('proofPreviewFile');
                    const prevName= document.getElementById('proofFileName');

                    function handleFile(file) {
                        if (!file) return;
                        label.textContent = '';
                        if (file.type.startsWith('image/')) {
                            prevFile.classList.add('d-none');
                            const reader = new FileReader();
                            reader.onload = e => { prevImg.src = e.target.result; prevImg.classList.remove('d-none'); };
                            reader.readAsDataURL(file);
                        } else {
                            prevImg.classList.add('d-none');
                            prevName.textContent = file.name;
                            prevFile.classList.remove('d-none');
                        }
                        zone.style.background = '#e8f4fd';
                    }

                    input.addEventListener('change', () => handleFile(input.files[0]));

                    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.style.background='#e8f4fd'; });
                    zone.addEventListener('dragleave', ()  => { zone.style.background=''; });
                    zone.addEventListener('drop', e => {
                        e.preventDefault();
                        const file = e.dataTransfer.files[0];
                        if (file) { input.files = e.dataTransfer.files; handleFile(file); }
                    });
                })();
                </script>
                @endif
            </div>
        </div>
        @endif

        {{-- Show this member's past requests --}}
        @if($c->paymentRequests->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Payment Requests</h3></div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter table-sm">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Ref</th><th>Status</th><th>Note</th></tr></thead>
                    <tbody>
                    @foreach($c->paymentRequests as $pr)
                    <tr>
                        <td>{{ $pr->created_at->format('Y-m-d') }}</td>
                        <td>{{ number_format($pr->amount, 0) }}</td>
                        <td>{{ ucfirst(str_replace('_',' ',$pr->method)) }}</td>
                        <td class="text-muted small">{{ $pr->channel_ref ?: '—' }}</td>
                        <td>
                            @if($pr->isPending())
                                <span class="badge bg-yellow text-dark">Pending</span>
                            @elseif($pr->isApproved())
                                <span class="badge bg-green">Approved</span>
                            @else
                                <span class="badge bg-red" title="{{ $pr->rejection_reason }}">Rejected</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $pr->rejection_reason ? Str::limit($pr->rejection_reason,40) : '—' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
