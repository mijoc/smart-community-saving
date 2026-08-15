@extends('layouts.app')
@section('title', 'Payment '.$payment->reference)
@section('content')

<x-page_header :title="'Payment '.$payment->reference" pretitle="Payments">
    <x-slot name="actions">
        <a href="{{ route('payments.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back</a>
    </x-slot>
</x-page_header>

<div class="card mt-3"><div class="card-body">
    <div class="datagrid">
        <div class="datagrid-item"><div class="datagrid-title">Date</div><div class="datagrid-content">{{ $payment->paid_on->format('Y-m-d') }}</div></div>
        <div class="datagrid-item"><div class="datagrid-title">Member</div><div class="datagrid-content">{{ $payment->member?->full_name }}</div></div>
        <div class="datagrid-item"><div class="datagrid-title">Group</div><div class="datagrid-content">{{ $payment->group?->name }}</div></div>
        <div class="datagrid-item"><div class="datagrid-title">Method</div><div class="datagrid-content">{{ str_replace('_',' ',$payment->method) }}</div></div>
        <div class="datagrid-item"><div class="datagrid-title">Channel ref</div><div class="datagrid-content">{{ $payment->channel_ref ?? '—' }}</div></div>
        <div class="datagrid-item"><div class="datagrid-title">Amount</div><div class="datagrid-content"><strong>{{ number_format($payment->amount, 0) }}</strong></div></div>
        <div class="datagrid-item"><div class="datagrid-title">Received by</div><div class="datagrid-content">{{ $payment->receiver?->name ?? '—' }}</div></div>
        @if($payment->contribution)
        <div class="datagrid-item"><div class="datagrid-title">Applied to</div>
            <div class="datagrid-content"><a href="{{ route('contributions.show', $payment->contribution) }}">Contribution #{{ $payment->contribution_id }}</a></div></div>
        @endif
    </div>
    @if($payment->notes)<hr><p class="text-muted">{{ $payment->notes }}</p>@endif
</div></div>

@can('delete', $payment)
<div class="card mt-3 border-danger">
    <div class="card-header">
        <h3 class="card-title text-danger"><i class="ti ti-alert-triangle me-1"></i>Danger zone</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Deleting this payment will permanently remove it
            @if($payment->contribution)
                and reverse <strong>{{ number_format($payment->amount, 0) }}</strong>
                from <a href="{{ route('contributions.show', $payment->contribution) }}">contribution
                #{{ $payment->contribution_id }}</a>
                (status will be re-evaluated)
            @endif.
            The matching passbook entry is also removed. This cannot be undone — use only to fix bad data entry.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('payments.destroy', $payment) }}"
                  onsubmit="return confirm('Delete payment {{ $payment->reference }} ({{ number_format($payment->amount, 0) }})? This will reverse its effects and cannot be undone.');">
                @csrf @method('DELETE')
                <button class="btn btn-danger">
                    <i class="ti ti-trash me-1"></i>Delete payment
                </button>
            </form>

            @if($payment->contribution)
            <form method="POST" action="{{ route('payments.mark-pending', $payment) }}"
                  onsubmit="return confirm('Delete payment {{ $payment->reference }} and move contribution #{{ $payment->contribution_id }} to Pending (not paid)? This cannot be undone.');">
                @csrf
                <button class="btn btn-warning">
                    <i class="ti ti-clock me-1"></i>Delete &amp; mark contribution as Pending
                </button>
            </form>
            @endif
        </div>
        <p class="text-muted small mt-2 mb-0">
            <em>“Delete payment”</em> reverses the payment and lets the contribution status auto-recalculate
            (it may become <strong>Overdue</strong> if the due date has passed).
            <em>“Delete &amp; mark as Pending”</em> does the same but forces the contribution back to <strong>Pending</strong> regardless of the due date.
        </p>
    </div>
</div>
@endcan
@endsection
