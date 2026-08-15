@php
    $cur  = fn ($n) => number_format((float) $n, 0);
    $k    = $report['kpis'];
    $c    = $report['section_c'];
    $hdr  = $report['header'];

    // Index all per-section rows by member_no for easy lookup
    $aMap = collect($report['section_a']['rows'])->keyBy('member_no');
    $bMap = collect($report['section_b']['rows'])->keyBy('member_no');
    $dMap = collect($report['section_d']['rows'])->keyBy('member_no');
    $eMap = collect($report['section_e']['rows'])->keyBy('member_no');
@endphp

{{-- ══════════════════════════════════════════════════════════════════
     GROUP IDENTITY HEADER
══════════════════════════════════════════════════════════════════ --}}
<div class="card mb-3 border-0 shadow-sm" style="background:linear-gradient(135deg,#1a56db 0%,#1e3a8a 100%)">
    <div class="card-body py-4 px-4">
        <div class="row align-items-center">
            <div class="col">
                <div class="text-white-50 small text-uppercase fw-bold mb-1">Official Monthly Financial Report</div>
                <h1 class="text-white mb-1 fw-bold">{{ $hdr['group']->name }}</h1>
                <div class="text-white-50">
                    @if($hdr['group']->code) <span class="me-3"><i class="ti ti-id me-1"></i>{{ $hdr['group']->code }}</span> @endif
                    @if($hdr['group']->location ?? null) <span><i class="ti ti-map-pin me-1"></i>{{ $hdr['group']->location }}</span> @endif
                </div>
            </div>
            <div class="col-auto text-end">
                <div class="text-white-50 small mb-1">Reporting Period</div>
                <div class="text-white h2 mb-0 fw-bold">{{ $hdr['month_label'] }}</div>
                <div class="text-white-50 small mt-1">Currency: <strong class="text-white">{{ $currency }}</strong></div>
            </div>
            <div class="col-auto d-print-none">
                <div class="bg-white bg-opacity-10 rounded p-3 text-center">
                    <div class="text-white-50 small">Members</div>
                    <div class="text-white h2 fw-bold mb-0">{{ $k['total_members'] }}</div>
                    @if($k['overdue_members'] > 0)
                        <div class="badge bg-danger mt-1">{{ $k['overdue_members'] }} overdue</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     KPI CARDS — ROW 1
