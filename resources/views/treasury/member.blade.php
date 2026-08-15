@extends('layouts.app')
@section('title', $member->first_name.' · My Equity')
@section('content')

@php
    $fmt      = fn ($v) => number_format((float) $v, 0);
    $s        = $summary;
    $cur      = $currency;
    $pct      = (float) $s['share_ratio_pct'];          // savings-based (info only)
    $members  = (int)   $s['active_member_count'];
    $payout   = (float) $s['projected_payout'];
    $hasDebt  = (float) $s['total_debt'] > 0;
@endphp

<style>
.eq-page { max-width:680px; margin:0 auto; }

/* Hero */
.eq-hero {
    background: linear-gradient(135deg,#1e1b4b 0%,#312e81 60%,#4338ca 100%);
    border-radius:18px;
    color:#fff;
    padding:1.5rem 1.5rem 1.75rem;
    margin-bottom:1rem;
    position:relative;
    overflow:hidden;
}
.eq-hero::after {
    content:'';position:absolute;right:-40px;top:-40px;
    width:180px;height:180px;border-radius:50%;
    background:rgba(255,255,255,.05);
}
.eq-hero .hero-label  { font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.6);margin-bottom:4px; }
.eq-hero .hero-value  { font-size:2.4rem;font-weight:800;line-height:1.1; }
.eq-hero .hero-sub    { font-size:.78rem;color:rgba(255,255,255,.55);margin-top:4px; }
.eq-hero .share-badge {
    display:inline-flex;align-items:center;gap:5px;
    background:rgba(255,255,255,.12);border-radius:20px;
    padding:4px 12px;font-size:.72rem;font-weight:600;
    border:1px solid rgba(255,255,255,.18);margin-top:.75rem;
}

/* Metric tiles */
.eq-tiles { display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1rem; }
@media(max-width:400px){ .eq-tiles { grid-template-columns:1fr 1fr; } }
.eq-tile {
    background:#fff;border-radius:12px;padding:.85rem .75rem;
    border:1px solid #e8eaed;text-align:center;
}
.eq-tile .tile-icon { font-size:1.4rem;margin-bottom:4px; }
.eq-tile .tile-val  { font-size:.95rem;font-weight:800;line-height:1.2; }
.eq-tile .tile-lbl  { font-size:.65rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-top:2px; }

