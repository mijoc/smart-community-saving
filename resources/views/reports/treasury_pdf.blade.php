<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Treasury Report — {{ $group->name }}</title>
<style>
    @page { margin: 12mm 10mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2d3d; font-size: 8.5px; margin: 0; }

    /* ── Header ─────────────────────────────── */
    .report-header { border-bottom: 2px solid #206bc4; padding-bottom: 6px; margin-bottom: 8px; }
    .report-header h1 { font-size: 15px; margin: 0 0 1px; color: #206bc4; }
    .report-header .sub { color: #697b8c; font-size: 8.5px; }
    .report-header .right { float: right; text-align: right; color: #697b8c; font-size: 8px; }
    .clearfix::after { content: ''; display: table; clear: both; }

    /* ── Section titles ──────────────────────── */
    h2 { font-size: 11px; margin: 12px 0 4px; color: #206bc4; border-left: 3px solid #206bc4; padding-left: 5px; }
    h3 { font-size: 9.5px; margin: 10px 0 3px; color: #1f2d3d; border-left: 3px solid #94a3b8; padding-left: 5px; }

    /* ── Tables ──────────────────────────────── */
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 3px 4px; vertical-align: middle; }
    thead th { background: #e8eef5; font-size: 7.5px; text-align: center; font-weight: bold; text-transform: uppercase; }
    td.num { text-align: right; }
    td.label { font-weight: bold; background: #f8fafc; }
    td.muted { color: #94a3b8; }
    td.bad  { color: #c0392b; font-weight: bold; }
    td.good { color: #1a7f4b; }

    /* ── Status badges ───────────────────────── */
    .badge-paid     { color: #1a7f4b; font-weight: bold; }
    .badge-pending  { color: #b45309; }
    .badge-overdue  { color: #c0392b; font-weight: bold; }
    .badge-partial  { color: #7c3aed; }
    .badge-waived   { color: #64748b; }
    .badge-active   { color: #206bc4; font-weight: bold; }
    .badge-paid-loan{ color: #1a7f4b; }
    .badge-default  { color: #c0392b; }

    /* ── Summary card grid ───────────────────── */
    .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .summary-grid td { border: 1px solid #cbd5e1; padding: 5px 7px; width: 25%; }
    .summary-grid .s-label { font-size: 7px; text-transform: uppercase; color: #697b8c; margin-bottom: 1px; }
    .summary-grid .s-val   { font-size: 12px; font-weight: bold; color: #1f2d3d; }
    .summary-grid .s-cur   { font-size: 8px; color: #697b8c; }

    /* ── Member page break ───────────────────── */
    .member-section { page-break-inside: avoid; margin-top: 10px; border: 1px solid #e2e8f0; padding: 6px; }
    .member-header  { background: #f1f5fb; padding: 4px 6px; margin-bottom: 5px; border-bottom: 1px solid #cbd5e1; }
    .member-header strong { font-size: 9.5px; }
    .member-header .meta { font-size: 7.5px; color: #697b8c; }

    /* ── All-members summary table ───────────── */
    .all-members th { background: #dde7f5; }
    .all-members tfoot td { background: #fef9c3; font-weight: bold; }
    .all-members tfoot td.num { text-align: right; }

    /* ── Footer ──────────────────────────────── */
    .report-footer { margin-top: 10px; color: #94a3b8; font-size: 7.5px; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 4px; }

    /* ── Totals row ──────────────────────────── */
    tfoot td { background: #fef9c3; font-weight: bold; border-top: 2px solid #94a3b8; }

    .no-data { color: #94a3b8; font-style: italic; padding: 6px; }

    /* ── Preview toolbar (hidden when printing / in PDF) ─── */
    @media print { .no-print { display: none !important; } }
    .preview-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 999;
        background: #1f2d3d; color: #fff; display: flex; align-items: center;
        gap: 10px; padding: 8px 16px; font-family: Arial, sans-serif; }
    .preview-bar .pb-title { flex: 1; font-weight: bold; font-size: 13px; }
    .preview-bar .pb-meta  { color: #94a3b8; font-size: 11px; }
    .preview-bar a.pb-back { color: #94a3b8; text-decoration: none; font-size: 13px; }
    .preview-bar a.pb-back:hover { color: #fff; }
    .preview-bar a.pb-dl   { background: #206bc4; color: #fff; padding: 6px 18px;
        border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; }
    .preview-bar a.pb-dl:hover { background: #1a58a0; }
    .preview-spacer { height: 44px; }
    @media screen { body { font-size: 10px; } }
</style>
</head>
<body>

@php
    $cur  = fn ($v) => number_format((float) $v, 0);
    $dec  = fn ($v) => number_format((float) $v, 2);
    $pct  = fn ($v) => number_format((float) $v, 1).'%';
    $date = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
    $contribBadge = function ($s) {
        return match ($s) {
            'paid'    => 'badge-paid',
            'pending' => 'badge-pending',
            'overdue' => 'badge-overdue',
            'partial' => 'badge-partial',
            'waived'  => 'badge-waived',
            default   => '',
        };
    };
    $loanBadge = function ($s) {
        return match ($s) {
            'paid'                  => 'badge-paid-loan',
            'disbursed','repaying'  => 'badge-active',
            'defaulted','written_off' => 'badge-default',
            default => '',
        };
    };
    $typeLabel = function ($t) {
        return ucfirst(str_replace('_', ' ', $t));
    };
@endphp

@if (isset($preview) && $preview)
<div class="no-print preview-bar">
    <a href="javascript:history.back()" class="pb-back">&#8592; Back</a>
    <span class="pb-title">{{ $group->name }} &mdash; Full Treasury Report</span>
    <span class="pb-meta">Preview &middot; {{ now()->format('d M Y') }}</span>
    <a href="{{ route('treasury.report.pdf') }}" class="pb-dl">&#8659;&nbsp; Download PDF</a>
</div>
<div class="preview-spacer"></div>
<style>
/* ─── Preview-only overrides (browser screen only; not emitted in PDF mode) ── */
body {
    font-size: 13px !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    background: #f0f2f5;
    padding: 24px !important;
}
/* Constrain to a readable column */
.report-wrapper { max-width: 1280px; margin: 0 auto; background: #fff; padding: 28px 32px; box-shadow: 0 1px 6px rgba(0,0,0,.12); border-radius: 6px; }

/* Headings */
.report-header h1 { font-size: 22px !important; }
.report-header .sub, .report-header .right { font-size: 12px !important; }
h2 { font-size: 17px !important; margin: 24px 0 10px !important; }
h3 { font-size: 14px !important; margin: 18px 0 7px !important; }

/* Summary card values */
.summary-grid .s-label { font-size: 10px !important; }
.summary-grid .s-val   { font-size: 20px !important; }
.summary-grid .s-cur   { font-size: 11px !important; }
.summary-grid td       { padding: 10px 14px !important; }

/* Tables */
th, td      { padding: 7px 10px !important; }
thead th    { font-size: 11.5px !important; }
td          { font-size: 13px !important; }
table       { margin-bottom: 14px !important; }

/* Member sections */
.member-section  { padding: 14px 18px !important; margin-top: 20px !important; border-radius: 5px !important; }
.member-header   { padding: 8px 12px !important; margin-bottom: 10px !important; border-radius: 4px 4px 0 0; }
.member-header strong { font-size: 15px !important; }
.member-header .meta  { font-size: 12px !important; }

/* Mini equity panel */
.member-section > table:first-of-type td { font-size: 13px !important; padding: 6px 10px !important; }

/* Outstanding warning */
.member-section p[style*="b45309"] { font-size: 12px !important; }

/* Footer */
.report-footer { font-size: 11px !important; }
.no-data       { font-size: 12px !important; }
</style>
@endif

@if (isset($preview) && $preview)<div class="report-wrapper">@endif
{{-- ══ REPORT HEADER ══════════════════════════════════════════════════════ --}}
<div class="report-header clearfix">
    <div class="right">
        Generated by {{ auth()->user()?->name ?? 'System' }}<br>
        {{ now()->format('d M Y, H:i') }}
    </div>
    <h1>&#36; {{ $group->name }} — Full Treasury Report</h1>
    <div class="sub">
        Group Treasury &amp; Member Financial Summary &nbsp;·&nbsp; Currency: {{ $currency }}
        &nbsp;·&nbsp; As at {{ now()->format('d M Y') }}
    </div>
</div>

{{-- ══ SECTION 1 — GROUP FINANCIAL SUMMARY ══════════════════════════════ --}}
<h2>1. Group Financial Summary</h2>

<table class="summary-grid">
    <tr>
        <td>
            <div class="s-label">Current Balance</div>
            <div class="s-val">{{ $cur($summary['cash_on_hand']) }}</div>
            <div class="s-cur">{{ $currency }} — Inflows minus outflows</div>
        </td>
        <td>
            <div class="s-label">Total Expected</div>
            <div class="s-val">{{ $cur($summary['total_expected_all']) }}</div>
            <div class="s-cur">{{ $currency }} — All booked inflows net expenses</div>
        </td>
        <td>
            <div class="s-label">Realized Profit</div>
            <div class="s-val">{{ $cur($summary['realized_profit']) }}</div>
            <div class="s-cur">{{ $currency }} — Penalties + interest + cashbook</div>
        </td>
        <td>
            <div class="s-label">Loans Outstanding (Principal)</div>
            <div class="s-val">{{ $cur($summary['principal_outstanding']) }}</div>
            <div class="s-cur">{{ $currency }} — {{ $summary['open_loans_count'] }} open loan(s)</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="s-label">Interest Receivable</div>
            <div class="s-val">{{ $cur($summary['interest_receivable']) }}</div>
            <div class="s-cur">{{ $currency }} — Unpaid interest on open loans</div>
        </td>
        <td>
            <div class="s-label">Total Expenses</div>
            <div class="s-val">{{ $cur($summary['cashbook_expense']) }}</div>
            <div class="s-cur">{{ $currency }} — All cashbook outflows</div>
        </td>
        <td>
            <div class="s-label">Open Arrears</div>
            <div class="s-val">{{ $cur($summary['open_arrears'] ?? 0) }}</div>
            <div class="s-cur">{{ $currency }} — Overdue + unpaid</div>
        </td>
        <td></td>
    </tr>
</table>

{{-- ══ SECTION 2 — ALL MEMBERS SUMMARY TABLE ════════════════════════════ --}}
<h2>2. All Members — Summary</h2>

<table class="all-members">
    <thead>
        <tr>
            <th rowspan="2" style="width:20px">#</th>
            <th rowspan="2" style="text-align:left; width:110px">Member</th>
            <th rowspan="2" style="width:40px">Mbr No.</th>
            <th colspan="4">Contributions</th>
            <th colspan="3">Loans</th>
            <th rowspan="2" style="width:55px">Total Debt</th>
            <th rowspan="2" style="width:60px">Proj. Payout</th>
        </tr>
        <tr>
            <th style="width:52px">Expected</th>
            <th style="width:52px">Paid</th>
            <th style="width:52px">Pending</th>
            <th style="width:52px">Overdue Amt</th>
            <th style="width:52px">Principal Left</th>
            <th style="width:52px">Interest Left</th>
            <th style="width:52px">Interest Rate</th>
        </tr>
    </thead>
    <tbody>
        @php
            $tot_exp = 0; $tot_paid = 0; $tot_pend = 0; $tot_ovd = 0;
            $tot_prin = 0; $tot_int = 0; $tot_debt = 0; $tot_payout = 0;
        @endphp
        @foreach ($memberData as $i => $row)
        @php
            $s   = $row['summary'];
            $cs  = $row['contribution_stats'];
            $tot_exp   += $cs['expected'];
            $tot_paid  += $cs['paid'];
            $tot_pend  += $cs['pending'];
            $tot_ovd   += $cs['overdue_amount'];
            $tot_prin  += $s['loan_principal_due'];
            $tot_int   += $s['loan_interest_due'];
            $tot_debt  += $s['total_debt'];
            $tot_payout += max(0, $s['projected_payout']);
        @endphp
        <tr>
            <td style="text-align:center">{{ $i + 1 }}</td>
            <td>{{ $row['member']->full_name }}</td>
            <td style="text-align:center">{{ $row['member']->member_no }}</td>
            <td class="num">{{ $cur($cs['expected']) }}</td>
            <td class="num good">{{ $cur($cs['paid']) }}</td>
            <td class="num {{ $cs['pending'] > 0 ? 'badge-pending' : '' }}">{{ $cur($cs['pending']) }}</td>
            <td class="num {{ $cs['overdue_amount'] > 0 ? 'bad' : '' }}">{{ $cur($cs['overdue_amount']) }}</td>
            <td class="num {{ $s['loan_principal_due'] > 0 ? 'badge-active' : '' }}">{{ $cur($s['loan_principal_due']) }}</td>
            <td class="num {{ $s['loan_interest_due'] > 0 ? 'badge-pending' : '' }}">{{ $cur($s['loan_interest_due']) }}</td>
            <td class="num">
                @if ($row['loans']->isNotEmpty())
                    {{ $pct($row['loans']->avg('interest_rate_pct')) }}
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="num {{ $s['total_debt'] > 0 ? 'bad' : '' }}">{{ $cur($s['total_debt']) }}</td>
            <td class="num {{ $s['projected_payout'] >= 0 ? 'good' : 'bad' }}">{{ $cur($s['projected_payout']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align:left">TOTALS ({{ count($memberData) }} members)</td>
            <td class="num">{{ $cur($tot_exp) }}</td>
            <td class="num">{{ $cur($tot_paid) }}</td>
            <td class="num">{{ $cur($tot_pend) }}</td>
            <td class="num">{{ $cur($tot_ovd) }}</td>
            <td class="num">{{ $cur($tot_prin) }}</td>
            <td class="num">{{ $cur($tot_int) }}</td>
            <td></td>
            <td class="num">{{ $cur($tot_debt) }}</td>
            <td class="num">{{ $cur($tot_payout) }}</td>
        </tr>
    </tfoot>
</table>

{{-- ══ SECTION 3 — PER-MEMBER DETAIL ════════════════════════════════════ --}}
<h2>3. Per-Member Detail</h2>

@foreach ($memberData as $i => $row)
@php
    $m   = $row['member'];
    $s   = $row['summary'];
    $cs  = $row['contribution_stats'];
@endphp

<div class="member-section" style="{{ $i > 0 ? 'page-break-before: always;' : '' }}">

    {{-- Member header bar --}}
    <div class="member-header clearfix">
        <div style="float:right; font-size:7.5px; color:#697b8c; text-align:right">
            Savings paid: <strong>{{ $cur($s['savings_paid']) }}</strong> {{ $currency }}
            &nbsp;|&nbsp; Social fund: <strong>{{ $cur($s['social_fund_paid']) }}</strong> {{ $currency }}
            &nbsp;|&nbsp; Total contributed: <strong>{{ $cur($s['total_contributed']) }}</strong> {{ $currency }}
        </div>
        <strong>{{ $i + 1 }}. {{ $m->full_name }}</strong>
        <span style="margin-left:6px; color:#697b8c; font-size:7.5px">#{{ $m->member_no }}</span>
        @if($m->phone)
            <span class="meta">&nbsp;·&nbsp; {{ $m->phone }}</span>
        @endif
    </div>

    {{-- Mini equity panel --}}
    <table style="width:50%; margin-bottom:6px;">
        <tr>
            <td class="label" style="width:50%">Projected share-out</td>
            <td class="num {{ $s['projected_payout'] >= 0 ? 'good' : 'bad' }}">
                {{ $cur($s['projected_payout']) }} {{ $currency }}
            </td>
        </tr>
        <tr>
            <td class="label">Total debt</td>
            <td class="num {{ $s['total_debt'] > 0 ? 'bad' : '' }}">
                {{ $cur($s['total_debt']) }} {{ $currency }}
            </td>
        </tr>
        <tr>
            <td class="label">Loan principal left</td>
            <td class="num">{{ $cur($s['loan_principal_due']) }} {{ $currency }}</td>
        </tr>
        <tr>
            <td class="label">Loan interest left</td>
            <td class="num">{{ $cur($s['loan_interest_due']) }} {{ $currency }}</td>
        </tr>
    </table>

    {{-- ── Contributions (grouped by type) ── --}}
    <h3>Contributions by Type</h3>
    @if ($row['contributions']->isEmpty())
        <p class="no-data">No contributions recorded.</p>
    @else
    @php
        $cTotLate = (float) $row['contributions']->sum('late_fee_amount');
        $cTotBal  = max(0, $cs['expected'] + $cTotLate - $cs['paid']);
    @endphp

    {{-- Summary table: one row per contribution type --}}
    <table>
        <thead>
            <tr>
                <th style="text-align:left; width:80px">Type</th>
                <th style="width:30px">Months</th>
                <th style="width:90px">Period</th>
                <th style="width:32px" class="good">Paid</th>
                <th style="width:36px" class="badge-pending">Pending</th>
                <th style="width:36px" class="bad">Overdue</th>
                <th style="width:36px" class="badge-partial">Partial</th>
                <th style="width:55px">Expected</th>
                <th style="width:55px">Paid Amt</th>
                <th style="width:45px">Late Fees</th>
                <th style="width:55px">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($row['contributions']->groupBy('type') as $cType => $cRows)
            @php
                $cSorted  = $cRows->sortBy('due_on');
                $cFirst   = $cSorted->first();
                $cLast    = $cSorted->last();
                $cRange   = $cFirst && $cFirst->period_start
                    ? \Carbon\Carbon::parse($cFirst->period_start)->format('M Y')
                      .($cLast && $cLast->period_start && $cLast->period_start !== $cFirst->period_start
                          ? ' – '.\Carbon\Carbon::parse($cLast->period_start)->format('M Y') : '')
                    : '—';
                $cPaid    = $cRows->where('status', 'paid')->count();
                $cPend    = $cRows->where('status', 'pending')->count();
                $cOvd     = $cRows->where('status', 'overdue')->count();
                $cPart    = $cRows->where('status', 'partial')->count();
                $cWaived  = $cRows->where('status', 'waived')->count();
                $cExp     = (float) $cRows->where('status', '!=', 'waived')->sum('expected_amount');
                $cPaidAmt = (float) $cRows->sum('paid_amount');
                $cLate    = (float) $cRows->sum('late_fee_amount');
                $cBal     = max(0, $cExp + $cLate - $cPaidAmt);
            @endphp
            <tr>
                <td><strong>{{ $typeLabel($cType) }}</strong></td>
                <td style="text-align:center">{{ $cRows->count() - $cWaived }}</td>
                <td style="font-size:7.5px">{{ $cRange }}</td>
                <td style="text-align:center" class="{{ $cPaid > 0 ? 'good' : 'muted' }}">{{ $cPaid ?: '—' }}</td>
                <td style="text-align:center" class="{{ $cPend > 0 ? 'badge-pending' : 'muted' }}">{{ $cPend ?: '—' }}</td>
                <td style="text-align:center" class="{{ $cOvd  > 0 ? 'bad'          : 'muted' }}">{{ $cOvd  ?: '—' }}</td>
                <td style="text-align:center" class="{{ $cPart > 0 ? 'badge-partial' : 'muted' }}">{{ $cPart ?: '—' }}</td>
                <td class="num">{{ $cur($cExp) }}</td>
                <td class="num good">{{ $cur($cPaidAmt) }}</td>
                <td class="num {{ $cLate > 0 ? 'bad' : 'muted' }}">{{ $cLate > 0 ? $cur($cLate) : '—' }}</td>
                <td class="num {{ $cBal > 0 ? 'bad' : 'good' }}">{{ $cBal > 0 ? $cur($cBal) : '&#10003;' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:left">Totals</td>
                <td style="text-align:center">{{ $cs['paid_count'] }}</td>
                <td style="text-align:center">{{ $cs['pending_count']  ?: '—' }}</td>
                <td style="text-align:center">{{ $cs['overdue_count']  ?: '—' }}</td>
                <td style="text-align:center">{{ $cs['partial_count']  ?: '—' }}</td>
                <td class="num">{{ $cur($cs['expected']) }}</td>
                <td class="num">{{ $cur($cs['paid']) }}</td>
                <td class="num">{{ $cur($cTotLate) }}</td>
                <td class="num {{ $cTotBal > 0 ? 'bad' : 'good' }}">{{ $cTotBal > 0 ? $cur($cTotBal) : '&#10003;' }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Outstanding rows: only pending / overdue / partial contributions --}}
    @php $outstanding = $row['contributions']->whereIn('status', ['pending','overdue','partial'])->sortBy('due_on'); @endphp
    @if ($outstanding->isNotEmpty())
    <p style="font-size:8px; margin:5px 0 2px; color:#b45309; font-weight:bold;">
        &#9888;&nbsp;{{ $outstanding->count() }} outstanding contribution(s) requiring attention:
    </p>
    <table>
        <thead>
            <tr>
                <th style="text-align:left; width:80px">Type</th>
                <th style="width:60px">Period</th>
                <th style="width:55px">Due</th>
                <th style="width:55px">Expected</th>
                <th style="width:55px">Paid</th>
                <th style="width:50px">Late Fee</th>
                <th style="width:55px">Balance</th>
                <th style="width:50px">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($outstanding as $oc)
            @php $oBal = max(0, (float)$oc->expected_amount + (float)$oc->late_fee_amount - (float)$oc->paid_amount); @endphp
            <tr>
                <td>{{ $typeLabel($oc->type) }}</td>
                <td style="text-align:center; font-size:7.5px">
                    {{ $oc->period_start ? \Carbon\Carbon::parse($oc->period_start)->format('M Y') : '—' }}
                </td>
                <td style="text-align:center">{{ $date($oc->due_on) }}</td>
                <td class="num">{{ $cur($oc->expected_amount) }}</td>
                <td class="num good">{{ $cur($oc->paid_amount) }}</td>
                <td class="num {{ (float)$oc->late_fee_amount > 0 ? 'bad' : 'muted' }}">
                    {{ (float)$oc->late_fee_amount > 0 ? $cur($oc->late_fee_amount) : '—' }}
                </td>
                <td class="num bad">{{ $cur($oBal) }}</td>
                <td style="text-align:center">
                    <span class="{{ $contribBadge($oc->status) }}">{{ ucfirst($oc->status) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endif

    {{-- ── Loans ── --}}
    <h3>Loans</h3>
    @if ($row['loans']->isEmpty())
        <p class="no-data">No loans recorded.</p>
    @else
    <table>
        <thead>
            <tr>
                <th style="text-align:left; width:70px">Reference</th>
                <th style="width:42px">Status</th>
                <th style="width:52px">Principal</th>
                <th style="width:35px">Rate %</th>
                <th style="width:52px">Total Interest</th>
                <th style="width:52px">Total Repayable</th>
                <th style="width:52px">Amount Repaid</th>
                <th style="width:52px">Outstanding</th>
                <th style="width:45px">Disbursed</th>
                <th style="width:45px">Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($row['loans'] as $loan)
            <tr>
                <td style="font-size:7.5px">{{ $loan->reference }}</td>
                <td style="text-align:center">
                    <span class="{{ $loanBadge($loan->status) }}">
                        {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                    </span>
                </td>
                <td class="num">{{ $cur($loan->principal) }}</td>
                <td style="text-align:center">{{ $pct($loan->interest_rate_pct) }}</td>
                <td class="num">{{ $cur($loan->total_interest) }}</td>
                <td class="num">{{ $cur($loan->total_repayable) }}</td>
                <td class="num good">{{ $cur($loan->amount_repaid) }}</td>
                <td class="num {{ (float)$loan->outstanding > 0 ? 'bad' : 'good' }}">
                    {{ $cur($loan->outstanding) }}
                </td>
                <td style="text-align:center">{{ $date($loan->disbursed_on) }}</td>
                <td style="text-align:center">{{ $date($loan->due_on) }}</td>
            </tr>
            @if($loan->repayments && $loan->repayments->isNotEmpty())
            {{-- Repayment sub-rows --}}
            @foreach($loan->repayments->sortByDesc('paid_on') as $rep)
            <tr style="background:#f8fafc;">
                <td style="font-size:7px; padding-left:12px; color:#697b8c">
                    ↳ Repayment {{ $date($rep->paid_on) }}
                </td>
                <td style="text-align:center; font-size:7px; color:#697b8c">
                    {{ ucfirst($rep->status ?? 'approved') }}
                </td>
                <td class="num" style="font-size:7.5px" colspan="2">
                    Total: {{ $cur($rep->amount) }}
                </td>
                <td class="num" style="font-size:7.5px; color:#697b8c">
                    Interest: {{ $cur($rep->interest_portion) }}
                </td>
                <td class="num" style="font-size:7.5px; color:#697b8c">
                    Principal: {{ $cur($rep->principal_portion) }}
                </td>
                <td colspan="4"></td>
            </tr>
            @endforeach
            @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align:left">Totals</td>
                <td></td>
                <td class="num">{{ $cur($row['loans']->sum('principal')) }}</td>
                <td></td>
                <td class="num">{{ $cur($row['loans']->sum('total_interest')) }}</td>
                <td class="num">{{ $cur($row['loans']->sum('total_repayable')) }}</td>
                <td class="num">{{ $cur($row['loans']->sum('amount_repaid')) }}</td>
                <td class="num">{{ $cur($row['loans']->sum('outstanding')) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
    @endif

</div>{{-- .member-section --}}
@endforeach

{{-- ══ REPORT FOOTER ═══════════════════════════════════════════════════ --}}
<div class="report-footer">
    {{ $group->name }} &nbsp;·&nbsp; Full Treasury Report &nbsp;·&nbsp;
    Generated by {{ auth()->user()?->name ?? 'System' }} on {{ now()->format('d M Y, H:i') }}
</div>
@if (isset($preview) && $preview)</div>@endif

</body>
</html>
