@php
    /** Single-sheet consolidated monthly report (Kinyarwanda layout).
     *  Expects: $report (from MonthlyReportService::generate), $currency.
     */
    $cur = fn ($n) => $n == 0 ? '' : number_format((float) $n, 0);
    $sheet  = $report['sheet'];
    $rows   = $sheet['rows'];
    $totals = $sheet['totals'];
    $sum    = $sheet['summary'];
    $monthLabel = $report['header']['month']->format('m/Y');
@endphp

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-bordered table-vcenter mb-0 small report-sheet">
            <thead>
                <tr class="text-center align-middle bg-light">
                    <th rowspan="2" style="min-width:90px">Ukwezi<br>{{ $monthLabel }}</th>
                    <th rowspan="2">No</th>
                    <th rowspan="2" class="text-start">Amazina</th>
                    <th colspan="5" class="bg-blue-lt">Ukwezi Gushize</th>
                    <th colspan="7" class="bg-yellow-lt">Uku Kwezi</th>
                    <th rowspan="2" class="bg-yellow-lt">Umwenda</th>
                </tr>
                <tr class="text-center small bg-light">
                    <th class="bg-blue-lt">Ibirarane arimo</th>
                    <th class="bg-blue-lt">Ibirarane byishywe</th>
                    <th class="bg-blue-lt">Inyungu y'inyungu kubukererwe</th>
                    <th class="bg-blue-lt">Inyungu y'inyungu kunguzanyo</th>
                    <th class="bg-blue-lt">Ubukererwe bwishyu</th>

                    <th class="bg-yellow-lt">Ayinjiye</th>
                    <th class="bg-yellow-lt">Inyungu n'ubukererwe</th>
                    <th class="bg-yellow-lt">Inguzanyo asanganywe</th>
                    <th class="bg-yellow-lt">Inguzanyo nshya itanzwe</th>
                    <th class="bg-yellow-lt">Inyungu kunguzanyo</th>
                    <th class="bg-yellow-lt">Inguzanyo yishyuwe</th>
                    <th class="bg-yellow-lt">Inyungu yishyuwe</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $i => $r)
                    <tr>
                        @if ($i === 0)
                            <td rowspan="{{ count($rows) }}" class="align-middle text-center fw-bold bg-light">
                                Ukwezi<br>{{ $monthLabel }}
                            </td>
                        @endif
                        <td class="text-center">{{ $r['no'] }}</td>
                        <td>{{ $r['member_name'] }}</td>
                        <td class="text-end">{{ $cur($r['last_arrears_in']) }}</td>
                        <td class="text-end">{{ $cur($r['last_arrears_paid']) }}</td>
                        <td class="text-end">{{ $cur($r['last_late_interest']) }}</td>
                        <td class="text-end">{{ $cur($r['last_loan_interest']) }}</td>
                        <td class="text-end">{{ $cur($r['last_arrears_outstanding']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_money_in']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_late_interest_in']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_loan_existing']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_loan_new']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_loan_interest_charged']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_loan_principal_paid']) }}</td>
                        <td class="text-end bg-yellow-lt-soft">{{ $cur($r['now_loan_interest_paid']) }}</td>
                        <td class="text-end fw-bold text-danger">{{ $cur($r['debt_now']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="text-center text-muted py-3">No members in this group.</td></tr>
                @endforelse
            </tbody>

            @if (count($rows))
                <tfoot class="fw-bold bg-yellow-lt">
                    <tr>
                        <td></td>
                        <td></td>
                        <td>Tatal</td>
                        <td class="text-end text-danger">{{ $cur($totals['last_arrears_in']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['last_arrears_paid']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['last_late_interest']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['last_loan_interest']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['last_arrears_outstanding']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_money_in']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_late_interest_in']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_loan_existing']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_loan_new']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_loan_interest_charged']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_loan_principal_paid']) }}</td>
                        <td class="text-end text-danger">{{ $cur($totals['now_loan_interest_paid']) }}</td>
                        <td class="text-end text-danger">{{ $cur($sum['umutungo_wose']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ---------- Summary block (Incomencyabuwe / Group balance) ---------- --}}
<div class="card mb-3" style="max-width:560px">
    <div class="table-responsive">
        <table class="table table-bordered mb-0 report-summary">
            <tbody>
                <tr>
                    <th class="bg-light" style="width:55%">Amafaranga yinjiye</th>
                    <td class="text-end fw-bold">{{ number_format($sum['amafaranga_yinjiye'], 0) }}</td>
                </tr>
                <tr>
                    <th class="bg-light">Amafaranga ari kuri konti</th>
                    <td class="text-end fw-bold">{{ number_format($sum['amafaranga_ari_kuti_konti'], 0) }}</td>
                </tr>
                <tr>
                    <th class="bg-light">Ayasohotse</th>
                    <td class="text-end fw-bold">{{ number_format($sum['ayasohotse'], 0) }}</td>
                </tr>
                <tr>
                    <th class="bg-light">Asigaye kuri konti</th>
                    <td class="text-end fw-bold">{{ number_format($sum['asigaye_kuri_konti'], 0) }}</td>
                </tr>
                <tr class="bg-yellow-lt">
                    <th>Umutungo wose</th>
                    <td class="text-end fw-bold h4 mb-0">
                        {{ number_format($sum['umutungo_wose'], 0) }} <small class="text-muted">{{ $currency }}</small>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="text-muted small text-end d-print-none">
    Generated by <strong>{{ $report['header']['generated_by'] }}</strong>
    on {{ $report['header']['generated_at']->toDayDateTimeString() }}
</div>

@push('head')
<style>
    .report-sheet th, .report-sheet td { vertical-align: middle; }
    .report-sheet thead th { font-size: .75rem; }
    .bg-yellow-lt-soft { background-color: rgba(255, 247, 209, .55); }
    @media print {
        .report-sheet { font-size: 10px; }
        .report-summary { font-size: 11px; }
    }
</style>
@endpush