/* Section cards */
.eq-section {
    background:#fff;border-radius:14px;border:1px solid #e8eaed;
    margin-bottom:1rem;overflow:hidden;
}
.eq-section-head {
    display:flex;align-items:center;gap:.5rem;
    padding:.75rem 1rem;border-bottom:1px solid #f0f2f4;
    font-weight:700;font-size:.82rem;color:#374151;
}
.eq-row {
    display:flex;align-items:center;justify-content:space-between;
    padding:.65rem 1rem;border-bottom:1px solid #f5f6f8;
}
.eq-row:last-child { border-bottom:none; }
.eq-row-label { font-size:.8rem;color:#374151;flex:1; }
.eq-row-sub   { font-size:.68rem;color:#9ca3af;margin-top:1px; }
.eq-row-val   { font-size:.85rem;font-weight:700;text-align:right;white-space:nowrap;margin-left:.5rem; }
.eq-row-total {
    display:flex;align-items:center;justify-content:space-between;
    padding:.75rem 1rem;background:#f8fafc;
}
.eq-row-total .eq-row-label { font-weight:700;font-size:.82rem; }
.eq-row-total .eq-row-val   { font-size:.95rem; }

/* Progress bar */
.eq-bar { height:4px;background:#e5e7eb;border-radius:4px;margin-top:6px;overflow:hidden; }
.eq-bar-fill { height:4px;border-radius:4px; }

/* Payout summary */
.eq-payout-calc {
    background:#f0fdf4;border-radius:14px;border:1px solid #bbf7d0;
    padding:1rem 1.1rem;margin-bottom:1rem;
}
.eq-payout-calc.negative { background:#fff1f2;border-color:#fecdd3; }
.eq-calc-row { display:flex;justify-content:space-between;align-items:center;padding:.3rem 0; }
.eq-calc-row .lbl { font-size:.8rem;color:#374151; }
.eq-calc-row .val { font-size:.82rem;font-weight:700;text-align:right; }
.eq-calc-divider { border:none;border-top:1px dashed #d1d5db;margin:.3rem 0; }
.eq-calc-total   { display:flex;justify-content:space-between;align-items:center;padding:.4rem 0 0; }
.eq-calc-total .lbl { font-size:.85rem;font-weight:700; }
.eq-calc-total .val { font-size:1.25rem;font-weight:800; }

/* Lifetime */
.eq-lifetime { display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem; }
.eq-stat-box {
    background:#fff;border-radius:12px;border:1px solid #e8eaed;
    padding:.85rem .75rem;
}
.eq-stat-box .s-lbl { font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;margin-bottom:2px; }
.eq-stat-box .s-val { font-size:.92rem;font-weight:800;color:#1e1b4b; }
</style>

<div class="eq-page py-3">

{{-- Group switcher (multi-group members) --}}
@if($memberGroups->count() > 1)
<form method="GET" class="d-flex align-items-center gap-2 mb-3">
    <label class="small text-muted mb-0">Group:</label>
    <select name="group_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        @foreach($memberGroups as $g)
            <option value="{{ $g->id }}" @selected($currentGroup?->id===$g->id)>{{ $g->name }}</option>
        @endforeach
    </select>
</form>
@endif

{{-- HERO — Projected Payout --}}
<div class="eq-hero">
    <div class="hero-label">{{ __('My Equity in') }} {{ $currentGroup?->name ?? __('Group') }}</div>
    <div class="hero-value {{ $payout < 0 ? 'text-danger' : '' }}">
        {{ $fmt($payout) }} <span style="font-size:1rem;font-weight:400;opacity:.7">{{ $cur }}</span>
    </div>
    <div class="hero-sub">{{ __('What you\'d receive if the group shared out today') }}</div>
    <div class="share-badge">
        <i class="ti ti-users"></i>
        1 {{ __('of') }} {{ $members }} {{ __('members') }} &nbsp;·&nbsp; {{ __('Total Expected') }}: {{ $fmt($s['group_total_expected']) }} {{ $cur }}
    </div>
</div>

{{-- 3 headline tiles --}}
<div class="eq-tiles">
    <div class="eq-tile">
        <div class="tile-icon">💰</div>
        <div class="tile-val" style="color:#4338ca">{{ $fmt($s['savings_paid']) }}</div>
        <div class="tile-lbl">{{ __('My Savings') }}</div>
    </div>
    <div class="eq-tile">
        <div class="tile-icon">📈</div>
        <div class="tile-val" style="color:#059669">+{{ $fmt($s['member_profit']) }}</div>
        <div class="tile-lbl">{{ __('Profit Share') }}</div>
    </div>
    <div class="eq-tile">
        <div class="tile-icon">{{ $hasDebt ? '⚠️' : '✅' }}</div>
        <div class="tile-val" style="color:{{ $hasDebt ? '#dc2626' : '#059669' }}">{{ $hasDebt ? '-'.$fmt($s['total_debt']) : $fmt(0) }}</div>
        <div class="tile-lbl">{{ __('My Debts') }}</div>
    </div>
</div>

{{-- EQUITY SECTION --}}
<div class="eq-section">
    <div class="eq-section-head">
        <span style="background:#ede9fe;border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center">
            <i class="ti ti-pig-money" style="color:#7c3aed;font-size:.9rem"></i>
        </span>
        {{ __('My Contributions (Equity)') }}
    </div>

    <div class="eq-row">
        <div>
            <div class="eq-row-label">{{ __('Savings contributions') }}</div>
            <div class="eq-bar"><div class="eq-bar-fill" style="background:#6366f1;width:{{ $s['group_total_savings'] > 0 ? min(100,round($s['savings_paid']/$s['group_total_savings']*100)) : 0 }}%"></div></div>
        </div>
        <div class="eq-row-val" style="color:#6366f1">{{ $fmt($s['savings_paid']) }} {{ $cur }}</div>
    </div>
    @if($s['social_fund_paid'] > 0)
    <div class="eq-row">
        <div>
            <div class="eq-row-label">{{ __('Social fund') }}</div>
            <div class="eq-row-sub">{{ __('Non-refundable') }}</div>
        </div>
        <div class="eq-row-val" style="color:#8b5cf6">{{ $fmt($s['social_fund_paid']) }} {{ $cur }}</div>
    </div>
    @endif
    @if($s['fines_paid'] > 0)
    <div class="eq-row">
        <div class="eq-row-label">{{ __('Fines & late fees paid') }}</div>
        <div class="eq-row-val" style="color:#9ca3af">{{ $fmt($s['fines_paid']) }} {{ $cur }}</div>
    </div>
    @endif

    <div class="eq-row">
        <div>
            <div class="eq-row-label">{{ __('Profit share') }} <span class="text-muted small">({{ __('info') }})</span></div>
            <div class="eq-row-sub">{{ __('Group profit') }} {{ $fmt($s['group_profit']) }} × {{ $pct }}%</div>
        </div>
        <div class="eq-row-val" style="color:#6b7280">{{ $fmt($s['member_profit']) }} {{ $cur }}</div>
    </div>

    <div class="eq-row" style="background:#f8f9ff">
        <div>
            <div class="eq-row-label fw-semibold">{{ __('Your equal share (1 of') }} {{ $members }})</div>
            <div class="eq-row-sub">{{ $fmt($s['group_total_expected']) }} {{ $cur }} ÷ {{ $members }} {{ __('members') }}</div>
        </div>
        <div class="eq-row-val" style="color:#4338ca;font-size:.95rem">{{ $fmt($s['gross_payout']) }} {{ $cur }}</div>
    </div>
</div>

{{-- DEBTS SECTION --}}
@if($hasDebt)
<div class="eq-section">
    <div class="eq-section-head">
        <span style="background:#fee2e2;border-radius:6px;width:26px;height:26px;display:flex;align-items:center;justify-content:center">
            <i class="ti ti-receipt" style="color:#dc2626;font-size:.9rem"></i>
        </span>
        {{ __('Outstanding Debts') }}
    </div>

    @if($s['loan_principal_due'] > 0)
    <div class="eq-row">
        <div class="eq-row-label">{{ __('Loan principal remaining') }}</div>
        <div class="eq-row-val" style="color:#dc2626">{{ $fmt($s['loan_principal_due']) }} {{ $cur }}</div>
    </div>
    @endif
    @if($s['loan_interest_due'] > 0)
    <div class="eq-row">
        <div class="eq-row-label">{{ __('Loan interest remaining') }}</div>
        <div class="eq-row-val" style="color:#ef4444">{{ $fmt($s['loan_interest_due']) }} {{ $cur }}</div>
    </div>
    @endif
    @if($s['contributions_due'] > 0)
    <div class="eq-row">
        <div>
            <div class="eq-row-label">{{ __('Unpaid contributions') }}</div>
            <div class="eq-row-sub">{{ __('Pending, partial or overdue') }}</div>
        </div>
        <div class="eq-row-val" style="color:#f59e0b">{{ $fmt($s['contributions_due']) }} {{ $cur }}</div>
    </div>
    @endif
    @if($s['attendance_fines_due'] > 0)
    <div class="eq-row">
        <div class="eq-row-label">{{ __('Attendance fines unpaid') }}</div>
        <div class="eq-row-val" style="color:#f59e0b">{{ $fmt($s['attendance_fines_due']) }} {{ $cur }}</div>
    </div>
    @endif

    <div class="eq-row-total">
        <div class="eq-row-label" style="color:#dc2626">{{ __('Total debts') }}</div>
        <div class="eq-row-val" style="color:#dc2626">−{{ $fmt($s['total_debt']) }} {{ $cur }}</div>
    </div>
</div>
@else
<div class="eq-section" style="border-color:#bbf7d0">
    <div class="eq-row" style="background:#f0fdf4">
        <div class="d-flex align-items-center gap-2">
            <i class="ti ti-circle-check text-success fs-4"></i>
            <div>
                <div class="eq-row-label fw-bold text-success">{{ __('No outstanding debts') }}</div>
                <div class="eq-row-sub">{{ __('You owe nothing to the group.') }}</div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- PAYOUT CALCULATION --}}
<div class="eq-payout-calc {{ $payout < 0 ? 'negative' : '' }}">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:.4rem;font-weight:600">
        {{ __('Share-out estimate') }}
    </div>
    <div class="eq-calc-row">
        <span class="lbl">{{ __('Your share') }} ({{ $fmt($s['group_total_expected']) }} ÷ {{ $members }})</span>
        <span class="val" style="color:#059669">{{ $fmt($s['gross_payout']) }} {{ $cur }}</span>
    </div>
    @if($hasDebt)
    <div class="eq-calc-row">
        <span class="lbl">{{ __('Less: outstanding debts') }}</span>
        <span class="val" style="color:#dc2626">−{{ $fmt($s['total_debt']) }} {{ $cur }}</span>
    </div>
    @endif
    <hr class="eq-calc-divider">
    <div class="eq-calc-total">
        <span class="lbl">{{ __('You would receive') }}</span>
        <span class="val" style="color:{{ $payout >= 0 ? '#059669' : '#dc2626' }}">{{ $fmt($payout) }} {{ $cur }}</span>
    </div>
</div>

{{-- LIFETIME STATS --}}
@if($s['loans_ever_borrowed'] > 0 || $s['interest_ever_paid'] > 0)
<div class="eq-lifetime">
    <div class="eq-stat-box">
        <div class="s-lbl">{{ __('Ever borrowed') }}</div>
        <div class="s-val">{{ $fmt($s['loans_ever_borrowed']) }}</div>
        <div style="font-size:.65rem;color:#9ca3af">{{ $cur }}</div>
    </div>
    <div class="eq-stat-box">
        <div class="s-lbl">{{ __('Interest ever paid') }}</div>
        <div class="s-val">{{ $fmt($s['interest_ever_paid']) }}</div>
        <div style="font-size:.65rem;color:#9ca3af">{{ $cur }}</div>
    </div>
</div>
@endif

{{-- FOOTER --}}
<div class="d-flex justify-content-between align-items-center gap-2 pb-2">
    <div class="small text-muted">{{ __('Updated in real time as payments and loans are recorded.') }}</div>
    <a href="{{ route('passbooks.show', $member) }}" class="btn btn-sm btn-outline-primary">
        <i class="ti ti-book me-1"></i>{{ __('Passbook') }}
    </a>
</div>

</div>
@endsection
