@php
    $group = $group ?? null;
@endphp
@csrf
<div class="row g-3">
    <div class="col-md-3"><label class="form-label">Code</label>
        <input name="code" value="{{ old('code', $group?->code ?? '') }}" class="form-control" placeholder="auto"></div>
    <div class="col-md-9"><label class="form-label required">Name</label>
        <input name="name" value="{{ old('name', $group?->name ?? '') }}" class="form-control" required></div>

    <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" rows="2" class="form-control">{{ old('description', $group?->description ?? '') }}</textarea></div>

    <div class="col-12">
        <hr class="mt-2 mb-1">
        <h4 class="mb-0">Location</h4>
        <small class="text-muted">Pick step by step — province first, then district, sector, cell and village.</small>
    </div>
    @include('partials.location_cascade', [
        'selected' => [
            'province' => $group?->province_code,
            'district' => $group?->district_code,
            'sector'   => $group?->sector_code,
            'cell'     => $group?->cell_code,
            'village'  => $group?->village_code,
        ],
    ])
    <div class="col-md-6"><label class="form-label">Country</label>
        <input name="country" value="{{ old('country', $group?->country ?? 'Rwanda') }}" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Region (optional)</label>
        <input name="region" value="{{ old('region', $group?->region ?? '') }}" class="form-control"></div>

    <div class="col-md-3"><label class="form-label required">Currency</label>
        <input name="currency" value="{{ old('currency', $group?->currency ?? config('vsla.currency')) }}" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label">Formed on</label>
        <input type="date" name="formed_on" value="{{ old('formed_on', $group?->formed_on?->format('Y-m-d') ?? '') }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Cycle starts</label>
        <input type="date" name="cycle_starts_on" value="{{ old('cycle_starts_on', $group?->cycle_starts_on?->format('Y-m-d') ?? '') }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Cycle ends</label>
        <input type="date" name="cycle_ends_on" value="{{ old('cycle_ends_on', $group?->cycle_ends_on?->format('Y-m-d') ?? '') }}" class="form-control"></div>

    <div class="col-md-3"><label class="form-label required">Status</label>
        <select name="status" class="form-select">
            @foreach(['active','paused','closed'] as $s)
                <option value="{{ $s }}" @selected(old('status', $group?->status ?? 'active')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
</div>
