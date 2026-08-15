@extends('layouts.app')
@section('title', 'Cashbook entry '.$entry->reference)
@section('content')

@php $cats = App\Models\CashbookEntry::categoriesFor($entry->type); @endphp

<x-page_header :title="'Cashbook · '.$entry->reference" pretitle="{{ ucfirst($entry->type) }}">
    <x-slot name="actions">
        <a href="{{ route('cashbook.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i>Back</a>
        @can('update', $entry)
        <a href="{{ route('cashbook.edit', $entry) }}" class="btn btn-outline-primary">
            <i class="ti ti-edit me-1"></i>Edit
        </a>
        @endcan
        @can('delete', $entry)
        <form method="POST" action="{{ route('cashbook.destroy', $entry) }}"
              onsubmit="return confirm('Remove this cashbook entry? This cannot be undone.');" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="ti ti-trash me-1"></i>Delete</button>
        </form>
        @endcan
    </x-slot>
</x-page_header>

<div class="card mt-3">
    <div class="card-body">
        <div class="datagrid">
            <div class="datagrid-item"><div class="datagrid-title">Type</div>
                <div class="datagrid-content">
                    @if($entry->type === 'income')
                        <span class="badge bg-green-lt"><i class="ti ti-arrow-down-circle me-1"></i>Income (deposit)</span>
                    @else
                        <span class="badge bg-red-lt"><i class="ti ti-arrow-up-circle me-1"></i>Expense (withdrawal)</span>
                    @endif
                </div>
            </div>
            <div class="datagrid-item"><div class="datagrid-title">Date</div>
                <div class="datagrid-content">{{ $entry->occurred_on->format('Y-m-d') }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Group</div>
                <div class="datagrid-content">{{ $entry->group?->name }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Category</div>
                <div class="datagrid-content">{{ App\Models\CashbookEntry::categoryLabel($entry->type, $entry->category) }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">{{ $entry->type === 'income' ? 'Received from' : 'Paid to' }}</div>
                <div class="datagrid-content">{{ $entry->counterparty ?? '—' }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Method</div>
                <div class="datagrid-content">{{ str_replace('_',' ', $entry->method) }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Channel reference</div>
                <div class="datagrid-content">{{ $entry->channel_ref ?? '—' }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Amount</div>
                <div class="datagrid-content">
                    <strong class="{{ $entry->type === 'income' ? 'text-success' : 'text-danger' }}">
                        {{ $entry->type === 'income' ? '+' : '-' }}{{ number_format($entry->amount, 0) }}
                    </strong>
                </div>
            </div>
            <div class="datagrid-item"><div class="datagrid-title">Recorded by</div>
                <div class="datagrid-content">{{ $entry->recorder?->name ?? '—' }}</div></div>
            <div class="datagrid-item"><div class="datagrid-title">Recorded at</div>
                <div class="datagrid-content text-muted">{{ $entry->created_at?->format('Y-m-d H:i') }}</div></div>
        </div>
        @if($entry->notes)
            <hr><p class="text-muted mb-0">{{ $entry->notes }}</p>
        @endif
    </div>
</div>
@endsection
