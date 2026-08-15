@extends('layouts.app')
@section('title', 'Treasury')
@section('content')

<x-page_header title="Group treasury" pretitle="Total wealth & fund position">
    <x-slot name="actions">
        @if(session('active_group_id') && $activeGroup && auth()->user()->can('view', $activeGroup))
            <a href="{{ route('treasury.report.preview') }}" class="btn btn-outline-danger" target="_blank">
                <i class="ti ti-file-type-pdf me-1"></i> Full treasury report
            </a>
            @include('partials._report_downloads', ['report' => 'treasury_members', 'label' => 'Member equity report'])
        @endif
        @can('create', App\Models\CashbookEntry::class)
        <a href="{{ route('cashbook.create', ['type' => 'income']) }}" class="btn btn-success">
            <i class="ti ti-arrow-down-circle me-1"></i> Record income
        </a>
        <a href="{{ route('cashbook.create', ['type' => 'expense']) }}" class="btn btn-danger">
            <i class="ti ti-arrow-up-circle me-1"></i> Record expense
        </a>
        @can('regularize', App\Models\CashbookEntry::class)
        <a href="{{ route('cashbook.regularize.create') }}" class="btn btn-warning">
            <i class="ti ti-adjustments-horizontal me-1"></i> Regularize
        </a>
        @endcan
        @endcan
        @can('viewAny', App\Models\Loan::class)
        <a href="{{ route('loans.index') }}" class="btn btn-outline-primary">
            <i class="ti ti-cash-banknote me-1"></i> Loan portfolio
        </a>
        @endcan
    </x-slot>
</x-page_header>

@php
    $fmt = fn ($v) => number_format((float) $v, 0);
@endphp

{{-- ====== Headline cards: total wealth + fund composition ====== --}}
<div class="row row-cards mt-3">
    <div class="col-md-6 col-xl-4">
        <div class="card card-md bg-primary text-white">
            <div class="card-body">
                <div class="text-uppercase small opacity-75">Total group fund</div>
                <div class="display-6 fw-bold">{{ $fmt($summary['total_group_fund']) }} <small class="fs-4 opacity-75">{{ $currency }}</small></div>
                <div class="small opacity-75 mt-1">Cash on hand + loan principal still owed to the group.</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-green-lt rounded p-3 me-3"><i class="ti ti-cash fs-2 text-green"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Cash on hand</div>
                    <div class="h2 mb-0">{{ $fmt($summary['cash_on_hand']) }} {{ $currency }}</div>
                    <div class="small text-muted">Inflows minus outflows to date.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-blue-lt rounded p-3 me-3"><i class="ti ti-cash-banknote fs-2 text-blue"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Loans out (principal)</div>
                    <div class="h2 mb-0">{{ $fmt($summary['principal_outstanding']) }} {{ $currency }}</div>
                    <div class="small text-muted">{{ $summary['open_loans_count'] }} open loan(s).</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-teal-lt rounded p-3 me-3"><i class="ti ti-percentage fs-2 text-teal"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Interest earned</div>
                    <div class="h3 mb-0">{{ $fmt($summary['interest_earned']) }}</div>
                    <div class="small text-muted">Already collected.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-azure-lt rounded p-3 me-3"><i class="ti ti-clock-dollar fs-2 text-azure"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Interest receivable</div>
                    <div class="h3 mb-0">{{ $fmt($summary['interest_receivable']) }}</div>
                    <div class="small text-muted">Still to be collected on open loans.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-pink-lt rounded p-3 me-3"><i class="ti ti-receipt-2 fs-2 text-pink"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Late fees collected</div>
                    <div class="h3 mb-0">{{ $fmt($summary['late_fees_collected']) }}</div>
                    <div class="small text-muted">Lifetime.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-red-lt rounded p-3 me-3"><i class="ti ti-alert-triangle fs-2 text-red"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">Open arrears</div>
                    <div class="h3 mb-0">{{ $fmt($summary['open_arrears']) }}</div>
                    <div class="small text-muted">Overdue contributions still owed.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ====== Per-member equity & debt ====== --}}
