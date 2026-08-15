@extends('layouts.app')
@section('title','Edit schedule')
@section('content')
<x-page_header :title="'Edit '.$schedule->name" pretitle="Schedules"></x-page_header>

<form method="POST" action="{{ route('groups.schedules.update', [$group, $schedule]) }}" class="card mt-3">@method('PUT')
    <div class="card-body">@include('schedules._form')</div>
    <div class="card-footer text-end">
        <a href="{{ route('groups.schedules.index', $group) }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Save</button>
    </div>
</form>

{{-- Reset pointer (danger zone) --}}
<div class="card mt-4 border-danger">
    <div class="card-header bg-danger-subtle">
        <h4 class="card-title text-danger mb-0"><i class="ti ti-clock-rewind me-2"></i>Reset generation pointer</h4>
        <p class="card-subtitle text-muted mt-1 mb-0 small">
            Moves the <strong>next_due_on</strong> pointer backward (or forward) to any date.
            After resetting, use <em>Catch up missed periods</em> on the schedules list to regenerate contributions from the new start point.
            Currently: <strong>{{ $schedule->next_due_on?->format('Y-m-d') ?? '—' }}</strong>
        </p>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('groups.schedules.reset-pointer', [$group, $schedule]) }}"
              onsubmit="return confirmReset(this)">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label required">Reset next_due_on to</label>
                    <input type="date" name="reset_to" class="form-control"
                           value="{{ $schedule->start_date->format('Y-m-d') }}" required>
                    <small class="text-muted">Must be on or after the schedule's start date ({{ $schedule->start_date->format('Y-m-d') }}).</small>
                </div>
                <div class="col-md-5">
                    <label class="form-label d-block">&nbsp;</label>
                    <label class="form-check">
                        <input type="checkbox" name="delete_ahead" value="1" class="form-check-input" id="deleteAhead">
                        <span class="form-check-label">
                            Also delete <strong>pending / partial</strong> contributions from the new date onward
                            <span class="text-muted">(paid contributions are never touched)</span>
                        </span>
                    </label>
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="ti ti-clock-rewind me-1"></i>Reset pointer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function confirmReset(form) {
    const date  = form.reset_to.value;
    const del   = form.delete_ahead.checked;
    let msg = `Reset the generation pointer to ${date}?`;
    if (del) msg += `\n\nWARNING: All pending/partial contributions from ${date} onward will be permanently deleted.`;
    msg += `\n\nContinue?`;
    return confirm(msg);
}
</script>
@endsection
