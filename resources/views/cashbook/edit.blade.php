@extends('layouts.app')
@section('title', 'Edit '.$entry->reference)
@section('content')

@php $isIncome = $entry->type === 'income'; @endphp

<x-page_header
    :title="'Edit · '.$entry->reference"
    pretitle="{{ $isIncome ? 'Income (deposit)' : 'Expense (withdrawal)' }}">
    <x-slot name="actions">
        <a href="{{ route('cashbook.show', $entry) }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back</a>
        @can('delete', $entry)
        <form method="POST" action="{{ route('cashbook.destroy', $entry) }}"
              onsubmit="return confirm('Delete this entry? This cannot be undone.');" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i>Delete</button>
        </form>
        @endcan
    </x-slot>
</x-page_header>

<form method="POST" action="{{ route('cashbook.update', $entry) }}" class="card mt-3">
    @csrf @method('PUT')

    <div class="card-header">
        <span class="badge {{ $isIncome ? 'bg-green-lt' : 'bg-red-lt' }}">
            <i class="ti {{ $isIncome ? 'ti-arrow-down-circle' : 'ti-arrow-up-circle' }} me-1"></i>
            {{ $isIncome ? 'Income / Deposit' : 'Expense / Withdrawal' }}
        </span>
        <span class="ms-2 text-muted small">Reference: <strong>{{ $entry->reference }}</strong> (cannot be changed)</span>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label required">Category</label>
                <select name="category" class="form-select" required>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $entry->category) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ $isIncome ? 'Received from' : 'Paid to' }}</label>
                <input name="counterparty" value="{{ old('counterparty', $entry->counterparty) }}"
                       class="form-control" placeholder="e.g. Stanbic Bank, John Doe">
            </div>

            <div class="col-md-4">
                <label class="form-label">Group</label>
                <input class="form-control" value="{{ $entry->group?->name }}" disabled>
                <div class="form-hint">Group cannot be changed after recording.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label required">Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount"
                       value="{{ old('amount', $entry->amount) }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label required">Method</label>
                <select name="method" class="form-select">
                    @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                        <option value="{{ $m }}" @selected(old('method', $entry->method) === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Channel reference</label>
                <input name="channel_ref" value="{{ old('channel_ref', $entry->channel_ref) }}"
                       class="form-control" placeholder="MM TX ID, slip #, cheque #">
            </div>

            <div class="col-md-3">
                <label class="form-label required">Date</label>
                <input type="date" name="occurred_on"
                       value="{{ old('occurred_on', $entry->occurred_on->toDateString()) }}"
                       class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $entry->notes) }}</textarea>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
                <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif
    </div>

    <div class="card-footer text-end">
        <a href="{{ route('cashbook.show', $entry) }}" class="btn me-2">Cancel</a>
        <button class="btn {{ $isIncome ? 'btn-success' : 'btn-danger' }}">
            <i class="ti ti-device-floppy me-1"></i>Save changes
        </button>
    </div>
</form>
@endsection