@if($activeGroup)
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Member equity & debt — {{ $activeGroup->name }}</h3>
        <div class="card-actions text-muted small">
            Equity = savings paid in. Debt = loans + unpaid contributions + attendance fines.
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th class="text-end bg-green-lt">Savings (equity)</th>
                    <th class="text-end bg-green-lt">Other contrib.</th>
                    <th class="text-end bg-red-lt">Loan principal</th>
                    <th class="text-end bg-red-lt">Loan interest</th>
                    <th class="text-end bg-red-lt">Other dues</th>
                    <th class="text-end bg-red-lt">Total debt</th>
                    <th class="text-end">Net position</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($memberRows as $row)
                    <tr>
                        <td class="text-muted">{{ $row['member']->member_no }}</td>
                        <td>{{ $row['member']->full_name }}</td>
                        <td class="text-end text-green">{{ $fmt($row['savings']) }}</td>
                        <td class="text-end text-muted">{{ $fmt($row['other_equity']) }}</td>
                        <td class="text-end">{{ $fmt($row['loan_principal']) }}</td>
                        <td class="text-end">{{ $fmt($row['loan_interest']) }}</td>
                        <td class="text-end">{{ $fmt($row['other_due']) }}</td>
                        <td class="text-end fw-bold {{ $row['total_debt'] > 0 ? 'text-red' : 'text-muted' }}">
                            {{ $fmt($row['total_debt']) }}
                        </td>
                        <td class="text-end fw-bold {{ $row['net_position'] >= 0 ? 'text-green' : 'text-red' }}">
                            {{ $fmt($row['net_position']) }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('treasury.member', $row['member']) }}"
                               class="btn btn-sm btn-outline-secondary"
                               title="View member treasury">
                                <i class="ti ti-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No members in this group yet.</td></tr>
                @endforelse
            </tbody>
            @if($memberTotals)
            <tfoot>
                <tr class="table-active">
                    <td colspan="2"><strong>Totals ({{ count($memberRows) }} members)</strong></td>
                    <td class="text-end fw-bold text-green">{{ $fmt($memberTotals['savings']) }}</td>
                    <td colspan="4"></td>
                    <td class="text-end fw-bold text-red">{{ $fmt($memberTotals['total_debt']) }}</td>
                    <td class="text-end fw-bold {{ $memberTotals['net_position'] >= 0 ? 'text-green' : 'text-red' }}">
                        {{ $fmt($memberTotals['net_position']) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endif

{{-- ====== Cash flow & profit composition ====== --}}
<div class="row row-cards mt-2">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Cash flow to date</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <tbody>
                        <tr>
                            <td><i class="ti ti-arrow-down-right text-green me-1"></i> Member payments</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['member_payments']) }}</td>
                        </tr>
                        <tr>
                            <td><i class="ti ti-arrow-down-right text-green me-1"></i> Loan repayments received</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['loan_repayments_total']) }}</td>
                        </tr>
                        <tr>
                            <td><i class="ti ti-arrow-down-right text-green me-1"></i> Cashbook income</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['cashbook_income']) }}</td>
                        </tr>
                        <tr>
                            <td><i class="ti ti-arrow-up-right text-red me-1"></i> Loans disbursed</td>
                            <td class="text-end text-red">− {{ $fmt($summary['loans_disbursed_total']) }}</td>
                        </tr>
                        <tr>
                            <td><i class="ti ti-arrow-up-right text-red me-1"></i> Cashbook expenses</td>
                            <td class="text-end text-red">− {{ $fmt($summary['cashbook_expense']) }}</td>
                        </tr>
                        <tr class="table-active">
                            <td><strong>Cash on hand</strong></td>
                            <td class="text-end"><strong>{{ $fmt($summary['cash_on_hand']) }} {{ $currency }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Profit & loss (lifetime)</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <tbody>
                        <tr>
                            <td>Interest earned on loans</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['interest_earned']) }}</td>
                        </tr>
                        <tr>
                            <td>Late fees collected</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['late_fees_collected']) }}</td>
                        </tr>
                        <tr>
                            <td>Other income (donations, bank interest, …)</td>
                            <td class="text-end text-green">+ {{ $fmt($summary['cashbook_income']) }}</td>
                        </tr>
                        <tr>
                            <td>Expenses</td>
                            <td class="text-end text-red">− {{ $fmt($summary['cashbook_expense']) }}</td>
                        </tr>
                        <tr class="table-active">
                            <td><strong>Net profit (distributable)</strong></td>
                            <td class="text-end"><strong class="{{ $summary['net_profit'] >= 0 ? 'text-green' : 'text-red' }}">
                                {{ $fmt($summary['net_profit']) }} {{ $currency }}
                            </strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Member equity (savings paid in)</td>
                            <td class="text-end text-muted">{{ $fmt($summary['member_equity_total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ====== Open loans ====== --}}
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Top open loans (principal still out)</h3>
        <div class="card-actions">
            <a href="{{ route('loans.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member</th>
                    <th>Group</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($openLoans as $loan)
                <tr>
                    <td><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">{{ $loan->reference }}</a></td>
                    <td>{{ $loan->member?->full_name }} <span class="text-muted small">{{ $loan->member?->member_no }}</span></td>
                    <td>{{ $loan->group?->name }}</td>
                    <td class="text-end">{{ $fmt($loan->principal) }}</td>
                    <td class="text-end fw-bold">{{ $fmt($loan->outstanding) }}</td>
                    <td><span class="badge bg-{{ $loan->statusBadge() }}-lt">{{ ucfirst($loan->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No open loans.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ====== Recent movements ====== --}}
<div class="row row-cards mt-2">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent member payments</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Date</th><th>Member</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @forelse($recentPayments as $p)
                            <tr>
                                <td class="text-muted">{{ optional($p->paid_on)->format('Y-m-d') }}</td>
                                <td>{{ $p->member?->full_name }}</td>
                                <td class="text-end text-green">+ {{ $fmt($p->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent cashbook entries</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Date</th><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @forelse($recentCashbook as $c)
                            <tr>
                                <td class="text-muted">{{ optional($c->occurred_on)->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge bg-{{ $c->type === 'income' ? 'green' : 'red' }}-lt">
                                        {{ str_replace('_', ' ', $c->category) }}
                                    </span>
                                </td>
                                <td class="text-end {{ $c->type === 'income' ? 'text-green' : 'text-red' }}">
                                    {{ $c->type === 'income' ? '+' : '−' }} {{ $fmt($c->amount) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No cashbook entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
