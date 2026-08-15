@extends('layouts.app')
@section('title', 'Rotations')
@section('content')

<x-page_header title="Rotation payouts" pretitle="Merry-go-round">
    <x-slot name="actions">
        @include('partials._report_downloads', ['report' => 'rotations'])
        @include('partials._report_downloads', ['report' => 'rotation_payouts', 'label' => 'Payouts download', 'btnClass' => 'btn-outline-secondary'])
        @can('create', App\Models\Rotation::class)
        <a href="{{ route('rotations.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> New rotation
        </a>
        @endcan
    </x-slot>
</x-page_header>

<form method="GET" class="card mt-3">
    <div class="card-body row g-2 align-items-center">
        <div class="col-auto"><label class="col-form-label">Status:</label></div>
        <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach(['active','completed','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Group</th>
                    <th>Cadence</th>
                    <th>Rule</th>
                    <th>Members</th>
                    <th>Progress</th>
                    <th>Next turn</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rotations as $r)
                    <tr>
                        <td><a href="{{ route('rotations.show', $r) }}" class="text-decoration-none fw-bold">{{ $r->name }}</a></td>
                        <td>{{ $r->group?->name }}</td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $r->frequencyLabel() }}</span>
                            <span class="text-muted small">· {{ $r->recipients_per_turn }}/turn</span>
                        </td>
                        <td class="small">{{ $r->disbursementLabel() }}</td>
                        <td>{{ $r->members->count() }}</td>
                        <td>
                            <span class="text-muted small">
                                {{ $r->paid_turns_count }} / {{ $r->members->count() > 0 ? ceil($r->members->count() / max(1, $r->recipients_per_turn)) : 0 }} paid
                            </span>
                        </td>
                        <td class="text-muted">{{ $r->next_turn_on?->format('Y-m-d') ?: '—' }}</td>
                        <td><span class="badge bg-{{ $r->statusBadge() }}-lt">{{ ucfirst($r->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('rotations.show', $r) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        No rotations yet.
                        @can('create', App\Models\Rotation::class)
                            <a href="{{ route('rotations.create') }}">Create one</a>.
                        @endcan
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $rotations->links() }}</div>
</div>

@endsection
