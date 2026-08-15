@extends('layouts.app')
@section('title','Record payment')
@section('content')

<x-page_header title="Record payment" pretitle="Payments"></x-page_header>

<form method="POST" action="{{ route('payments.store') }}" class="card mt-3" id="payForm">@csrf
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label required">Group</label>
                <select id="group_id" name="group_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected($g->id == ($preselect_group_id ?? 0))>{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label required">Member</label>
                <select id="member_id" name="member_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach($members as $m)
                        <option value="{{ $m->id }}" @selected($m->id == ($preselect_member_id ?? 0))>{{ $m->full_name }} ({{ $m->member_no }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Apply to contribution (optional)</label>
                <select id="contribution_id" name="contribution_id" class="form-select">
                    <option value="">— general payment —</option>
                </select>
                <small class="text-muted">Pick group + member to load outstanding contributions.</small>
            </div>

            <div class="col-md-3"><label class="form-label required">Amount</label>
                <input type="number" step="0.01" name="amount" id="amount" class="form-control"
                       value="{{ $preselect_amount ? number_format((float)$preselect_amount, 2, '.', '') : '' }}" required></div>
            <div class="col-md-3"><label class="form-label required">Method</label>
                <select name="method" class="form-select">
                    @foreach(['cash','mobile_money','bank','cheque','other'] as $m)
                        <option value="{{ $m }}">{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Channel reference</label>
                <input name="channel_ref" class="form-control" placeholder="MM TX ID, slip #, etc."></div>
            <div class="col-md-3"><label class="form-label required">Paid on</label>
                <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control" required></div>

            <div class="col-md-4"><label class="form-label">Internal reference</label>
                <input name="reference" class="form-control" placeholder="auto"></div>
            <div class="col-md-8"><label class="form-label">Notes</label>
                <input name="notes" class="form-control"></div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="{{ route('payments.index') }}" class="btn">Cancel</a>
        <button class="btn btn-primary">Record payment</button>
    </div>
</form>

@push('scripts')
<script>
const groupSel   = document.getElementById('group_id');
const memberSel  = document.getElementById('member_id');
const contribSel = document.getElementById('contribution_id');
const amountEl   = document.getElementById('amount');

const preselect      = @json((int) ($preselect_contribution_id ?? 0));
const preselectGroup = @json((int) ($preselect_group_id ?? 0));
const preselectMember= @json((int) ($preselect_member_id ?? 0));

async function reload() {
    contribSel.innerHTML = '<option value="">— general payment —</option>';
    if (!groupSel.value || !memberSel.value) return;
    const url = new URL(@json(route('payments.lookup')), window.location.origin);
    url.searchParams.set('group_id', groupSel.value);
    url.searchParams.set('member_id', memberSel.value);
    const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const rows = await res.json();
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function fmtPeriod(start, end) {
        const s = new Date(start);
        const e = new Date(end);
        const sLabel = `${monthNames[s.getUTCMonth()]} ${s.getUTCFullYear()}`;
        const eLabel = `${monthNames[e.getUTCMonth()]} ${e.getUTCFullYear()}`;
        return sLabel === eLabel ? sLabel : `${sLabel} – ${eLabel}`;
    }
    function fmtNum(n) {
        return parseFloat(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    rows.forEach(r => {
        const balance = (parseFloat(r.expected_amount) + parseFloat(r.late_fee_amount) - parseFloat(r.paid_amount));
        const opt = document.createElement('option');
        opt.value = r.id;
        const period = fmtPeriod(r.period_start, r.period_end);
        const type   = r.type.charAt(0).toUpperCase() + r.type.slice(1).replace(/_/g, ' ');
        opt.textContent = `${period} · ${type} · bal ${fmtNum(balance)}`;
        opt.dataset.balance = balance.toFixed(2);
        contribSel.appendChild(opt);
    });
    if (preselect) {
        contribSel.value = preselect;
        contribSel.dispatchEvent(new Event('change'));
    }
}

contribSel.addEventListener('change', () => {
    const opt = contribSel.options[contribSel.selectedIndex];
    if (opt && opt.dataset.balance && !amountEl.value) amountEl.value = opt.dataset.balance;
});

groupSel.addEventListener('change', reload);
memberSel.addEventListener('change', reload);

// Auto-load contributions when arriving from a contribution row
if (preselectGroup && preselectMember) {
    reload();
}
</script>
@endpush
@endsection
