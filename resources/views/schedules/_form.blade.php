@csrf
@php($schedule = $schedule ?? new \App\Models\ContributionSchedule())
<div class="row g-3">
    <div class="col-md-6"><label class="form-label required">Name</label>
        <input name="name" value="{{ old('name', $schedule->name ?? '') }}" class="form-control" required></div>

    <div class="col-md-3"><label class="form-label required">Type</label>
        <select name="type" class="form-select">
            @foreach(['savings','social_fund','loan_repayment','fine','other'] as $t)
                <option value="{{ $t }}" @selected(old('type', $schedule->type ?? '')===$t)>{{ ucfirst(str_replace('_',' ',$t)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3"><label class="form-label required">Frequency</label>
        <select name="frequency" class="form-select">
            @foreach(config('vsla.frequencies') as $k => $v)
                <option value="{{ $k }}" @selected(old('frequency', $schedule->frequency ?? '')===$k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3"><label class="form-label required">Amount</label>
        <input type="number" step="0.01" name="amount" value="{{ old('amount', $schedule->amount ?? 0) }}" class="form-control" required></div>

    <div class="col-md-3"><label class="form-label required">Start date</label>
        <input type="date" name="start_date" value="{{ old('start_date', $schedule->start_date?->format('Y-m-d') ?? now()->toDateString()) }}" class="form-control" required></div>

    <div class="col-md-3"><label class="form-label">End date</label>
        <input type="date" name="end_date" value="{{ old('end_date', $schedule->end_date?->format('Y-m-d') ?? '') }}" class="form-control"></div>

    <div class="col-md-3"><label class="form-label required">Grace days</label>
        <input type="number" name="grace_days" value="{{ old('grace_days', $schedule->grace_days ?? config('vsla.grace_days')) }}" class="form-control" min="0" required></div>

    <div class="col-md-3"><label class="form-label required">Late fee %</label>
        <input type="number" step="0.01" name="late_fee_pct" value="{{ old('late_fee_pct', $schedule->late_fee_pct ?? config('vsla.late_fee_pct')) }}" class="form-control" required></div>

    <div class="col-md-3"><label class="form-label required">Late fee flat</label>
        <input type="number" step="0.01" name="late_fee_flat" value="{{ old('late_fee_flat', $schedule->late_fee_flat ?? 0) }}" class="form-control" required></div>

    <div class="col-md-3"><label class="form-label">Active</label>
        <label class="form-check form-switch mt-2"><input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $schedule->is_active ?? true))>
            <span class="form-check-label">Generate contributions</span>
        </label>
    </div>
</div>
