@extends('layouts.app')
@php($regularize = $regularize ?? false)
@section('title', $regularize ? 'Regularize expense' : ($type === 'income' ? 'Record deposit' : 'Record withdrawal'))
@section('content')

<x-page_header
    :title="$regularize ? 'Regularize expense' : ($type === 'income' ? 'Record deposit (income)' : 'Record withdrawal (expense)')"
    pretitle="Cashbook">
    <x-slot name="actions">
        <a href="{{ route('cashbook.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back</a>
    </x-slot>
</x-page_header>

@if (! $regularize)
<div class="btn-group mt-3" role="group">
    <a href="{{ route('cashbook.create', ['type' => 'income']) }}"
       class="btn btn-sm {{ $type === 'income' ? 'btn-success' : 'btn-outline-success' }}">
        <i class="ti ti-arrow-down-circle me-1"></i> Deposit
    </a>
    <a href="{{ route('cashbook.create', ['type' => 'expense']) }}"
       class="btn btn-sm {{ $type === 'expense' ? 'btn-danger' : 'btn-outline-danger' }}">
        <i class="ti ti-arrow-up-circle me-1"></i> Withdrawal
    </a>
</div>
@endif

<form method="POST" action="{{ $regularize ? route('cashbook.regularize.store') : route('cashbook.store') }}" class="card mt-3">@csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label required">Group</label>
                <select name="group_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(old('group_id', $activeId) == $g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label required">Category</label>
                @if ($regularize)
                    <input type="hidden" name="category" value="{{ App\Models\CashbookEntry::REGULARIZATION_CATEGORY }}">
                    <div class="form-control bg-orange-lt">
                        <i class="ti ti-adjustments-horizontal me-1"></i>Regularization
                    </div>
                    <div class="form-hint">Admin-only correction. It is not shown in group activity or to non-admin users.</div>
                @else
                <select name="category" class="form-select" required>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @endif
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ $type === 'income' ? 'Received from' : 'Paid to' }}</label>
                <input name="counterparty" value="{{ old('counterparty') }}" class="form-control"
                       placeholder="e.g. Stanbic Bank, John Doe, NGO X">
            </div>

            <div class="col-md-3">
                <label class="form-label required">Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label required">Method</label>
                <select name="method" class="form-select">
                    @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                        <option value="{{ $m }}" @selected(old('method') === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Channel reference</label>
                <input name="channel_ref" value="{{ old('channel_ref') }}" class="form-control"
                       placeholder="MM TX ID, slip #, cheque #">
            </div>

            <div class="col-md-3">
                <label class="form-label required">Date</label>
                <input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
                <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif
    </div>
    <div class="card-footer text-end">
        <a href="{{ route('cashbook.index') }}" class="btn">Cancel</a>
        <button class="btn {{ $regularize ? 'btn-warning' : ($type === 'income' ? 'btn-success' : 'btn-danger') }}">
            <i class="ti ti-device-floppy me-1"></i>{{ $regularize ? 'Save regularization' : 'Save '.$type }}
        </button>
    </div>
</form>
@endsection
