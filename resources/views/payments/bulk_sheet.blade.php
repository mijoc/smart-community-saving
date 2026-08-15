<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Collection sheet · {{ $group->name }} · {{ $schedule->name }}</title>
<style>
    @page { size: A4 portrait; margin: 14mm 12mm; }
    * { box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
           font-size: 11pt; color: #111; margin: 0; padding: 14px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start;
              border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
    .header h1 { margin: 0 0 4px 0; font-size: 16pt; }
    .header .meta { font-size: 10pt; color: #444; line-height: 1.5; }
    .header .right { text-align: right; font-size: 10pt; }
    .summary { background: #f6f6f6; border: 1px solid #ddd; padding: 6px 10px;
               margin-bottom: 10px; font-size: 10pt; display: flex; gap: 18px; }
    .summary b { color: #000; }
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: middle; }
    thead th { background: #eee; text-align: left; }
    th.num, td.num { text-align: right; white-space: nowrap; }
    th.center, td.center { text-align: center; }
    .skip-row td { background: #fafafa; color: #777; }
    .signature { width: 22%; }
    .method { width: 14%; }
    .amount { width: 12%; }
    .seq { width: 4%; text-align: center; }
    .footer { margin-top: 18px; display: flex; justify-content: space-between;
              gap: 24px; font-size: 10pt; }
    .sigbox { flex: 1; }
    .sigbox .line { border-top: 1px solid #333; margin-top: 36px; padding-top: 4px; text-align: center; }
    .totals { margin-top: 14px; width: 50%; margin-left: auto; }
    .totals td { border: none; padding: 3px 6px; }
    .totals tr.sum td { border-top: 2px solid #111; font-weight: 700; }
    .toolbar { background: #fffae5; border: 1px solid #f0d050; padding: 8px 12px;
               border-radius: 4px; margin-bottom: 12px; font-size: 10pt;
               display: flex; gap: 10px; align-items: center; }
    .toolbar button { padding: 4px 12px; font-size: 10pt; cursor: pointer; }
    @media print {
        .toolbar { display: none !important; }
        body { padding: 0; }
    }
</style>
</head>
<body>

<div class="toolbar">
    <strong>Tip:</strong> press <kbd>Ctrl/Cmd + P</kbd> to print, or save as PDF from your browser's print dialog.
    <button onclick="window.print()">Print now</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="header">
    <div>
        <h1>Contribution collection sheet</h1>
        <div class="meta">
            <strong>{{ $group->name }}</strong>
            @if($group->village || $group->district) · {{ trim($group->village.', '.$group->district, ', ') }} @endif<br>
            Schedule: <strong>{{ $schedule->name }}</strong>
            ({{ ucfirst($schedule->frequency) }} · expected
            {{ number_format((float) $schedule->amount, 0) }}
            @if($group->currency) {{ $group->currency }} @endif)
        </div>
    </div>
    <div class="right">
        <div>Sheet date: <strong>{{ now()->format('d M Y') }}</strong></div>
        <div>Members: <strong>{{ $rows->count() }}</strong></div>
        <div>Outstanding total: <strong>{{ number_format((float) $rows->sum('balance'), 0) }}</strong></div>
    </div>
</div>

@php
    $payable    = $rows->filter(fn($r) => $r->contribution && $r->balance > 0);
    $upToDate   = $rows->count() - $payable->count();
    $expectedT  = $payable->sum(fn($r) => (float) $r->contribution->expected_amount + (float) $r->contribution->late_fee_amount);
    $alreadyT   = $payable->sum(fn($r) => (float) $r->contribution->paid_amount);
    $outstandT  = $payable->sum('balance');
@endphp

<div class="summary">
    <span><b>To collect:</b> {{ $payable->count() }} member(s)</span>
    <span><b>Already up to date:</b> {{ $upToDate }}</span>
    <span><b>Expected:</b> {{ number_format($expectedT, 0) }}</span>
    <span><b>Already paid:</b> {{ number_format($alreadyT, 0) }}</span>
    <span><b>Outstanding:</b> {{ number_format($outstandT, 0) }}</span>
</div>

<table>
    <thead>
        <tr>
            <th class="seq">#</th>
            <th>Member</th>
            <th>Member no</th>
            <th>Period</th>
            <th class="num">Outstanding</th>
            <th class="amount num">Amount paid</th>
            <th class="method center">Method</th>
            <th class="signature center">Signature</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $r)
            @php $c = $r->contribution; $hasDue = $c && $r->balance > 0; @endphp
            <tr class="{{ $hasDue ? '' : 'skip-row' }}">
                <td class="seq">{{ $i + 1 }}</td>
                <td>{{ $r->member->full_name }}</td>
                <td>{{ $r->member->member_no }}</td>
                <td>
                    @if($c)
                        {{ $c->period_start?->format('d M') }} – {{ $c->period_end?->format('d M Y') }}
                    @else
                        — up to date —
                    @endif
                </td>
                <td class="num">{{ $hasDue ? number_format($r->balance, 0) : '—' }}</td>
                <td class="amount"></td>
                <td class="method"></td>
                <td class="signature"></td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Cash collected:</td><td class="num">_______________________</td></tr>
    <tr><td>Mobile money collected:</td><td class="num">_______________________</td></tr>
    <tr><td>Bank / cheque collected:</td><td class="num">_______________________</td></tr>
    <tr class="sum"><td>Total collected:</td><td class="num">_______________________</td></tr>
</table>

<div class="footer">
    <div class="sigbox"><div class="line">Treasurer signature & date</div></div>
    <div class="sigbox"><div class="line">Secretary signature & date</div></div>
    <div class="sigbox"><div class="line">Chairperson signature & date</div></div>
</div>

</body>
</html>
