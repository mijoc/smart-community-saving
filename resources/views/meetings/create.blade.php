@extends('layouts.app')
@section('title','New meeting')
@section('content')

<x-page_header title="Schedule a meeting" pretitle="{{ $group->name }} · Attendance"></x-page_header>

@if($errors->any())
<div class="alert alert-danger mt-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('meetings.store') }}" class="card mt-3" style="max-width:760px">
    @csrf
    <input type="hidden" name="group_id" value="{{ $group->id }}">

    <div class="card-header">
        <h3 class="card-title">Meeting details</h3>
        <div class="card-subtitle text-muted">
            Roll-call rows will be created for every active member in <strong>{{ $group->name }}</strong>.
        </div>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required">Meeting date</label>
                <input type="date" name="meeting_date" class="form-control"
                       value="{{ old('meeting_date', now()->toDateString()) }}" required>
                @if($meetingDay)
                    <div class="form-hint">Group meeting day: <strong>{{ $meetingDay }}</strong></div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title') }}" placeholder="Weekly contribution day">
            </div>

            <div class="col-md-6">
                <label class="form-label required">Late fine ({{ $group->currency }})</label>
                <input type="number" min="0" step="0.01" name="late_fine" class="form-control"
                       value="{{ old('late_fine', $lateFine) }}" required>
                <div class="form-hint">Default from group rule <code>attendance_late_fine</code>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label required">Absent fine ({{ $group->currency }})</label>
                <input type="number" min="0" step="0.01" name="absent_fine" class="form-control"
                       value="{{ old('absent_fine', $absentFine) }}" required>
                <div class="form-hint">Default from group rule <code>attendance_absent_fine</code>.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Agenda / notes</label>
                <textarea name="agenda" rows="3" class="form-control" placeholder="Topics, special collections, visitors…">{{ old('agenda') }}</textarea>
            </div>
        </div>
    </div>

    <div class="card-footer text-end">
        <a href="{{ route('meetings.index') }}" class="btn btn-link">Cancel</a>
        <button class="btn btn-primary"><i class="ti ti-calendar-plus me-1"></i> Create meeting</button>
    </div>
</form>
@endsection
