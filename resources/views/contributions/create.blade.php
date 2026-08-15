@extends('layouts.app')
@section('title','Manual contribution')
@section('content')
<x-page_header title="Manual contribution" pretitle="Contributions"></x-page_header>

<form method="POST" action="{{ route('contributions.store') }}" class="card mt-3">@csrf
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label required">Group</label>
                <select name="group_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label required">Member</label>
                <select name="member_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($members as $m)<option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->member_no }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label required">Type</label>
                <select name="type" class="form-select">
                    @foreach(['savings','social_fund','loan_repayment','fine','late_fee','other'] as $t)
                        <option value="{{ $t }}">{{ ucfirst(str_replace('_',' ',$t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label required">Expected amount</label>
                <input type="number" step="0.01" name="expected_amount" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label required">Period start</label>
                <input type="date" name="period_start" value="{{ now()->startOfWeek()->toDateString() }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label required">Period end</label>
                <input type="date" name="period_end"   value="{{ now()->endOfWeek()->toDateString() }}"   class="form-control" required></div>
            <div class="col-md-4"><label class="form-label required">Due on</label>
                <input type="date" name="due_on"       value="{{ now()->endOfWeek()->toDateString() }}"   class="form-control" required></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="{{ route('contributions.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Create</button>
    </div>
</form>
@endsection
