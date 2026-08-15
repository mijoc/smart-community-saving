@extends('layouts.app')
@section('title','Cashbook')
@section('content')

<x-page_header title="Cashbook" pretitle="Group income & expenses">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'cashbook'])
        @can('create', App\Models\CashbookEntry::class)
        <a href="{{ route('cashbook.create', ['type' => 'income']) }}" class="btn btn-success">
            <i class="ti ti-arrow-down-circle me-1"></i> Record deposit
        </a>
        <a href="{{ route('cashbook.create', ['type' => 'expense']) }}" class="btn btn-danger">
            <i class="ti ti-arrow-up-circle me-1"></i> Record withdrawal
        </a>
        @can('regularize', App\Models\CashbookEntry::class)
        <a href="{{ route('cashbook.regularize.create') }}" class="btn btn-warning">
            <i class="ti ti-adjustments-horizontal me-1"></i> Regularize
        </a>
        @endcan
        @endcan
    </x-slot>
</x-page_header>

<div class="row row-cards mt-3">
    @php
        $cards = [
            ['Deposits (income)',   number_format($income, 0), 'ti-arrow-down-circle', 'green'],
            ['Withdrawals (expense)', number_format($expense, 0), 'ti-arrow-up-circle',   'red'],
            ['Net cash movement',   number_format($net, 0), 'ti-equal',             $net >= 0 ? 'azure' : 'orange'],
        ];
    @endphp
    @foreach($cards as [$label,$value,$icon,$color])
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="bg-{{ $color }}-lt rounded p-3 me-3"><i class="ti {{ $icon }} fs-2 text-{{ $color }}"></i></div>
                <div>
                    <div class="text-muted small text-uppercase">{{ $label }}</div>
                    <div class="h2 mb-0">{{ $value }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <select name="group_id" class="form-select">
                    <option value="">All groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(request('group_id')==$g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="">All types</option>
                    <option value="income"  @selected(request('type')==='income')>Income (deposit)</option>
                    <option value="expense" @selected(request('type')==='expense')>Expense (withdrawal)</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="to"   value="{{ request('to') }}"   class="form-control"></div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary"><i class="ti ti-search me-1"></i>Filter</button>
                <a href="{{ route('cashbook.index') }}" class="btn btn-link">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Counterparty</th>
                    <th>Method</th>
                    <th>Group</th>
                    <th class="text-end">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($entries as $e)
                @php $cats = App\Models\CashbookEntry::categoriesFor($e->type); @endphp
                <tr>
                    <td><a href="{{ route('cashbook.show', $e) }}" class="text-reset fw-semibold">{{ $e->reference }}</a></td>
                    <td>{{ $e->occurred_on->format('Y-m-d') }}</td>
                    <td>
                        @if($e->type === 'income')
                            <span class="badge bg-green-lt"><i class="ti ti-arrow-down-circle me-1"></i>Income</span>
                        @else
                            <span class="badge bg-red-lt"><i class="ti ti-arrow-up-circle me-1"></i>Expense</span>
                        @endif
                    </td>
                    <td>{{ App\Models\CashbookEntry::categoryLabel($e->type, $e->category) }}</td>
                    <td class="text-muted">{{ $e->counterparty ?? '—' }}</td>
                    <td><span class="badge bg-blue-lt">{{ str_replace('_',' ',$e->method) }}</span></td>
                    <td>{{ $e->group?->name }}</td>
                    <td class="text-end fw-semibold {{ $e->type === 'income' ? 'text-success' : 'text-danger' }}">
                        {{ $e->type === 'income' ? '+' : '-' }}{{ number_format($e->amount, 0) }}
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            @can('update', $e)
                            <a href="{{ route('cashbook.edit', $e) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="ti ti-edit"></i>
                            </a>
                            @endcan
                            @can('delete', $e)
                            <form method="POST" action="{{ route('cashbook.destroy', $e) }}"
                                  onsubmit="return confirm('Delete {{ $e->reference }}?');" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No cashbook entries match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $entries->links() }}</div>
</div>
@endsection
