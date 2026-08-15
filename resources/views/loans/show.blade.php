@extends('layouts.app')
@section('title', 'Loan '.$loan->reference)
@section('content')

@push('head')
<style>
@media (max-width: 767.98px) {
    .member-reorder { display: contents; }
    .member-reorder > .col-lg-7,
    .member-reorder > .col-lg-5 { display: contents; }
    .member-reorder #card-summary { order: 1; }
    .member-reorder #card-statement { order: 2; }
    .member-reorder #card-repayment-form { order: 3; }
    .member-reorder #card-pending { order: 4; }
    .member-reorder #card-repayments-detail { order: 5; }
    .member-reorder #card-member { order: 6; }
}
</style>
@endpush

@php
    $u = auth()->user();
    $canDecide = $u->can('decide', $loan);
    $canRecord = $u->can('record', $loan);
    $isCompound = $loan->isCompound();
    $accruals = $isCompound ? $loan->accruals : collect();
    $unpaidInterest = $isCompound ? $loan->unpaidAccruedInterest() : 0;
    $minPayment = $isCompound ? $loan->monthlyInterestDue() : 0;

    // Remaining interest / principal for the partial-payment breakdown panel
    if ($isCompound) {
        $remainingInterest  = $unpaidInterest;
        $remainingPrincipal = max(0, (float) $loan->outstanding - $unpaidInterest);
    } else {
        $paidInterest       = (float) $loan->approvedRepayments()->sum('interest_portion');
        $remainingInterest  = max(0, (float) $loan->total_interest - $paidInterest);
        $paidPrincipal      = (float) $loan->approvedRepayments()->sum('principal_portion');
        $remainingPrincipal = max(0, (float) $loan->principal - $paidPrincipal);
    }
    $currency = $loan->group->currency ?? '';
    $isMemberOnly = $u->hasRole('member') && ! $u->hasAnyRole(['super_admin','group_admin','treasurer','secretary']);
@endphp

<x-page_header :title="'Loan '.$loan->reference" :pretitle="$loan->member->full_name.' · '.$loan->group->name">
    <x-slot name="actions">
        <a href="{{ route('loans.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back</a>
    </x-slot>
</x-page_header>

