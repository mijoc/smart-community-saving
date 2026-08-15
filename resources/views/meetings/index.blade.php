@extends('layouts.app')
@section('title','Attendance')
@section('content')

<x-page_header title="Attendance" pretitle="Contribution-day meetings">
    <x-slot name="actions">
        @can('create', App\Models\Meeting::class)
        <a href="{{ route('meetings.create') }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i> New meeting
        </a>
        @endcan
    </x-slot>
</x-page_header>

@if(session('status'))<div class="alert alert-success mt-3">{{ session('status') }}</div>@endif
@if(session('error')) <div class="alert alert-danger  mt-3">{{ session('error')  }}</div>@endif

<div class="card mt-3">
    <div class="card-body border-bottom py-3">
        <form class="row g-2" method="GET">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="open"   @selected(request('status')==='open')>Open</option>
                    <option value="closed" @selected(request('status')==='closed')>Closed</option>
                </select>
            </div>
            <div class="col-md-3"><input type="date" name="from" value="{{ request('from') }}" class="form-control" placeholder="From"></div>
            <div class="col-md-3"><input type="date" name="to"   value="{{ request('to')   }}" class="form-control" placeholder="To"></div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary"><i class="ti ti-filter me-1"></i> Filter</button>
                <a href="{{ route('meetings.index') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table card-table table-vcenter text-nowrap">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th class="text-end">Present</th>
                    <th class="text-end">Late</th>
                    <th class="text-end">Absent</th>
                    <th class="text-end">Excused</th>
                    <th class="text-end">Fines</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($meetings as $m)
                @php
                    $outstanding = max(0, (float)($m->fines_total ?? 0) - (float)($m->fines_paid ?? 0));
                    $cur = $m->group->currency ?? '';
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('meetings.show', $m) }}" class="fw-semibold text-reset">
                            {{ $m->meeting_date->format('D · M j, Y') }}
                        </a>
                        <div class="text-muted small">{{ $m->group->name }}</div>
                    </td>
                    <td class="text-truncate" style="max-width:260px">{{ $m->title }}</td>
                    <td class="text-end"><span class="badge bg-green-lt">{{ $m->present_count }}</span></td>
                    <td class="text-end"><span class="badge bg-orange-lt">{{ $m->late_count }}</span></td>
                    <td class="text-end"><span class="badge bg-red-lt">{{ $m->absent_count }}</span></td>
                    <td class="text-end"><span class="badge bg-azure-lt">{{ $m->excused_count }}</span></td>
                    <td class="text-end">{{ number_format((float)($m->fines_total ?? 0), 0) }} {{ $cur }}</td>
                    <td class="text-end text-success">{{ number_format((float)($m->fines_paid ?? 0), 0) }}</td>
                    <td class="text-end {{ $outstanding > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ number_format($outstanding, 0) }}
                    </td>
                    <td>
                        @if($m->status === 'open')
                            <span class="badge bg-blue-lt">Open</span>
                        @else
                            <span class="badge bg-secondary-lt">Closed</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('meetings.show', $m) }}">
                            Open
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">
                    No meetings yet.
                    @can('create', App\Models\Meeting::class)
                    <a href="{{ route('meetings.create') }}">Create the first one →</a>
                    @endcan
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer">{{ $meetings->links() }}</div>
</div>
@endsection
