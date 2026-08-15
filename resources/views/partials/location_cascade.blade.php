{{-- 
    Cascading location selects: Province → District → Sector → Cell → Village.
    
    Inputs:
        $selected = ['province' => '...', 'district' => '...', 'sector' => '...', 'cell' => '...', 'village' => '...']
        $provinces = collection of Province models (passed by parent view)
    
    Submits five `*_code` hidden values via the selects themselves.
--}}
@php
    $sel = $selected ?? [];
    $sel = array_merge([
        'province' => null, 'district' => null, 'sector' => null, 'cell' => null, 'village' => null,
    ], $sel);
@endphp

<div class="col-md-4">
    <label class="form-label">Province</label>
    <select name="province_code" class="form-select" data-loc="province">
        <option value="">-- Select province --</option>
        @foreach(($provinces ?? []) as $p)
            <option value="{{ $p->code }}" @selected(old('province_code', $sel['province']) === $p->code)>{{ $p->name }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">District</label>
    <select name="district_code" class="form-select" data-loc="district"
            data-initial="{{ old('district_code', $sel['district']) }}"
            data-parent="{{ old('province_code', $sel['province']) }}">
        <option value="">-- Select district --</option>
    </select>
</div>

<div class="col-md-4">
    <label class="form-label">Sector</label>
    <select name="sector_code" class="form-select" data-loc="sector"
            data-initial="{{ old('sector_code', $sel['sector']) }}"
            data-parent="{{ old('district_code', $sel['district']) }}">
        <option value="">-- Select sector --</option>
    </select>
</div>

<div class="col-md-6">
    <label class="form-label">Cell</label>
    <select name="cell_code" class="form-select" data-loc="cell"
            data-initial="{{ old('cell_code', $sel['cell']) }}"
            data-parent="{{ old('sector_code', $sel['sector']) }}">
        <option value="">-- Select cell --</option>
    </select>
</div>

<div class="col-md-6">
    <label class="form-label">Village</label>
    <select name="village_code" class="form-select" data-loc="village"
            data-initial="{{ old('village_code', $sel['village']) }}"
            data-parent="{{ old('cell_code', $sel['cell']) }}">
        <option value="">-- Select village --</option>
    </select>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        const CHILDREN = { province: 'district', district: 'sector', sector: 'cell', cell: 'village' };
        const ENDPOINTS = {
            district: code => `/locations/districts/${encodeURIComponent(code)}`,
            sector:   code => `/locations/sectors/${encodeURIComponent(code)}`,
            cell:     code => `/locations/cells/${encodeURIComponent(code)}`,
            village:  code => `/locations/villages/${encodeURIComponent(code)}`,
        };

        async function loadOptions(selectEl, parentCode, preselect) {
            const level = selectEl.dataset.loc;
            const placeholder = selectEl.querySelector('option[value=""]');
            selectEl.innerHTML = '';
            if (placeholder) selectEl.appendChild(placeholder);
            else {
                const o = document.createElement('option');
                o.value = ''; o.textContent = `-- Select ${level} --`;
                selectEl.appendChild(o);
            }
            selectEl.disabled = true;

            if (!parentCode) { selectEl.disabled = false; return; }
            try {
                const res = await fetch(ENDPOINTS[level](parentCode), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Network error ' + res.status);
                const items = await res.json();
                for (const it of items) {
                    const opt = document.createElement('option');
                    opt.value = it.code; opt.textContent = it.name;
                    if (preselect && String(preselect) === String(it.code)) opt.selected = true;
                    selectEl.appendChild(opt);
                }
            } catch (e) { console.error('Cascade load failed:', e); }
            finally { selectEl.disabled = false; }
        }

        function attach(form) {
            const selects = {};
            form.querySelectorAll('select[data-loc]').forEach(s => selects[s.dataset.loc] = s);

            // When a parent changes, repopulate every downstream select.
            Object.entries(CHILDREN).forEach(([parentLevel, childLevel]) => {
                const parent = selects[parentLevel];
                const child  = selects[childLevel];
                if (!parent || !child) return;

                parent.addEventListener('change', async () => {
                    let cursorParent = parent.value;
                    let cursorChild  = childLevel;
                    while (cursorChild) {
                        const childSel = selects[cursorChild];
                        if (!childSel) break;
                        await loadOptions(childSel, cursorParent, null);
                        cursorParent = childSel.value; // probably empty after reset
                        cursorChild  = CHILDREN[cursorChild];
                    }
                });
            });

            // Initial chain load: walk from district → village honoring data-initial / data-parent.
            (async () => {
                for (const level of ['district', 'sector', 'cell', 'village']) {
                    const sel = selects[level];
                    if (!sel) continue;
                    const parentVal = sel.dataset.parent;
                    const initial   = sel.dataset.initial;
                    if (!parentVal) continue;
                    await loadOptions(sel, parentVal, initial);
                }
            })();
        }

        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('select[data-loc="province"]')) attach(form);
        });
    })();
    </script>
    @endpush
@endonce
