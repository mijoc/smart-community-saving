@extends('layouts.app')
@section('title', $member->full_name.' · Passbook')
@section('content')

<x-page_header :title="$member->full_name.' · Passbook'" :pretitle="$member->member_no">
    <x-slot name="actions">
        @if($currentGroup)
            @include('partials._report_downloads', [
                'report' => 'passbook',
                'params' => ['member_id' => $member->id, 'group_id' => $currentGroup->id],
            ])
        @endif
    </x-slot>
</x-page_header>

<div class="row row-cards mt-3">
    <div class="col-md-4">
        <form method="GET" class="card">
            <div class="card-header"><h3 class="card-title">Group</h3></div>
            <div class="card-body">
                <select name="group_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— pick a group —</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected($currentGroup?->id===$g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <div class="card stat-card"><div class="card-body d-flex align-items-center">
            <div class="bg-green-lt rounded p-3 me-3"><i class="ti ti-pig-money fs-2 text-green"></i></div>
            <div><div class="text-muted small text-uppercase">Current balance</div>
                 <div class="h2 mb-0">{{ number_format($balance, 0) }} {{ $currentGroup?->currency }}</div></div>
        </div></div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3 class="card-title">Ledger</h3></div>
    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead><tr><th>Date</th><th>Description</th><th>Category</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
                @forelse($entries as $e)
                <tr>
                    <td class="text-muted">{{ $e->entry_date->format('Y-m-d') }}</td>
                    <td>{{ $e->description }}</td>
                    <td><span class="badge bg-blue-lt">{{ str_replace('_',' ',$e->category) }}</span></td>
                    <td class="text-end text-red">{{ $e->debit > 0 ? number_format($e->debit, 0) : '' }}</td>
                    <td class="text-end text-green">{{ $e->credit > 0 ? number_format($e->credit, 0) : '' }}</td>
                    <td class="text-end"><strong>{{ number_format($e->balance, 0) }}</strong></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No passbook entries{{ $currentGroup ? ' in this group yet' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