══════════════════════════════════════════════════════════════════ --}}
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #1a56db!important">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-blue text-white me-2"><i class="ti ti-trending-up"></i></span>
                    <span class="text-muted small fw-medium">Expected Income</span>
                </div>
                <div class="h2 mb-0 fw-bold text-blue">{{ $cur($k['total_expected_income']) }}</div>
                <div class="text-muted" style="font-size:.72rem">Total cycle-to-date expected</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #2fb344!important">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-green text-white me-2"><i class="ti ti-wallet"></i></span>
                    <span class="text-muted small fw-medium">Cash on Hand</span>
                </div>
                <div class="h2 mb-0 fw-bold text-green">{{ $cur($k['closing_balance']) }}</div>
                <div class="text-muted" style="font-size:.72rem">Opening {{ $cur($k['opening_balance']) }} → Closing</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f76707!important">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-orange text-white me-2"><i class="ti ti-cash-banknote"></i></span>
                    <span class="text-muted small fw-medium">Loans Outstanding</span>
                </div>
                <div class="h2 mb-0 fw-bold text-orange">{{ $cur($k['total_loans_out']) }}</div>
                <div class="text-muted" style="font-size:.72rem">Principal still owed to the group</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #d63939!important">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm bg-red text-white me-2"><i class="ti ti-alert-triangle"></i></span>
                    <span class="text-muted small fw-medium">Contribution Arrears</span>
                </div>
                <div class="h2 mb-0 fw-bold text-red">{{ $cur($k['total_arrears']) }}</div>
                <div class="text-muted" style="font-size:.72rem">Incl. penalties {{ $cur($k['total_penalties']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     MASTER MEMBER STATUS TABLE  (A + B + D + E merged)
══════════════════════════════════════════════════════════════════ --}}
@php
    $prevMonthLabel = $hdr['month']->copy()->subMonth()->format('M Y');

    $mTot = [
        'prev_outstanding'=> 0.0,
        'prev_penalty'    => 0.0,
        'expected'        => 0.0,
        'paid_month'      => 0.0,
        'loans_issued'    => 0.0,
        'principal_rep'   => 0.0,
        'interest'        => 0.0,
        'savings_balance' => 0.0,
        'loan_outstanding'=> 0.0,
        'total_credit'    => 0.0,
    ];
@endphp

<div class="card mb-3 shadow-sm">
    <div class="card-header py-3" style="background:linear-gradient(90deg,#1a56db,#1e40af);color:#fff">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="card-title fw-bold m-0 text-white">
                    <i class="ti ti-users me-2"></i>Member Monthly Status — {{ $hdr['month_label'] }}
                </h3>
                <div class="small" style="color:rgba(255,255,255,.65)">
                    Carry-forward from {{ $prevMonthLabel }} · this month contributions &amp; loans · cycle-to-date equity &amp; total credit
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-bordered table-sm mb-0" style="font-size:.78rem;white-space:nowrap">
            <thead>
                <tr style="font-size:.70rem;text-transform:uppercase;letter-spacing:.03em">
                    <th colspan="2" style="background:#f1f5f9;border-bottom:0"></th>
                    <th colspan="2" class="text-center py-2" style="background:#eff6ff;color:#2563eb;border-bottom:2px solid #93c5fd">
                        <i class="ti ti-clock-backward me-1"></i>Month Closed — {{ $prevMonthLabel }}
                    </th>
                    <th colspan="2" class="text-center py-2" style="background:#f0fdf4;color:#16a34a;border-bottom:2px solid #86efac">
                        <i class="ti ti-calendar-check me-1"></i>{{ $hdr['month_label'] }} — Contributions
                    </th>
                    <th colspan="3" class="text-center py-2" style="background:#fff7ed;color:#ea580c;border-bottom:2px solid #fdba74">
                        <i class="ti ti-cash me-1"></i>Loans &amp; Repayments
                    </th>
                    <th colspan="2" class="text-center py-2" style="background:#faf5ff;color:#9333ea;border-bottom:2px solid #d8b4fe">
                        <i class="ti ti-chart-line me-1"></i>Cycle-to-Date
                    </th>
                    <th colspan="1" class="text-center py-2" style="background:#fef2f2;color:#b91c1c;border-bottom:2px solid #fca5a5">
                        <i class="ti ti-scale me-1"></i>Position
                    </th>
                </tr>
                <tr style="font-size:.72rem">
                    <th class="text-center py-2" style="background:#f1f5f9;min-width:28px">#</th>
                    <th class="py-2" style="background:#f1f5f9;min-width:140px">Member Name</th>
                    <th class="text-end py-2" style="background:#eff6ff;min-width:90px;color:#dc2626" title="Total unpaid contribution debt carried into this month">Outstanding</th>
                    <th class="text-end py-2" style="background:#eff6ff;min-width:85px;color:#ea580c" title="Total accumulated late-fee penalties (outstanding + collected)">Penalty (Total)</th>
                    <th class="text-end py-2" style="background:#f0fdf4;min-width:85px" title="Total contributions scheduled for this month">Expected</th>
                    <th class="text-end py-2" style="background:#f0fdf4;min-width:85px;color:#16a34a" title="Cash actually received from this member this month">Paid</th>
                    <th class="text-end py-2" style="background:#fff7ed;min-width:85px" title="New loan principal disbursed to member this month">Loans Issued</th>
                    <th class="text-end py-2" style="background:#fff7ed;min-width:85px;color:#16a34a" title="Loan principal repaid this month">Principal Rep.</th>
                    <th class="text-end py-2" style="background:#fff7ed;min-width:75px;color:#9333ea" title="Interest collected this month">Interest</th>
                    <th class="text-end py-2" style="background:#faf5ff;min-width:90px;color:#2563eb" title="Total savings accumulated since cycle start">Savings Bal.</th>
                    <th class="text-end py-2" style="background:#faf5ff;min-width:90px;color:#dc2626" title="Current loan principal still owed to the group">Loan Out.</th>
                    <th class="text-end py-2 fw-bold" style="background:#fef2f2;min-width:95px;color:#b91c1c" title="Total credit = contribution outstanding + loan outstanding">Total Credit</th>
                </tr>
            </thead>
            <tbody>
            @php $i = 1; @endphp
            @foreach($report['members'] as $m)
            @php
                $a = $aMap->get($m->member_no, []);
                $b = $bMap->get($m->member_no, []);
                $d = $dMap->get($m->member_no, []);
                $e = $eMap->get($m->member_no, []);

                $outstanding = (float)($e['outstanding']      ?? 0);
                $penalty     = (float)($e['penalty']          ?? 0);
                $expected    = (float)($e['expected']         ?? 0);
                $paidM       = (float)($e['paid']             ?? 0);
                $loansIssued = (float)($b['loans_issued']      ?? 0);
                $principalRep= (float)($b['loans_repaid']      ?? 0);
                $interest    = (float)($b['interest_collected'] ?? 0);
                $savBal      = (float)($d['savings']           ?? 0);
                $loanOut     = (float)($d['outstanding']       ?? 0);
                $totalCredit = $outstanding + $loanOut;
                $status      = $e['status'] ?? 'none';

                $mTot['prev_outstanding'] += $outstanding;
                $mTot['prev_penalty']     += $penalty;
                $mTot['expected']         += $expected;
                $mTot['paid_month']       += $paidM;
                $mTot['loans_issued']     += $loansIssued;
                $mTot['principal_rep']    += $principalRep;
                $mTot['interest']         += $interest;
                $mTot['savings_balance']  += $savBal;
                $mTot['loan_outstanding'] += $loanOut;
                $mTot['total_credit']     += $totalCredit;

                $rowBg = $status === 'overdue' ? '#fff1f2' : ($status === 'pending' ? '#fffbeb' : '');
            @endphp
            <tr style="{{ $rowBg ? 'background:'.$rowBg : '' }}">
                <td class="text-center text-muted">{{ $i++ }}</td>
                <td class="fw-semibold">{{ $m->full_name }}</td>
                <td class="text-end fw-semibold {{ $outstanding > 0 ? 'text-danger' : 'text-muted' }}" style="background:#f5f8ff">{{ $outstanding > 0 ? $cur($outstanding) : '—' }}</td>
                <td class="text-end {{ $penalty > 0 ? 'text-orange fw-semibold' : 'text-muted' }}" style="background:#f5f8ff">{{ $penalty > 0 ? '+'.$cur($penalty) : '—' }}</td>
                <td class="text-end">{{ $expected > 0 ? $cur($expected) : '—' }}</td>
                <td class="text-end text-success fw-semibold">{{ $paidM > 0 ? $cur($paidM) : '—' }}</td>
                <td class="text-end {{ $loansIssued > 0 ? 'text-orange fw-semibold' : 'text-muted' }}" style="background:#fffbf5">{{ $loansIssued > 0 ? $cur($loansIssued) : '—' }}</td>
                <td class="text-end text-success" style="background:#fffbf5">{{ $principalRep > 0 ? $cur($principalRep) : '—' }}</td>
                <td class="text-end text-purple" style="background:#fffbf5">{{ $interest > 0 ? $cur($interest) : '—' }}</td>
                <td class="text-end text-blue fw-semibold" style="background:#faf5ff">{{ $savBal > 0 ? $cur($savBal) : '—' }}</td>
                <td class="text-end {{ $loanOut > 0 ? 'text-danger fw-semibold' : 'text-muted' }}" style="background:#faf5ff">{{ $loanOut > 0 ? $cur($loanOut) : '—' }}</td>
                <td class="text-end fw-bold {{ $totalCredit > 0 ? 'text-danger' : 'text-muted' }}" style="background:#fff5f5">{{ $totalCredit > 0 ? $cur($totalCredit) : '—' }}</td>
            </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;font-size:.78rem;background:#f0f4ff;border-top:2px solid #93c5fd">
                    <td colspan="2" class="py-2 ps-3" style="color:#1a56db"><i class="ti ti-sum me-1"></i>TOTALS</td>
                    <td class="text-end text-danger">{{ $mTot['prev_outstanding'] > 0 ? $cur($mTot['prev_outstanding']) : '—' }}</td>
                    <td class="text-end text-orange">{{ $mTot['prev_penalty'] > 0 ? '+'.$cur($mTot['prev_penalty']) : '—' }}</td>
                    <td class="text-end">{{ $cur($mTot['expected']) }}</td>
                    <td class="text-end text-success">{{ $cur($mTot['paid_month']) }}</td>
                    <td class="text-end text-orange">{{ $mTot['loans_issued'] > 0 ? $cur($mTot['loans_issued']) : '—' }}</td>
                    <td class="text-end text-success">{{ $mTot['principal_rep'] > 0 ? $cur($mTot['principal_rep']) : '—' }}</td>
                    <td class="text-end text-purple">{{ $mTot['interest'] > 0 ? $cur($mTot['interest']) : '—' }}</td>
                    <td class="text-end text-blue">{{ $cur($mTot['savings_balance']) }}</td>
                    <td class="text-end text-danger">{{ $mTot['loan_outstanding'] > 0 ? $cur($mTot['loan_outstanding']) : '—' }}</td>
                    <td class="text-end text-danger" style="background:#ffeaea">{{ $mTot['total_credit'] > 0 ? $cur($mTot['total_credit']) : '—' }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
{{-- ══════════════════════════════════════════════════════════════════
     TOTAL EXPECTED INCOME — comprehensive cycle-to-date banner
══════════════════════════════════════════════════════════════════ --}}
@php
    $totExp      = (float)($k['total_expected_income']      ?? 0);
    $totRec      = (float)($k['total_received_income']      ?? 0);
    $totGap      = $totExp - $totRec;
    $totRate     = $totExp > 0 ? round($totRec / $totExp * 100, 1) : 0;
    $totColor    = $totRate >= 90 ? '#2fb344' : ($totRate >= 60 ? '#f76707' : '#d63939');

    $brkContrib  = (float)($k['expected_contributions_all'] ?? 0);
    $brkPenalty  = (float)($k['expected_penalties_all']     ?? 0);
    $brkInterest = (float)($k['expected_interest_all']      ?? 0);
    $brkIncome   = (float)($k['expected_cash_income_all']   ?? 0);
    $brkExpenses = (float)($k['cycle_withdrawals']          ?? 0);

    // Group profit = Total Expected − Contributions − Cashbook Expenses
    $profitExp        = (float)($k['group_profit_expected']  ?? 0);
    $profitRec        = (float)($k['group_profit_collected'] ?? 0);
    $profitGap        = $profitExp - $profitRec;
    $totalShares      = (int)($k['total_group_shares']       ?? 1);
    $profitPerShare   = (float)($k['profit_per_share']       ?? 0);
    $memberProfits    = $k['member_profit_shares']           ?? collect();
@endphp

{{-- ── GROUP PROFIT callout ──────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-3" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border-left:5px solid #d97706!important">
    <div class="card-body py-3 px-4">

        {{-- Top row: formula + summary tiles --}}
        <div class="row align-items-start g-3 mb-3">
            <div class="col-12 col-md-5">
                <div class="d-flex align-items-center mb-2">
                    <span class="avatar avatar-sm me-2" style="background:#d97706;color:#fff"><i class="ti ti-trending-up"></i></span>
                    <span class="fw-bold text-uppercase" style="letter-spacing:.06em;color:#92400e;font-size:.78rem">Group Profit (Cycle-to-Date)</span>
                </div>
                <div class="text-muted mb-2" style="font-size:.72rem">
                    Total Expected Income &minus; Member Contributions (capital) &minus; Cashbook Expenses = Earned Profit
                </div>
                <div class="d-flex align-items-baseline gap-1 flex-wrap mb-3">
                    <span class="text-muted" style="font-size:.80rem">{{ $cur($totExp) }}</span>
                    <span class="text-muted mx-1">−</span>
                    <span class="text-muted" style="font-size:.80rem">{{ $cur($brkContrib) }}</span>
                    <span class="text-muted mx-1">−</span>
                    <span class="text-muted" style="font-size:.80rem">{{ $cur($brkExpenses) }}</span>
                    <span class="text-muted mx-1">=</span>
                    <span class="fw-bold" style="font-size:1.45rem;color:#d97706">{{ $cur($profitExp) }}</span>
                </div>
                {{-- Profit per share callout --}}
                <div class="rounded-2 px-3 py-2 d-inline-flex align-items-center gap-2"
                     style="background:#fef9c3;border:1px solid #fbbf24">
                    <i class="ti ti-coin" style="color:#d97706;font-size:1.1rem"></i>
                    <div>
                        <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.04em">Profit per Share</div>
                        <div class="fw-bold" style="color:#d97706;font-size:1.05rem">
                            {{ $cur($profitPerShare) }}
                        </div>
                        <div class="text-muted" style="font-size:.65rem">
                            {{ $cur($profitExp) }} ÷ {{ $totalShares }} share{{ $totalShares != 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-7">
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="bg-white rounded-2 border border-warning p-2 text-center h-100">
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.04em">Expected Profit</div>
                            <div class="fw-bold mt-1" style="color:#d97706;font-size:.95rem">{{ $cur($profitExp) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white rounded-2 border p-2 text-center h-100">
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.04em">Profit Collected</div>
                            <div class="fw-bold mt-1 text-green" style="font-size:.95rem">{{ $cur($profitRec) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white rounded-2 border p-2 text-center h-100">
                            <div class="text-muted" style="font-size:.67rem;text-transform:uppercase;letter-spacing:.04em">Outstanding</div>
                            <div class="fw-bold mt-1 text-orange" style="font-size:.95rem">{{ $cur($profitGap) }}</div>
                        </div>
                    </div>
                </div>
                <div class="row g-2">
                    @foreach([
                        ['label'=>'Loan Interest','val'=>$brkInterest,'color'=>'#4f46e5'],
                        ['label'=>'Penalties',    'val'=>$brkPenalty, 'color'=>'#f76707'],
                        ['label'=>'Other Income', 'val'=>$brkIncome,  'color'=>'#0ca678'],
                    ] as $b)
                    <div class="col-4">
                        <div class="bg-white rounded-2 border p-2 h-100">
                            <div class="text-muted" style="font-size:.66rem;text-transform:uppercase;letter-spacing:.04em">{{ $b['label'] }}</div>
                            <div class="fw-semibold mt-1" style="color:{{ $b['color'] }};font-size:.88rem">{{ $cur($b['val']) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>


    </div>
</div>

{{-- ── TOTAL EXPECTED INCOME summary banner ─────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);border-left:5px solid #4f46e5!important">
    <div class="card-body py-3 px-4">
        <div class="row align-items-center g-3">
            <div class="col-12 col-lg-4">
                <div class="d-flex align-items-center mb-1">
                    <span class="avatar avatar-sm bg-indigo text-white me-2"><i class="ti ti-target"></i></span>
                    <span class="fw-bold text-muted small text-uppercase" style="letter-spacing:.06em">Total Expected Income (Cycle-to-Date)</span>
                </div>
                <div class="d-flex align-items-baseline gap-3 mt-2">
                    <div>
                        <div class="text-muted" style="font-size:.70rem">Expected</div>
                        <div class="h3 fw-bold mb-0 text-indigo">{{ $cur($totExp) }}</div>
                    </div>
                    <div class="text-muted">vs</div>
                    <div>
                        <div class="text-muted" style="font-size:.70rem">Received</div>
                        <div class="h3 fw-bold mb-0 text-green">{{ $cur($totRec) }}</div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:.70rem">Gap</div>
                        <div class="h3 fw-bold mb-0" style="color:{{ $totColor }}">{{ $cur($totGap) }}</div>
                    </div>
                </div>
                <div class="progress mt-2" style="height:6px;border-radius:3px">
                    <div class="progress-bar" style="width:{{ min($totRate,100) }}%;background:{{ $totColor }}"></div>
                </div>
                <div class="text-muted mt-1" style="font-size:.70rem">{{ $totRate }}% of total income collected</div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="row g-2">
                    @foreach([
                        ['label'=>'Contributions (Capital)','val'=>$brkContrib, 'icon'=>'arrow-down-circle','color'=>'#2fb344'],
                        ['label'=>'Penalties',               'val'=>$brkPenalty, 'icon'=>'flame',            'color'=>'#f76707'],
                        ['label'=>'Loan Interest',           'val'=>$brkInterest,'icon'=>'chart-line',       'color'=>'#4f46e5'],
                        ['label'=>'Other Income',            'val'=>$brkIncome,  'icon'=>'wallet',           'color'=>'#0ca678'],
                    ] as $b)
                    <div class="col-6 col-md-3">
                        <div class="bg-white rounded-2 border p-2 h-100">
                            <div class="d-flex align-items-center mb-1">
                                <i class="ti ti-{{ $b['icon'] }} me-1" style="color:{{ $b['color'] }};font-size:.85rem"></i>
                                <span class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.04em">{{ $b['label'] }}</span>
                            </div>
                            <div class="fw-bold" style="color:{{ $b['color'] }};font-size:.95rem">{{ $cur($b['val']) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- KPI ROW 2  ─  5 | 7  split (contributions left, 2×2 metrics right) --}}
@php
    $expContrib  = (float)($k['expected_contributions'] ?? 0);
    $collContrib = (float)($k['contributions']          ?? 0);
    $collectRate = $expContrib > 0 ? round($collContrib / $expContrib * 100, 1) : 0;
    $rateColor   = $collectRate >= 90 ? 'success' : ($collectRate >= 60 ? 'warning' : 'danger');

    $cumulExp    = (float)($k['cumulative_expected']  ?? 0);
    $cumulColl   = (float)($k['cumulative_collected'] ?? 0);
    $cumulMonths = (int)  ($k['cumulative_months']    ?? 0);
    $cumulRate   = $cumulExp > 0 ? round($cumulColl / $cumulExp * 100, 1) : 0;
    $cumulColor  = $cumulRate >= 90 ? 'success' : ($cumulRate >= 60 ? 'warning' : 'danger');
@endphp
<div class="row row-cards mb-4 g-3">

    {{-- LEFT: Contributions — month vs cycle-to-date side-by-side --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">

                {{-- Header --}}
                <div class="d-flex align-items-center mb-3">
                    <span class="avatar avatar-sm bg-teal text-white me-2">
                        <i class="ti ti-arrow-down-circle"></i>
                    </span>
                    <span class="fw-semibold text-muted small text-uppercase" style="letter-spacing:.05em">Contributions</span>
                </div>

                {{-- Two columns: This Month | Cycle-to-Date --}}
                <div class="row g-0" style="font-size:.82rem">

                    {{-- This Month --}}
                    <div class="col-6 pe-3" style="border-right:1px solid #e8eaf0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-semibold" style="font-size:.70rem;text-transform:uppercase;letter-spacing:.05em">This Month</span>
                            <span class="badge bg-{{ $rateColor }}-lt text-{{ $rateColor }} fw-bold" style="font-size:.68rem">{{ $collectRate }}%</span>
                        </div>
                        <table class="w-100" style="border-collapse:collapse">
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Expected</td>
                                <td class="text-end fw-semibold py-1">{{ $cur($expContrib) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Collected</td>
                                <td class="text-end fw-bold text-teal py-1">{{ $cur($collContrib) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Gap</td>
                                <td class="text-end fw-semibold text-{{ $rateColor }} py-1">{{ $cur($expContrib - $collContrib) }}</td>
                            </tr>
                        </table>
                        <div class="progress mt-2" style="height:5px;border-radius:3px">
                            <div class="progress-bar bg-{{ $rateColor }}" style="width:{{ min($collectRate,100) }}%"></div>
                        </div>
                    </div>

                    {{-- Cycle-to-Date --}}
                    <div class="col-6 ps-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-semibold" style="font-size:.70rem;text-transform:uppercase;letter-spacing:.05em">
                                Cycle ({{ $cumulMonths }} mo)
                            </span>
                            <span class="badge bg-{{ $cumulColor }}-lt text-{{ $cumulColor }} fw-bold" style="font-size:.68rem">{{ $cumulRate }}%</span>
                        </div>
                        <table class="w-100" style="border-collapse:collapse">
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Expected</td>
                                <td class="text-end fw-semibold py-1">{{ $cur($cumulExp) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Collected</td>
                                <td class="text-end fw-bold text-teal py-1">{{ $cur($cumulColl) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted py-1" style="font-size:.72rem">Gap</td>
                                <td class="text-end fw-semibold text-{{ $cumulColor }} py-1">{{ $cur($cumulExp - $cumulColl) }}</td>
                            </tr>
                        </table>
                        <div class="progress mt-2" style="height:5px;border-radius:3px">
                            <div class="progress-bar bg-{{ $cumulColor }}" style="width:{{ min($cumulRate,100) }}%"></div>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.65rem">
                            amount × {{ $cumulMonths }} mo × members
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT: 4 metric tiles in a 2×2 grid --}}
    <div class="col-12 col-lg-7">
        <div class="row row-cards g-3 h-100">
            @foreach([
                ['label'=>'Loan Interest Outstanding', 'val'=>$k['interest_receivable'],        'color'=>'indigo',  'icon'=>'chart-line',   'hint'=>'Accrued but unpaid interest on active loans'],
                ['label'=>'Interest Earned (Mo)',       'val'=>$k['interest_earned'],            'color'=>'purple',  'icon'=>'percentage',   'hint'=>'Interest actually received in cash this month'],
                ['label'=>'Penalties Outstanding',      'val'=>$k['total_penalties'],            'color'=>'yellow',  'icon'=>'flame',        'hint'=>'Total unpaid late fees across all periods'],
                ['label'=>'Penalties Collected (Mo)',   'val'=>$k['penalties_collected_month'],  'color'=>'orange',  'icon'=>'cash',         'hint'=>'Late fees paid in cash this month'],
            ] as $card)
            <div class="col-6">
                <div class="card card-sm border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-2">
                            <span class="avatar avatar-sm bg-{{ $card['color'] }}-lt text-{{ $card['color'] }} me-2">
                                <i class="ti ti-{{ $card['icon'] }}"></i>
                            </span>
                            <div>
                                <div class="text-muted fw-medium" style="font-size:.75rem;line-height:1.3">{{ $card['label'] }}</div>
                            </div>
                        </div>
                        <div class="h3 mb-0 fw-bold">{{ $cur($card['val']) }}</div>
                        <div class="text-muted mt-1" style="font-size:.68rem">{{ $card['hint'] }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>


{{-- ══════════════════════════════════════════════════════════════════
     MONTHLY FINANCIAL SUMMARY + WEALTH BREAKDOWN
══════════════════════════════════════════════════════════════════ --}}
<div class="row row-cards mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header" style="background:#f0fdf4;border-bottom:2px solid #2fb344">
                <h3 class="card-title fw-bold m-0" style="color:#2fb344">
                    <i class="ti ti-trending-up me-2"></i>Monthly Financial Summary
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-sm mb-0">
                    <tbody>
                        <tr style="background:#eef9ee">
                            <td class="fw-semibold"><i class="ti ti-wallet me-1 text-muted"></i>Opening Balance</td>
                            <td class="text-end fw-bold">{{ $cur($c['opening_balance']) }}</td>
                        </tr>
                        <tr class="table-success">
                            <td><i class="ti ti-plus me-1 text-success"></i>+ Contributions Received</td>
                            <td class="text-end text-success fw-semibold">{{ $cur($c['contributions_collected']) }}</td>
                        </tr>
                        @if(($c['penalties_collected'] ?? 0) > 0)
                        <tr>
                            <td class="ps-4 text-muted small"><i class="ti ti-arrow-right me-1"></i>of which: late fees collected</td>
                            <td class="text-end text-orange small fw-semibold">{{ $cur($c['penalties_collected']) }}</td>
                        </tr>
                        @endif
                        <tr class="table-success">
                            <td><i class="ti ti-plus me-1 text-success"></i>+ Loan Repayments (Principal)</td>
                            <td class="text-end text-success fw-semibold">{{ $cur($c['loan_repayments']) }}</td>
                        </tr>
                        <tr class="table-success">
                            <td><i class="ti ti-plus me-1 text-success"></i>+ Interest Earned</td>
                            <td class="text-end text-success fw-semibold">{{ $cur($c['interest_earned']) }}</td>
                        </tr>
                        @if($c['adjustments'] != 0)
                        <tr>
                            <td><i class="ti ti-adjustments me-1 text-muted"></i>± Cashbook Adjustments</td>
                            <td class="text-end {{ $c['adjustments'] >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $c['adjustments'] >= 0 ? '+' : '' }}{{ $cur($c['adjustments']) }}
                            </td>
                        </tr>
                        @endif
                        <tr class="table-danger">
                            <td><i class="ti ti-minus me-1 text-danger"></i>− Loan Disbursements</td>
                            <td class="text-end text-danger fw-semibold">{{ $cur($c['loan_disbursements']) }}</td>
                        </tr>
                        <tr class="table-danger">
                            <td><i class="ti ti-minus me-1 text-danger"></i>− Withdrawals</td>
                            <td class="text-end text-danger fw-semibold">{{ $cur($c['withdrawals']) }}</td>
                        </tr>
                        <tr style="background:#fef9c3">
                            <td class="fw-bold py-3"><i class="ti ti-report-money me-1"></i>= Closing Balance</td>
                            <td class="text-end fw-bold h4 mb-0 py-3 text-blue">{{ $cur($c['closing_balance']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header" style="background:#fff8f0;border-bottom:2px solid #f76707">
                <h3 class="card-title fw-bold m-0" style="color:#f76707">
                    <i class="ti ti-chart-pie me-2"></i>Group Wealth Breakdown
                </h3>
            </div>
            <div class="card-body">
                @php
                    $totalW  = $k['group_amount'] > 0 ? $k['group_amount'] : 1;
                    $cashPct = min(100, round($k['closing_balance'] / $totalW * 100));
                    $loanPct = min(100, round($k['total_loans_out'] / $totalW * 100));
                @endphp
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold">Total Group Wealth</span>
                        <span class="fw-bold text-blue h4 mb-0">{{ $cur($k['group_amount']) }} {{ $currency }}</span>
                    </div>
                    <div class="progress mb-1" style="height:18px;border-radius:6px">
                        <div class="progress-bar bg-success" style="width:{{ $cashPct }}%" title="Cash {{ $cashPct }}%">Cash</div>
                        <div class="progress-bar bg-warning" style="width:{{ $loanPct }}%" title="Loans {{ $loanPct }}%">Loans</div>
                    </div>
                    <div class="d-flex justify-content-between text-muted small">
                        <span><span class="text-success me-1">●</span>Cash {{ $cashPct }}%</span>
                        <span><span class="text-warning me-1">●</span>Loans out {{ $loanPct }}%</span>
                    </div>
                </div>
                <div class="divide-y">
                    <div class="row py-2"><div class="col text-muted">Cash on Hand</div><div class="col-auto fw-bold text-green">{{ $cur($k['closing_balance']) }}</div></div>
                    <div class="row py-2"><div class="col text-muted">Loans Outstanding (Principal)</div><div class="col-auto fw-bold text-orange">{{ $cur($k['total_loans_out']) }}</div></div>
                    <div class="row py-2"><div class="col text-muted">Loan Interest Outstanding</div><div class="col-auto fw-bold text-indigo">{{ $cur($k['interest_receivable']) }}</div></div>
                    <div class="row py-2"><div class="col text-muted">Interest Earned this Month</div><div class="col-auto fw-bold text-purple">{{ $cur($k['interest_earned']) }}</div></div>
                    <div class="row py-2"><div class="col text-muted">Contribution Arrears</div><div class="col-auto fw-bold text-red">{{ $cur($k['total_arrears']) }}</div></div>
                    <div class="row py-2"><div class="col text-muted">Penalties Outstanding</div><div class="col-auto fw-bold text-orange">{{ $cur($k['total_penalties']) }}</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     REPORT FOOTER
══════════════════════════════════════════════════════════════════ --}}
<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col">
                <div class="text-muted small">
                    <i class="ti ti-file-check me-1"></i>
                    Generated by <strong>{{ $hdr['generated_by'] }}</strong>
                    on {{ $hdr['generated_at']->format('d M Y, H:i') }}
                </div>
                <div class="text-muted small mt-1">
                    Period: <strong>{{ $hdr['month_label'] }}</strong> |
                    Group: <strong>{{ $hdr['group']->name }}</strong> |
                    Cycle starts: <strong>{{ $hdr['cycle_start']->format('d M Y') }}</strong> |
                    Currency: <strong>{{ $currency }}</strong>
                </div>
            </div>
            <div class="col-auto d-print-none">
                <span class="badge bg-success-lt text-success">
                    <i class="ti ti-circle-check me-1"></i>Report Complete
                </span>
            </div>
        </div>
        {{-- Signature lines for print --}}
        <div class="row mt-4 d-none d-print-flex">
            <div class="col-4 text-center">
                <div style="border-top:1px solid #000;padding-top:4px;margin-top:40px">Treasurer Signature</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-top:1px solid #000;padding-top:4px;margin-top:40px">Secretary Signature</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-top:1px solid #000;padding-top:4px;margin-top:40px">Chairperson Signature</div>
            </div>
        </div>
    </div>
</div>

@push('head')
<style>
    @media print {
        .card { break-inside: avoid; }
        table { font-size: 7px !important; white-space: nowrap; }
        .h2,.h3,.h4 { font-size: 12px !important; }
    }
</style>
@endpush