<div class="row row-cards mt-3 @if($isMemberOnly) member-reorder @endif">

    {{-- Summary --}}
    <div class="col-lg-7">
        <div class="card" id="card-summary">
            <div class="card-header">
                <h3 class="card-title">Summary</h3>
                <div class="ms-auto d-flex align-items-center gap-2">
                    @if($isCompound)
                        <span class="badge bg-purple-lt">Compound interest</span>
                    @else
                        <span class="badge bg-secondary-lt">Flat interest</span>
                    @endif
                    <span class="badge bg-{{ $loan->statusBadge() }}-lt fs-3">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span>
                    @if($isCompound && auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
                        <form method="POST" action="{{ route('loans.recalculate', $loan) }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                    title="Rebuild outstanding balance from accruals"
                                    onclick="return confirm('Recalculate outstanding balance from accruals table?')">
                                <i class="ti ti-refresh me-1"></i>Recalculate balance
                            </button>
                        </form>
                    @endif
                    @if(!$isCompound && $loan->isOpen() && $loan->due_on && $loan->due_on->lt(now()) && (float)$loan->outstanding > 0 && auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
                        <form method="POST" action="{{ route('loans.apply.interest') }}" class="m-0"
                              onsubmit="return confirm('Apply interest penalty to overdue loans in this group?')">
                            @csrf
                            <input type="hidden" name="group_id" value="{{ $loan->group_id }}">
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="ti ti-percentage me-1"></i>Apply interest penalty
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Principal</div>
                        <div class="h3 mb-0">{{ number_format($loan->principal, 0) }} {{ $loan->group->currency }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Monthly rate</div>
                        <div class="h4 mb-0">{{ rtrim(rtrim(number_format($loan->interest_rate_pct, 3), '0'), '.') }}% / mo</div>
                    </div>

                    @if($isCompound)
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Total interest accrued</div>
                        <div class="h4 mb-0">{{ number_format($loan->total_interest, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small text-danger">Unpaid interest</div>
                        <div class="h4 mb-0 {{ $unpaidInterest > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($unpaidInterest, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Total paid</div>
                        <div class="h3 mb-0 text-success">{{ number_format($loan->amount_repaid, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small fw-bold">Outstanding balance</div>
                        <div class="h3 mb-0 text-{{ $loan->outstanding > 0 ? 'red' : 'muted' }}">{{ number_format($loan->outstanding, 0) }}</div>
                    </div>
                    @else
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Rate / Term</div>
                        <div class="h4 mb-0">{{ rtrim(rtrim(number_format($loan->interest_rate_pct, 3), '0'), '.') }}% × {{ $loan->term_months ? $loan->term_months.' mo' : '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Total interest</div>
                        <div class="h4 mb-0">{{ number_format($loan->total_interest, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Total repayable</div>
                        <div class="h3 mb-0">{{ number_format($loan->total_repayable, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Repaid</div>
                        <div class="h3 mb-0 text-success">{{ number_format($loan->amount_repaid, 0) }}</div>
                    </div>
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small">Outstanding</div>
                        <div class="h3 mb-0 text-{{ $loan->outstanding > 0 ? 'red' : 'muted' }}">{{ number_format($loan->outstanding, 0) }}</div>
                    </div>
                    @if((float)$loan->late_fee_amount > 0)
                    <div class="col-6 col-md-4 mb-3">
                        <div class="text-muted small text-orange">Late penalty fees</div>
                        <div class="h4 mb-0 text-orange">{{ number_format($loan->late_fee_amount, 0) }}</div>
                    </div>
                    @endif
                    @endif
                </div>

                @if($isCompound && $loan->isOpen())
                <div class="alert alert-warning d-flex align-items-center mt-2 mb-0 py-2">
                    <i class="ti ti-info-circle me-2 fs-3"></i>
                    <div>
                        <strong>Next month's interest:</strong>
                        {{ number_format($minPayment, 0) }} {{ $loan->group->currency }}
                        ({{ rtrim(rtrim(number_format($loan->interest_rate_pct,3),'0'),'.') }}% of {{ number_format($loan->outstanding, 0) }}).
                        <br class="d-none d-md-block">
                        Paying at least this amount each month keeps the balance from growing.
                    </div>
                </div>
                @endif

                <hr>
                <div class="row text-muted small">
                    <div class="col-md-3"><strong>Requested</strong><br>{{ $loan->requested_on?->format('d M Y') }}</div>
                    <div class="col-md-3"><strong>Approved</strong><br>{{ $loan->approved_on?->format('d M Y') ?? '—' }} {{ $loan->approver?->name ? '· '.$loan->approver->name : '' }}</div>
                    <div class="col-md-3"><strong>Disbursed</strong><br>{{ $loan->disbursed_on?->format('d M Y') ?? '—' }}</div>
                    <div class="col-md-3"><strong>Due on</strong><br>{{ $loan->due_on?->format('d M Y') ?? ($isCompound ? 'Open-ended' : '—') }}</div>
                </div>

                @if($loan->prior_outstanding > 0)
                    @php
                        $originalPrincipal = round((float)$loan->principal - (float)$loan->prior_outstanding, 2);
                    @endphp
                    <div class="alert alert-info d-flex align-items-center mt-2 mb-0 py-2">
                        <i class="ti ti-arrows-join me-2 fs-3"></i>
                        <div>
                            <strong>Combined loan</strong>
                            @if($loan->consolidated_loan_ids)
                                — includes {{ implode(', ', array_map(fn($id) => 'L-'.str_pad($id,5,'0',STR_PAD_LEFT), $loan->consolidated_loan_ids)) }}
                            @endif
                            <br>
                            <span class="text-muted small">
                                New funds: <strong>{{ number_format($originalPrincipal, 0) }}</strong>
                                + prior outstanding: <strong>{{ number_format((float)$loan->prior_outstanding, 0) }}</strong>
                                = combined principal: <strong>{{ number_format((float)$loan->principal, 0) }}</strong>
                            </span>
                        </div>
                    </div>
                @endif

                @if($loan->purpose)
                    <hr>
                    <div><strong>Purpose:</strong> {{ $loan->purpose }}</div>
                @endif
                @if($loan->rejection_reason)
                    <div class="mt-2 text-red"><strong>Rejected:</strong> {{ $loan->rejection_reason }}</div>
                @endif
            </div>
        </div>

        {{-- Loan Statement (unified chronological ledger) --}}
        @if($ledger->isNotEmpty())
        <div class="card mt-3" id="card-statement">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-list-details me-1 text-blue"></i>Loan statement</h3>
                <span class="ms-auto text-muted small">chronological ledger</span>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter table-sm">
                    <thead>
                        <tr class="table-light">
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-end">Capital</th>
                            <th class="text-end text-danger">Interest added</th>
                            <th class="text-end text-success">Payment</th>
                            <th class="text-end fw-bold">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($ledger as $row)
                        @php
                            $rowClass = match($row['type']) {
                                'disbursement' => 'table-primary',
                                'rollover'     => 'table-warning',
                                'accrual'      => '',
                                'flat_interest'=> 'table-danger-lt',
                                'repayment'    => 'table-success-lt',
                                default        => '',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="text-nowrap small">
                                @if($row['type'] === 'accrual')
                                    <span class="text-muted">
                                        {{ $row['period_start']->format('d M') }}
                                        –
                                        {{ $row['period_end']->format('d M Y') }}
                                    </span>
                                @else
                                    {{ $row['date']->format('d M Y') }}
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $row['description'] }}</span>
                                @if($row['meta'])
                                    <span class="text-muted small ms-1">· {{ $row['meta'] }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($row['type'] === 'rollover')
                                    <span class="text-warning fw-semibold">+{{ number_format($row['capital'], 0) }}</span>
                                @else
                                    {{ $row['capital'] > 0 ? number_format($row['capital'], 0) : '—' }}
                                @endif
                            </td>
                            <td class="text-end text-danger fw-semibold">
                                {{ $row['interest'] > 0 ? '+'.number_format($row['interest'], 0) : '0' }}
                            </td>
                            <td class="text-end text-success fw-semibold">
                                {{ $row['payment'] > 0 ? number_format($row['payment'], 0) : '0' }}
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format($row['balance'], 0) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-muted small">Totals</td>
                            <td class="text-end fw-bold text-danger">
                                +{{ number_format($ledger->sum('interest'), 0) }}
                            </td>
                            <td class="text-end fw-bold text-success">
                                {{ number_format($ledger->sum('payment'), 0) }}
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format($ledger->last()['balance'] ?? 0, 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{-- Repayments detail (principal / interest split per approved payment) --}}
        @php $approvedRepayments = $loan->repayments->where('status','approved'); @endphp
        @if($approvedRepayments->isNotEmpty())
        <div class="card mt-3" id="card-repayments-detail">
            <div class="card-header">
                <h3 class="card-title">Repayments — principal / interest split</h3>
            </div>
            <div class="table-responsive">
                <table class="table card-table table-vcenter table-sm">
                    <thead><tr>
                        <th>Date</th><th>Type</th><th>Method</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Interest</th>
                        <th>By</th>
                        <th>Proof</th>
                        @if(auth()->user()->hasAnyRole(['super_admin','group_admin']))<th></th>@endif
                    </tr></thead>
                    <tbody>
                    @foreach($approvedRepayments->sortBy('paid_on') as $r)
                        <tr>
                            <td class="small">{{ $r->paid_on?->format('d M Y') }}</td>
                            <td><span class="badge bg-secondary-lt small">{{ $r->paymentTypeLabel() }}</span></td>
                            <td>{{ str_replace('_',' ',$r->method) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r->amount, 0) }}</td>
                            <td class="text-end text-blue">{{ number_format($r->principal_portion, 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($r->interest_portion, 0) }}</td>
                            <td class="text-muted small">{{ $r->recorder?->name }}</td>
                            <td>
                                @if($r->proof_file)
                                    @php $pUrl = Storage::disk('public')->url($r->proof_file); $pExt = strtolower(pathinfo($r->proof_file, PATHINFO_EXTENSION)); @endphp
                                    @if(in_array($pExt, ['jpg','jpeg','png','webp']))
                                        <a href="{{ $pUrl }}" target="_blank">
                                            <img src="{{ $pUrl }}" alt="Proof"
                                                 style="height:32px;width:48px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                                        </a>
                                    @else
                                        <a href="{{ $pUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-1">
                                            <i class="ti ti-file-description"></i>
                                        </a>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @if(auth()->user()->hasAnyRole(['super_admin','group_admin']))
                            <td>
                                <form method="POST"
                                      action="{{ route('loans.repayments.destroy', [$loan, $r]) }}"
                                      onsubmit="return confirm('Delete this repayment of {{ number_format($r->amount, 0) }}? The loan balance will be reversed.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ghost-danger py-0 px-1" title="Delete repayment">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-muted small">Totals</td>
                            <td class="text-end fw-bold">{{ number_format($approvedRepayments->sum('amount'), 0) }}</td>
                            <td class="text-end fw-bold text-blue">{{ number_format($approvedRepayments->sum('principal_portion'), 0) }}</td>
                            <td class="text-end fw-bold text-danger">{{ number_format($approvedRepayments->sum('interest_portion'), 0) }}</td>
                            <td></td>
                            <td></td>
                            @if(auth()->user()->hasAnyRole(['super_admin','group_admin']))<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @else
        <div class="card mt-3" id="card-repayments-detail">
            <div class="card-header"><h3 class="card-title">Repayments</h3></div>
            <div class="card-body text-center text-muted py-4">No repayments recorded yet.</div>
        </div>
        @endif
    </div>

    {{-- Action panel --}}
    <div class="col-lg-5">

        @if($canDecide && $loan->status === 'requested')
        <div class="card">
            <div class="card-header"><h3 class="card-title">Review request</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('loans.approve', $loan) }}" class="d-inline">@csrf
                    <button class="btn btn-success"><i class="ti ti-check me-1"></i>Approve</button>
                </form>
                <button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-form">
                    <i class="ti ti-x me-1"></i>Reject…
                </button>
                <form method="POST" action="{{ route('loans.reject', $loan) }}" class="collapse mt-3" id="reject-form">@csrf
                    <textarea name="rejection_reason" class="form-control mb-2" placeholder="Reason (optional)" rows="2"></textarea>
                    <button class="btn btn-danger btn-sm">Confirm reject</button>
                </form>
            </div>
        </div>
        @endif

        @if($canDecide && $loan->status === 'approved')
        <div class="card">
            <div class="card-header"><h3 class="card-title">Disburse</h3></div>
            <form method="POST" action="{{ route('loans.disburse', $loan) }}" class="card-body">@csrf
                <label class="form-label">Disbursement date</label>
                <input type="date" name="disbursed_on" value="{{ now()->toDateString() }}" class="form-control mb-2" required>

                @if($isCompound && $consolidationCandidates->isNotEmpty())
                @php $rollTotal = $consolidationCandidates->sum(fn($l) => (float)$l->outstanding); @endphp
                <div class="alert alert-warning mb-2 py-2">
                    <div class="fw-semibold mb-1"><i class="ti ti-arrows-join me-1"></i>This member has outstanding loan(s) — combine into this loan?</div>
                    <div class="small text-muted mb-2">Tick the loans to merge. Their full outstanding (principal + interest) will be added to this loan's principal. Old loans will be closed.</div>
                    @foreach($consolidationCandidates as $cl)
                    <label class="form-check mb-1">
                        <input class="form-check-input consolidate-cb" type="checkbox"
                               name="consolidate_loan_ids[]" value="{{ $cl->id }}"
                               data-outstanding="{{ $cl->outstanding }}" checked>
                        <span class="form-check-label">
                            <strong>{{ $cl->reference }}</strong>
                            · outstanding <strong>{{ number_format((float)$cl->outstanding, 0) }}</strong>
                            <span class="text-muted">(disbursed {{ $cl->disbursed_on?->format('d M Y') }})</span>
                        </span>
                    </label>
                    @endforeach
                    <div class="mt-1 small">
                        Combined principal:
                        <strong>{{ number_format((float)$loan->principal, 0) }}</strong>
                        + <span id="rollover-sum">{{ number_format($rollTotal, 0) }}</span>
                        = <strong id="combined-total">{{ number_format((float)$loan->principal + $rollTotal, 0) }}</strong>
                    </div>
                </div>
                @endif

                <button class="btn btn-primary"><i class="ti ti-send me-1"></i>Mark as disbursed</button>
            </form>
        </div>
        @endif

        {{-- Edit disbursement date (admins only, active loans) --}}
        @if(in_array($loan->status, ['disbursed','repaying']) && auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar-event me-1 text-blue"></i>Change disbursement date</h3>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-ghost-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#edit-disburse-form">
                        <i class="ti ti-edit me-1"></i>Edit
                    </button>
                </div>
            </div>
            <div class="collapse" id="edit-disburse-form">
                <form method="POST" action="{{ route('loans.updateDisbursedOn', $loan) }}" class="card-body pt-0">
                    @csrf
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="ti ti-alert-triangle me-1"></i>
                        Changing this date <strong>does not</strong> recalculate past interest accruals —
                        use this only to correct a data-entry error.
                    </div>
                    <label class="form-label required">Disbursement date</label>
                    <div class="input-group">
                        <input type="date" name="disbursed_on"
                               value="{{ $loan->disbursed_on?->toDateString() }}"
                               class="form-control" required>
                        <button class="btn btn-primary">
                            <i class="ti ti-check me-1"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        @if($canRecord && in_array($loan->status, ['disbursed','repaying']))
        @php
            $isMemberRole = $isMemberOnly;
        @endphp
        <div class="card" id="card-repayment-form">
            <div class="card-header">
                <h3 class="card-title">
                    @if($isMemberRole)
                        <i class="ti ti-send me-1 text-yellow"></i>Submit repayment
                    @else
                        <i class="ti ti-receipt me-1"></i>Record repayment
                    @endif
                </h3>
                @if($isCompound && $unpaidInterest > 0)
                    <span class="ms-auto badge bg-warning-lt">Interest due: {{ number_format($unpaidInterest, 0) }}</span>
                @endif
            </div>
            @if($isMemberRole && $pendingRepayments->isNotEmpty())
            <div class="card-body">
                <div class="alert alert-warning mb-0 small">
                    <i class="ti ti-clock me-1"></i>
                    You already have a repayment waiting for approval. You can submit another payment after this one is approved or rejected.
                </div>
            </div>
            @endif
            <form method="POST" action="{{ route('loans.repayments.store', $loan) }}" class="card-body" id="repayment-form" enctype="multipart/form-data" @if($isMemberRole && $pendingRepayments->isNotEmpty()) onsubmit="return false;" @endif>@csrf

                @if($isMemberRole)
                <div class="alert alert-info py-2 mb-3 small">
                    <i class="ti ti-info-circle me-1"></i>
                    Your payment will be reviewed and approved by the group admin before it is applied to your loan.
                </div>
                @endif

                {{-- Payment type selector --}}
                <div class="mb-3">
                    <label class="form-label required">What are you paying?</label>
                    <div class="row g-2" id="payment-type-options">
                        <div class="col-6 col-md-4">
                            <label class="form-selectgroup-item w-100">
                                <input type="radio" name="payment_type" value="full" class="form-selectgroup-input" checked>
                                <span class="form-selectgroup-label text-center w-100 py-2">
                                    <i class="ti ti-coin d-block mb-1 fs-2"></i>
                                    <span class="small">Full payment</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-selectgroup-item w-100">
                                <input type="radio" name="payment_type" value="interest_only" class="form-selectgroup-input">
                                <span class="form-selectgroup-label text-center w-100 py-2">
                                    <i class="ti ti-trending-up d-block mb-1 fs-2 text-danger"></i>
                                    <span class="small">Interest only</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-selectgroup-item w-100">
                                <input type="radio" name="payment_type" value="principal_only" class="form-selectgroup-input">
                                <span class="form-selectgroup-label text-center w-100 py-2">
                                    <i class="ti ti-bank d-block mb-1 fs-2 text-blue"></i>
                                    <span class="small">Principal only</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Interest month checkboxes (shown only for interest_only, multi-select) --}}
                @if($isCompound && $accrualOptions->isNotEmpty())
                <div class="mb-3 d-none" id="accrual-period-row">
                    <label class="form-label">Which month(s) of interest?</label>
                    <div class="card card-sm border mb-1">
                        <div class="card-body py-2 px-3">
                            @forelse($accrualOptions as $opt)
                            <div class="form-check mb-1">
                                <input class="form-check-input accrual-cb"
                                       type="checkbox"
                                       value="{{ $opt['period'] }}"
                                       data-amount="{{ $opt['amount'] }}"
                                       id="acc-cb-{{ $loop->index }}">
                                <label class="form-check-label small d-flex align-items-center gap-2" for="acc-cb-{{ $loop->index }}">
                                    <span>{{ $opt['label'] }}</span>
                                    @if($opt['partial'])
                                        <span class="badge bg-warning-lt text-warning" title="Originally {{ number_format($opt['original'], 0) }} — partially paid">partial</span>
                                    @endif
                                    <strong class="text-danger ms-auto">{{ number_format($opt['amount'], 0) }} {{ $loan->group->currency }}</strong>
                                </label>
                            </div>
                            @empty
                            <div class="text-muted small py-1">
                                <i class="ti ti-circle-check text-success me-1"></i>All monthly interest has been paid.
                            </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="small text-muted" id="accrual-hint">
                        <i class="ti ti-info-circle me-1"></i>
                        Amounts shown are the <strong>remaining unpaid</strong> interest per month. Leave all unchecked to apply a free amount against total unpaid interest.
                    </div>
                    {{-- Hidden field carries the single period when exactly 1 month is checked --}}
                    <input type="hidden" name="accrual_period" id="accrual-period-hidden" value="">
                </div>
                @endif

                <div class="row g-2">
                    @if($isCompound && $unpaidInterest > 0)
                    <div class="col-12" id="interest-hint">
                        <div class="alert alert-warning py-2 mb-0 small">
                            <strong>Unpaid interest:</strong> {{ number_format($unpaidInterest, 0) }} {{ $loan->group->currency }}.
                            Paying at least this amount keeps the balance from growing.
                        </div>
                    </div>
                    @endif
                    <div class="col-12 d-none" id="principal-hint">
                        <div class="alert alert-blue py-2 mb-0 small">
                            <strong>Outstanding loan:</strong> {{ number_format($loan->outstanding, 0) }} {{ $loan->group->currency }}.
                            @if($remainingInterest > 0)
                                Remaining principal: <strong>{{ number_format($remainingPrincipal, 0) }} {{ $loan->group->currency }}</strong>
                                (excludes {{ number_format($remainingInterest, 0) }} unpaid interest).
                            @endif
                            Enter any amount up to the outstanding balance.
                        </div>
                    </div>
                    <div class="col-7">
                        <label class="form-label required">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="repayment-amount" class="form-control" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label required">Date</label>
                        <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                                <option value="{{ $m }}">{{ str_replace('_',' ',$m) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label">Ref</label>
                        <input type="text" name="reference" class="form-control" maxlength="60">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Proof of payment <span class="text-muted">(optional)</span></label>
                        <input type="file" name="proof_file" class="form-control"
                               accept="image/jpeg,image/png,image/webp,application/pdf">
                        <div class="form-text">JPG, PNG, WebP or PDF · max 5 MB</div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3 w-100" id="repayment-submit-btn" @if($isMemberRole && $pendingRepayments->isNotEmpty()) disabled @endif>
                    @if($isMemberRole && $pendingRepayments->isNotEmpty())
                        <i class="ti ti-clock me-1"></i>Awaiting approval
                    @elseif($isMemberRole)
                        <i class="ti ti-send me-1"></i>Submit for approval
                    @else
                        <i class="ti ti-receipt me-1"></i>Record repayment
                    @endif
                </button>
            </form>
        </div>
        @endif

        {{-- Pending repayments awaiting approval --}}
        @if($pendingRepayments->isNotEmpty())
        <div class="card mt-3 border-warning" id="card-pending">
            <div class="card-header bg-warning-lt">
                <h3 class="card-title text-warning">
                    <i class="ti ti-clock me-1"></i>Pending repayments
                    <span class="badge bg-warning ms-2">{{ $pendingRepayments->count() }}</span>
                </h3>
            </div>
            @foreach($pendingRepayments as $pr)
            <div class="card-body border-bottom py-3">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="fw-semibold">{{ number_format($pr->amount, 0) }} {{ $loan->group->currency }}</div>
                        <div class="text-muted small">
                            {{ $pr->paid_on?->format('d M Y') }} ·
                            {{ str_replace('_',' ',$pr->method) }} ·
                            <span class="badge bg-secondary-lt">{{ $pr->paymentTypeLabel() }}</span>
                            @if($pr->accrual_period)
                                · for {{ \Carbon\Carbon::parse($pr->accrual_period)->format('M Y') }}
                            @endif
                        </div>
                        @if($pr->notes)
                            <div class="text-muted small mt-1">{{ $pr->notes }}</div>
                        @endif
                        <div class="text-muted small mt-1">Submitted by {{ $pr->recorder?->name }}</div>
                        @if($pr->proof_file)
                        <div class="mt-2">
                            @php $proofUrl = Storage::disk('public')->url($pr->proof_file); $ext = strtolower(pathinfo($pr->proof_file, PATHINFO_EXTENSION)); @endphp
                            @if(in_array($ext, ['jpg','jpeg','png','webp']))
                                <a href="{{ $proofUrl }}" target="_blank">
                                    <img src="{{ $proofUrl }}" alt="Proof" class="rounded border"
                                         style="max-height:80px;max-width:160px;object-fit:cover;">
                                </a>
                            @else
                                <a href="{{ $proofUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="ti ti-file-description me-1"></i>View proof (PDF)
                                </a>
                            @endif
                        </div>
                        @endif
                    </div>
                    <div class="d-flex gap-1 ms-3">
                        @can('approveRepayment', $loan)
                        <form method="POST" action="{{ route('loans.repayments.approve', [$loan, $pr]) }}">@csrf
                            <button class="btn btn-sm btn-success"
                                    onclick="return confirm('Approve this repayment of {{ number_format($pr->amount, 0) }}?')">
                                <i class="ti ti-check me-1"></i>Approve
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-danger" type="button"
                                data-bs-toggle="collapse" data-bs-target="#reject-repayment-{{ $pr->id }}">
                            <i class="ti ti-x"></i>
                        </button>
                        @endcan
                        @if(auth()->user()->hasAnyRole(['super_admin','group_admin']))
                        <form method="POST"
                              action="{{ route('loans.repayments.destroy', [$loan, $pr]) }}"
                              onsubmit="return confirm('Delete this pending repayment of {{ number_format($pr->amount, 0) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost-danger" title="Delete repayment">
                                <i class="ti ti-trash"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @can('approveRepayment', $loan)
                <div class="collapse" id="reject-repayment-{{ $pr->id }}">
                    <form method="POST" action="{{ route('loans.repayments.reject', [$loan, $pr]) }}" class="mt-2">@csrf
                        <textarea name="rejection_reason" class="form-control form-control-sm mb-2"
                                  placeholder="Reason for rejection (optional)" rows="2"></textarea>
                        <button class="btn btn-sm btn-danger w-100">Confirm rejection</button>
                    </form>
                </div>
                @endcan
            </div>
            @endforeach
        </div>
        @endif

        <div class="card mt-3" id="card-member">
            <div class="card-header"><h3 class="card-title">Member</h3></div>
            <div class="card-body d-flex align-items-center">
                <span class="avatar avatar-md me-3" style="background-image:url('{{ $loan->member->photo_url }}')"></span>
                <div>
                    <a href="{{ route('members.show', $loan->member) }}" class="text-reset fw-semibold">{{ $loan->member->full_name }}</a>
                    <div class="text-muted small">{{ $loan->member->member_no }} · {{ $loan->member->phone ?? 'no phone' }}</div>
                </div>
            </div>
        </div>

        {{-- Manual accrual (compound loans, admin/treasurer only) --}}
        @if($isCompound && $loan->isOpen() && auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer']))
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-trending-up me-1 text-orange"></i>Run interest accrual</h3>
            </div>
            <form method="POST" action="{{ route('loans.accrue', $loan) }}" class="card-body" id="accrual-form">@csrf
                <p class="text-muted small mb-3">
                    Manually add one month of compound interest to this loan.
                    The scheduler does this automatically on the 1st of each month —
                    use this only to catch up a missed period or to test.
                    Running it twice for the same month is safe (idempotent).
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col">
                        <label class="form-label">Period (default: current month)</label>
                        <input type="month" name="period" class="form-control"
                               value="{{ now()->format('Y-m') }}"
                               max="{{ now()->format('Y-m') }}">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-warning"
                                onclick="return confirm('Accrue interest for the selected month on loan {{ $loan->reference }}?')">
                            <i class="ti ti-calculator me-1"></i>Accrue now
                        </button>
                    </div>
                </div>
                @php
                    $previewRate = rtrim(rtrim(number_format($loan->interest_rate_pct,3),'0'),'.');
                    $previewAmt  = number_format($loan->monthlyInterestDue(), 0);
                @endphp
                <div class="mt-2 text-muted small">
                    Current outstanding: <strong>{{ number_format($loan->outstanding, 0) }}</strong>
                    × {{ $previewRate }}% = <strong class="text-danger">+{{ $previewAmt }}</strong> {{ $loan->group->currency }}
                </div>
            </form>
        </div>
        @endif

        {{-- Default / Write-off actions (group_admin / super_admin only) --}}
        @if($u->can('markDefaulted', $loan) || $u->can('writeOff', $loan))
        <div class="card mt-3 border-warning">
            <div class="card-header">
                <h3 class="card-title text-warning">
                    <i class="ti ti-alert-circle me-1"></i>Default &amp; write-off
                </h3>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-ghost-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#default-writoff-panel">
                        <i class="ti ti-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="collapse" id="default-writoff-panel">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        These actions are irreversible. Use <strong>Mark as defaulted</strong> when the member
                        has stopped repaying; use <strong>Write off</strong> to permanently clear the outstanding
                        balance from the group's books.
                    </p>

                    @if($u->can('markDefaulted', $loan))
                    <button class="btn btn-outline-warning w-100 mb-2"
                            data-bs-toggle="collapse" data-bs-target="#default-form">
                        <i class="ti ti-alert-triangle me-1"></i>Mark as defaulted…
                    </button>
                    <div class="collapse mb-3" id="default-form">
                        <form method="POST" action="{{ route('loans.markDefaulted', $loan) }}"
                              onsubmit="return confirm('Flag loan {{ $loan->reference }} as defaulted? You can still record repayments afterwards.')">
                            @csrf
                            <label class="form-label">Reason / notes (optional)</label>
                            <textarea name="notes" class="form-control mb-2" rows="2"
                                      placeholder="e.g. Member unreachable since March 2026"></textarea>
                            <button class="btn btn-warning btn-sm w-100">
                                <i class="ti ti-alert-triangle me-1"></i>Confirm: mark as defaulted
                            </button>
                        </form>
                    </div>
                    @endif

                    @if($u->can('writeOff', $loan))
                    <button class="btn btn-outline-danger w-100"
                            data-bs-toggle="collapse" data-bs-target="#writoff-form">
                        <i class="ti ti-circle-x me-1"></i>Write off loan…
                    </button>
                    <div class="collapse mt-2" id="writoff-form">
                        <div class="alert alert-danger py-2 small mb-2">
                            <i class="ti ti-alert-triangle me-1"></i>
                            Writing off clears the outstanding balance of
                            <strong>{{ number_format((float)$loan->outstanding, 0) }} {{ $currency }}</strong>
                            from the group's books. This cannot be undone.
                        </div>
                        <form method="POST" action="{{ route('loans.writeOff', $loan) }}"
                              onsubmit="return confirm('Write off {{ $loan->reference }}? The outstanding balance will be permanently cleared.')">
                            @csrf
                            <label class="form-label">Reason / notes (optional)</label>
                            <textarea name="notes" class="form-control mb-2" rows="2"
                                      placeholder="e.g. Member deceased, debt unrecoverable"></textarea>
                            <button class="btn btn-danger btn-sm w-100">
                                <i class="ti ti-circle-x me-1"></i>Confirm: write off
                                {{ number_format((float)$loan->outstanding, 0) }} {{ $currency }}
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @can('delete', $loan)
        <div class="card mt-3 border-danger">
            <div class="card-header">
                <h3 class="card-title text-danger"><i class="ti ti-alert-triangle me-1"></i>Danger zone</h3>
            </div>
            <div class="card-body">
                @if($u->isSuperAdmin() && in_array($loan->status, ['approved','disbursed','repaying','paid']))
                    <p class="text-muted small mb-3">
                        Deleting this {{ $loan->status }} loan will permanently remove
                        the loan record and <strong>all {{ $loan->repayments->count() }} repayment(s)</strong>
                        attached to it. This cannot be undone — use only to fix bad data entry.
                    </p>
                @else
                    <p class="text-muted small mb-3">
                        Permanently remove this loan request.
                    </p>
                @endif
                <form method="POST" action="{{ route('loans.destroy', $loan) }}"
                      onsubmit="return confirm('Delete loan {{ $loan->reference }}? This cannot be undone.');">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger w-100">
                        <i class="ti ti-trash me-1"></i>Delete loan
                    </button>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('repayment-form');
    if (!form) return;

    const radios         = form.querySelectorAll('input[name="payment_type"]');
    const periodRow      = document.getElementById('accrual-period-row');
    const amountInput    = document.getElementById('repayment-amount');
    const hiddenPeriod   = document.getElementById('accrual-period-hidden');
    const accrualCbs     = form.querySelectorAll('.accrual-cb');
    const interestHint   = document.getElementById('interest-hint');
    const principalHint  = document.getElementById('principal-hint');

    // Balances from PHP
    const fullOutstanding  = {{ (float) $loan->outstanding }};
    const remainPrincipal  = {{ (float) $remainingPrincipal }};
    const unpaidInterest   = {{ (float) $unpaidInterest }};

    function onCheckboxChange(clearIfNone = false) {
        const checked = [...accrualCbs].filter(cb => cb.checked);
        if (checked.length === 0) {
            // No months selected — free-form amount, no accrual_period
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-light');
            if (clearIfNone) amountInput.value = '';
            if (hiddenPeriod) hiddenPeriod.value = '';
        } else {
            // Sum selected months, capped at actual unpaid interest (still editable)
            const rawTotal = checked.reduce((s, cb) => s + parseFloat(cb.dataset.amount || 0), 0);
            const capped   = unpaidInterest > 0 ? Math.min(rawTotal, unpaidInterest) : rawTotal;
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-light');
            amountInput.value    = capped.toFixed(2);
            // Pass single period when exactly one month checked; otherwise null
            if (hiddenPeriod) {
                hiddenPeriod.value = checked.length === 1 ? checked[0].value : '';
            }
        }
    }

    function onTypeChange() {
        const val = form.querySelector('input[name="payment_type"]:checked')?.value;

        // Show / hide month checkbox panel
        if (periodRow) {
            periodRow.classList.toggle('d-none', val !== 'interest_only');
        }

        // Hint banners
        if (interestHint)  interestHint.classList.toggle('d-none',  val === 'principal_only');
        if (principalHint) principalHint.classList.toggle('d-none', val !== 'principal_only');

        if (val === 'full') {
            // Auto-fill the total outstanding and lock the field
            amountInput.value    = fullOutstanding.toFixed(2);
            amountInput.readOnly = true;
            amountInput.classList.add('bg-light');
            if (hiddenPeriod) hiddenPeriod.value = '';
            accrualCbs.forEach(cb => cb.checked = false);
        } else if (val === 'interest_only') {
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-light');
            onCheckboxChange(true);
        } else {
            // principal_only — pre-fill with remaining principal (editable)
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-light');
            amountInput.value    = remainPrincipal > 0 ? remainPrincipal.toFixed(2) : '';
            if (hiddenPeriod) hiddenPeriod.value = '';
            accrualCbs.forEach(cb => cb.checked = false);
        }
    }

    radios.forEach(r => r.addEventListener('change', onTypeChange));
    accrualCbs.forEach(cb => cb.addEventListener('change', onCheckboxChange));

    form.addEventListener('submit', function () {
        const submitButton = document.getElementById('repayment-submit-btn');
        if (!submitButton || submitButton.disabled) return;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="ti ti-loader-2 me-1"></i>Submitting…';
    });

    // Run once on load
    onTypeChange();
})();
</script>
@endpush

@endsection
