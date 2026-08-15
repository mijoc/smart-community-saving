@extends('layouts.app')
@section('title','Request a loan')
@section('content')

<x-page_header title="New loan request" pretitle="Loans"></x-page_header>

<form method="POST" action="{{ route('loans.store') }}" class="card mt-3">@csrf
    {{-- Always compound — no flat model --}}
    <input type="hidden" name="interest_model" value="compound">

    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label required">Group</label>
                <select name="group_id" class="form-select" required>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected(old('group_id', session('active_group_id'))==$g->id)>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label required">Member</label>
                <select name="member_id" class="form-select" required @if($lockMember) readonly @endif>
                    @foreach($members as $m)
                        <option value="{{ $m->id }}" @selected(old('member_id')==$m->id || $lockMember)>{{ $m->full_name }} ({{ $m->member_no }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label required">Principal amount</label>
                <input type="number" step="0.01" min="1" name="principal" id="principal-input"
                       value="{{ old('principal') }}" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label required">Monthly interest rate (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="interest_rate_pct" id="rate-input"
                       value="{{ old('interest_rate_pct', 5) }}" class="form-control" required>
                <div class="form-hint">Applied to the outstanding balance each month — interest on interest until fully repaid.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Request date</label>
                <input type="date" name="requested_on"
                       value="{{ old('requested_on', now()->toDateString()) }}"
                       class="form-control" max="{{ now()->toDateString() }}">
                <div class="form-hint">Defaults to today. Set a past date to backdate.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Estimated term (months) <span class="text-muted small">(optional)</span></label>
                <input type="number" min="1" max="120" name="term_months" id="term_months"
                       value="{{ old('term_months') }}" class="form-control" placeholder="Open-ended">
                <div class="form-hint">Leave blank — loan stays open until fully repaid.</div>
            </div>

            <div class="col-12">
                <div class="alert alert-info small mb-0" id="model-info">
                    <strong>Compound interest:</strong> Each month, <strong id="rate-preview">5%</strong>
                    of the current outstanding balance is added as interest.
                    If interest is not paid, it rolls into the balance and itself attracts interest next month.
                    There is no fixed end date — the loan closes when the balance reaches zero.
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Purpose</label>
                <textarea name="purpose" rows="3" class="form-control"
                          placeholder="What is the loan for?">{{ old('purpose') }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="{{ route('loans.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary"><i class="ti ti-send me-1"></i>Submit request</button>
    </div>
</form>

<script>
(function () {
    const rateInput = document.getElementById('rate-input');
    const ratePreview = document.getElementById('rate-preview');

    function update() {
        ratePreview.textContent = (parseFloat(rateInput.value) || 0) + '%';
    }

    rateInput.addEventListener('input', update);
    update();
})();
</script>

@endsection
