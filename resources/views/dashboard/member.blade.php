@extends('layouts.app')
@section('title', __('My Dashboard'))
@section('content')

@php
    $member      = $personal['member'];
    $pStats      = $personal['stats'];
    $savings     = (float)($stats['member_contributions'] ?? 0);
    $loanPrinc   = (float)($stats['pending_loans_principal'] ?? 0);
    $loanInt     = (float)($stats['pending_loans_interest']  ?? 0);
    $loansOut    = $loanPrinc + $loanInt;
    $balance     = (float)($stats['current_balance'] ?? 0);
    $arrears     = (float)($stats['open_arrears_amount'] ?? 0) + (float)($stats['unpaid_this_month'] ?? 0);
    $membersCount= (int)($stats['members_count'] ?? 0);

    // Group Overview donut — 4 segments representing all 5 metrics:
    //  seg1 (indigo)  = group contributions excluding this member
    //  seg2 (purple)  = this member's expected contribution
    //  seg3 (amber)   = group profit excluding this member's share
    //  seg4 (green)   = this member's profit per share
    //  centre         = Total Expected Income
    $ovTotalExp  = (float)($stats['total_expected']                ?? 0);
    $ovContrib   = (float)($stats['contributions_expected']        ?? 0);
    $ovProfit    = max(0, (float)($stats['group_profit']           ?? 0));
    $ovMemberExp = (float)($stats['member_contributions_expected'] ?? 0);
    $ovPPS       = (float)($stats['profit_per_share']              ?? 0);

    $seg1 = max(0, $ovContrib - $ovMemberExp);   // other members' contributions
    $seg2 = $ovMemberExp;                         // this member's contribution
    $seg3 = max(0, $ovProfit  - $ovPPS);          // other members' profit
    $seg4 = $ovPPS;                               // this member's profit share

    $chartTotal  = $ovTotalExp;
    if ($chartTotal > 0) {
        $s1 =                    round($seg1 / $chartTotal * 100);
        $s2 = $s1 +              round($seg2 / $chartTotal * 100);
        $s3 = $s2 +              round($seg3 / $chartTotal * 100);
        $s4 = $s3 +              round($seg4 / $chartTotal * 100);
    } else {
        $s1 = 57; $s2 = 65; $s3 = 95; $s4 = 99;
    }

    // Contribution quick counts
    $totalC = $pStats['pending'] + $pStats['overdue'] + $pStats['paid'];
@endphp

<style>
.member-dash { max-width: 700px; margin: 0 auto; }
@media (max-width: 576px) {
    .member-dash { padding: 0 4px; }
    .page-wrapper .page-body { padding-top: 0.5rem !important; }
}
/* Dark group card */
.group-hero {
    background: #1e1b4b;
    border-radius: 16px;
    color: #fff;
    padding: 1rem 1.25rem 1.25rem;
    margin-bottom: 1rem;
    overflow: hidden;
}
.group-hero .stat-label { color: rgba(255,255,255,.55); font-size: .72rem; text-transform: uppercase; letter-spacing:.04em; margin-bottom:2px; }
.group-hero .stat-value { font-size: 1.05rem; font-weight: 700; color:#fff; }
.group-hero .stat-icon  { width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:6px; }
/* Action buttons */
.action-btn { display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;color:#1e1b4b;font-size:.72rem;font-weight:600;flex:1; }
.action-btn .action-icon { width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem; }
.action-btn:hover .action-icon { filter:brightness(.92); }
/* Donut — 4 segments: other-contrib / my-contrib / other-profit / my-profit */
.donut-ring {
    width:130px;height:130px;border-radius:50%;
    background:conic-gradient(
        #6366f1 0% var(--s1),
        #8b5cf6 var(--s1) var(--s2),
        #f59e0b var(--s2) var(--s3),
        #10b981 var(--s3) var(--s4),
        #e5e7eb var(--s4) 100%
    );
    -webkit-mask:radial-gradient(farthest-side,transparent 56%,#000 56%);
    mask:radial-gradient(farthest-side,transparent 56%,#000 56%);
    flex-shrink:0;
}
.legend-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
/* Overview list responsive */
.donut-list-label { font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.donut-list-amt   { min-width:90px;text-align:right;font-weight:700;font-size:.82rem;margin-left:.5rem;flex-shrink:0; }
.donut-list-hdr-amt { min-width:90px;font-size:.67rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;font-weight:600;text-align:right;flex-shrink:0; }
@media (max-width:400px) {
    .donut-list-label   { font-size:.72rem;white-space:normal;line-height:1.2; }
    .donut-list-amt     { min-width:72px;font-size:.74rem; }
    .donut-list-hdr-amt { min-width:72px; }
}
/* Activity item */
.activity-item { display:flex;align-items:flex-start;gap:.7rem;padding:.6rem 0;border-bottom:1px solid #f1f3f5; }
.activity-item:last-child { border-bottom:none; }
.activity-icon-wrap { width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0; }
/* AI Chat */
.chat-bubble { border-radius:12px;padding:.55rem .8rem;margin-bottom:.5rem;font-size:.82rem;max-width:92%;line-height:1.4; }
.chat-bubble-ai   { background:#f1f5f9;color:#374151;align-self:flex-start; }
.chat-bubble-user { background:#6366f1;color:#fff;align-self:flex-end;margin-left:auto; }
.chat-bubble-link { display:inline-flex;align-items:center;gap:4px;font-size:.72rem;color:#6366f1;text-decoration:none;margin-top:4px;background:#ede9fe;border-radius:6px;padding:2px 8px; }
.chat-bubble-link:hover { background:#ddd6fe; }
#chatMessages { display:flex;flex-direction:column; }
</style>

<div class="member-dash">

    {{-- Welcome --}}
    <div class="mb-3 mt-2 px-1">
        <div class="text-muted small">{{ __('Welcome back') }},</div>
        <div class="fs-4 fw-bold text-dark">{{ $member->first_name }}</div>
    </div>

    {{-- Dark Group Hero Card --}}
    <div class="group-hero">
        {{-- Header: group icon + name + members count --}}
        <div class="d-flex align-items-start justify-content-between pb-2" style="border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:.75rem">
            <div class="d-flex align-items-center gap-2">
                {{-- Group icon badge --}}
                <div style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.4);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="ti ti-users-group" style="color:#c4b5fd;font-size:.9rem"></i>
                </div>
                <div>
                    <div style="font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">{{ __('My Group') }}</div>
                    <div style="font-size:1rem;font-weight:700;color:#fff;line-height:1.2;border:0;text-decoration:none">
                        {{ $activeGroup->name ?? __('Group') }}
                        <span style="background:rgba(16,185,129,.25);color:#6ee7b7;font-size:.6rem;padding:2px 7px;border-radius:20px;font-weight:500;vertical-align:middle">{{ __('Active') }}</span>
                    </div>
                </div>
            </div>
            {{-- Members count --}}
            <div class="d-flex align-items-center gap-2 ms-2" style="flex-shrink:0">
                <div style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.3);display:flex;align-items:center;justify-content:center">
                    <i class="ti ti-users" style="color:#a5b4fc;font-size:.9rem"></i>
                </div>
                <div class="text-end">
                    <div style="font-weight:700;font-size:.95rem;line-height:1;color:#fff">{{ number_format($membersCount) }}</div>
                    <div style="font-size:.6rem;color:rgba(255,255,255,.5);text-transform:uppercase">{{ __('Members') }}</div>
                </div>
            </div>
        </div>

        <div class="row g-2">
            {{-- 1: Total Expected --}}
            <div class="col-6 col-sm-3">
                <div class="stat-icon" style="background:rgba(99,102,241,.25)"><i class="ti ti-coin" style="color:#a5b4fc"></i></div>
                <div class="stat-value">{{ number_format($stats['total_expected'] ?? 0) }} <span style="font-weight:400;opacity:.75">RWF</span></div>
                <div class="stat-label">{{ __('Total Expected') }}</div>
                @if(($stats['contributions_expected'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(196,181,253,.85);margin-top:2px">{{ __('Contributions') }}: {{ number_format($stats['contributions_expected']) }} RWF</div>
                @endif
                @if(($stats['group_profit'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(167,243,208,.85)">{{ __('Exp. Profit') }}: {{ number_format($stats['group_profit']) }} RWF</div>
                @endif
            </div>
            {{-- 2: Current Balance --}}
            <div class="col-6 col-sm-3" style="border-left:1px solid rgba(255,255,255,.15)">
                <div class="stat-icon" style="background:rgba(16,185,129,.25)"><i class="ti ti-wallet" style="color:#6ee7b7"></i></div>
                <div class="stat-value">{{ number_format($stats['current_balance'] ?? $balance) }} <span style="font-weight:400;opacity:.75">RWF</span></div>
                <div class="stat-label">{{ __('Current Balance') }}</div>
            </div>
            {{-- 3: Pending Loans (capital + interest) --}}
            <div class="col-6 col-sm-3">
                <div class="stat-icon" style="background:rgba(245,158,11,.25)"><i class="ti ti-clock-dollar" style="color:#fcd34d"></i></div>
                <div class="stat-value">{{ number_format($stats['pending_loans_amount'] ?? 0) }} <span style="font-weight:400;opacity:.75">RWF</span></div>
                <div class="stat-label">{{ __('Pending Loans') }}</div>
                @if(($stats['pending_loans_principal'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(252,211,77,.8);margin-top:2px">{{ __('Capital') }}: {{ number_format($stats['pending_loans_principal']) }} RWF</div>
                @endif
                @if(($stats['pending_loans_interest'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(167,243,208,.8)">{{ __('Interest') }}: {{ number_format($stats['pending_loans_interest']) }} RWF</div>
                @endif
            </div>
            {{-- 4: Contribution Arrears (arrears + this month unpaid) --}}
            <div class="col-6 col-sm-3" style="border-left:1px solid rgba(255,255,255,.15)">
                <div class="stat-icon" style="background:rgba(239,68,68,.25)"><i class="ti ti-alert-triangle" style="color:#fca5a5"></i></div>
                @php
                    $totalArrears = ($stats['open_arrears_amount'] ?? 0) + ($stats['unpaid_this_month'] ?? 0);
                @endphp
                <div class="stat-value">{{ number_format($totalArrears) }} <span style="font-weight:400;opacity:.75">RWF</span></div>
                <div class="stat-label">{{ __('Contribution Arrears') }}</div>
                @if(($stats['open_arrears_amount'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(252,165,165,.75);margin-top:2px">{{ __('Arrears') }}: {{ number_format($stats['open_arrears_amount']) }} RWF</div>
                @endif
                @if(($stats['unpaid_this_month'] ?? 0) > 0)
                <div style="font-size:.6rem;color:rgba(252,211,77,.8)">{{ __('This month') }}: {{ number_format($stats['unpaid_this_month']) }} RWF</div>
                @endif
            </div>
        </div>

    </div>

    {{-- Quick Actions --}}
    <div class="card mb-3">
        <div class="card-body py-3 px-2">
            <div class="d-flex justify-content-around">
                <a href="{{ route('contributions.index') }}" class="action-btn">
                    <div class="action-icon" style="background:#ede9fe"><i class="ti ti-clipboard-list text-indigo" style="color:#6366f1"></i></div>
                    <span>{{ __('Contributions') }}</span>
                </a>
                <a href="{{ route('loans.create') }}" class="action-btn">
                    <div class="action-icon" style="background:#fef3c7"><i class="ti ti-coin" style="color:#d97706"></i></div>
                    <span>{{ __('Request Loan') }}</span>
                </a>
                <a href="{{ route('passbooks.show', $member) }}" class="action-btn">
                    <div class="action-icon" style="background:#d1fae5"><i class="ti ti-book" style="color:#059669"></i></div>
                    <span>{{ __('My Passbook') }}</span>
                </a>
                <a href="{{ route('treasury.member', $member) }}" class="action-btn">
                    <div class="action-icon" style="background:#dbeafe"><i class="ti ti-building-bank" style="color:#2563eb"></i></div>
                    <span>{{ __('My Equity') }}</span>
                </a>
            </div>
        </div>
    </div>


    {{-- Group Overview + Donut --}}
    @if($chartTotal > 0)
    @php
        // These match the 4 actual donut segments + the total
        $listRows = [
            ['label' => __('Total Expected Income'),        'value' => $ovTotalExp, 'color' => '#1e1b4b', 'seg' => 'total', 'onclick' => ''],
            ['label' => __('Group Expected Contribution'),  'value' => $ovContrib,  'color' => '#6366f1', 'seg' => '1',     'onclick' => 'donutClickLegend(0)'],
            ['label' => __('My Expected Contribution'),     'value' => $ovMemberExp,'color' => '#8b5cf6', 'seg' => '2',     'onclick' => 'donutClickLegend(1)'],
            ['label' => __('Expected Profit'),              'value' => $ovProfit,   'color' => '#f59e0b', 'seg' => '3',     'onclick' => 'donutClickLegend(2)'],
            ['label' => __('My Profit per Share'),          'value' => $ovPPS,      'color' => '#10b981', 'seg' => '4',     'onclick' => 'donutClickLegend(3)'],
        ];
    @endphp
    <div class="card mb-3">
        <div class="card-header border-0 pb-0">
            <h3 class="card-title fw-semibold">{{ __('Group Overview') }}</h3>
        </div>
        <div class="card-body pt-2 pb-0">

            {{-- Donut — centred --}}
            <div class="d-flex justify-content-center mb-3">
                <div class="position-relative d-flex align-items-center justify-content-center">
                    <div id="donutRing" class="donut-ring"
                         style="--s1:{{ $s1 }}%;--s2:{{ $s2 }}%;--s3:{{ $s3 }}%;--s4:{{ $s4 }}%"
                         data-s1="{{ $s1 }}" data-s2="{{ $s2 }}" data-s3="{{ $s3 }}" data-s4="{{ $s4 }}"
                         data-seg1-label="{{ __('Group Expected Contribution') }}"  data-seg1-val="{{ number_format($ovContrib) }}"  data-seg1-color="#6366f1"
                         data-seg2-label="{{ __('Member Expected Contribution') }}"    data-seg2-val="{{ number_format($seg2) }}"  data-seg2-color="#8b5cf6"
                         data-seg3-label="{{ __('Expected Profit') }}"               data-seg3-val="{{ number_format($ovProfit) }}"  data-seg3-color="#f59e0b"
                         data-seg4-label="{{ __('My Profit per Share') }}"             data-seg4-val="{{ number_format($ovPPS) }}"    data-seg4-color="#10b981"
                    ></div>
                    <div class="position-absolute text-center" style="pointer-events:none;max-width:68px">
                        <div class="fw-bold" style="color:#f59e0b;font-size:.78rem;line-height:1.1">{{ number_format($ovProfit,0) }}</div>
                        <div style="font-size:.57rem;color:#6b7280;line-height:1.2">{{ __('Exp. Profit') }}</div>
                    </div>
                </div>
            </div>

            {{-- Floating tooltip (desktop hover) --}}
            <div id="donutTooltip" style="
                display:none;position:fixed;z-index:9999;
                background:rgba(15,23,42,.92);color:#fff;
                border-radius:8px;padding:6px 11px;font-size:.75rem;
                pointer-events:none;white-space:nowrap;
                box-shadow:0 4px 16px rgba(0,0,0,.25);
                border-left:3px solid #ccc;
            "></div>

            {{-- Always-visible info list --}}
            <div style="border-radius:8px;overflow:hidden;border:1px solid #e8eaed">
                {{-- Header row --}}
                <div class="d-flex align-items-center px-2 py-2" style="background:#f8fafc;border-bottom:1px solid #e8eaed">
                    <div style="width:10px;flex-shrink:0"></div>
                    <div class="flex-grow-1 ms-2" style="font-size:.67rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;font-weight:600;overflow:hidden">{{ __('Category') }}</div>
                    <div class="donut-list-hdr-amt">{{ __('Amount') }}</div>
                    <div class="d-none d-sm-block" style="min-width:40px;font-size:.67rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;font-weight:600;text-align:right;flex-shrink:0">%</div>
                </div>

                @foreach($listRows as $row)
                @php $pct = $ovTotalExp > 0 ? round($row['value'] / $ovTotalExp * 100, 1) : 0; @endphp
                <div class="donut-legend-row d-flex align-items-center px-2 py-2"
                     data-seg="{{ $row['seg'] }}"
                     style="border-bottom:1px solid #f0f2f4;border-left:3px solid {{ $row['color'] }};transition:background .15s;{{ $row['onclick'] ? 'cursor:pointer' : '' }}"
                     {{ $row['onclick'] ? 'onclick="'.$row['onclick'].'"' : '' }}>
                    <span class="rounded-circle flex-shrink-0" style="width:10px;height:10px;background:{{ $row['color'] }}"></span>
                    <div class="flex-grow-1 ms-2 min-w-0">
                        <div class="donut-list-label">{{ $row['label'] }}</div>
                        <div class="progress mt-1" style="height:3px;background:#eee;border-radius:3px">
                            <div style="width:{{ $pct }}%;background:{{ $row['color'] }};height:3px;border-radius:3px"></div>
                        </div>
                    </div>
                    <div class="donut-list-amt" style="color:{{ $row['color'] }}">
                        {{ number_format($row['value'], 0) }}
                    </div>
                    <div class="d-none d-sm-block" style="min-width:40px;text-align:right;font-size:.75rem;color:#9ca3af;margin-left:.5rem;flex-shrink:0">
                        {{ $pct }}%
                    </div>
                </div>
                @endforeach
            </div>

        </div>
        <div class="card-body pt-2 pb-2">
            {{-- Tap info panel (shown on ring click/tap) --}}
            <div id="donutInfoPanel" style="display:none;border-radius:10px;padding:.65rem 1rem;border-left:4px solid #ccc;background:#f8fafc;transition:all .2s">
                <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px" id="donutInfoLabel"></div>
                <div style="font-size:1.25rem;font-weight:700;line-height:1.2" id="donutInfoValue"></div>
                <div style="font-size:.7rem;color:#9ca3af;margin-top:2px" id="donutInfoPct"></div>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Activities --}}
    @if($memberActivities->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title fw-semibold mb-0">{{ __('Recent Activities') }}</h3>
            <a href="{{ route('activity.index') }}" class="small" style="color:#6366f1;text-decoration:none">{{ __('View All') }} →</a>
        </div>
        <div class="card-body pt-2 pb-1">
            @foreach($memberActivities as $act)
            @php
                $bg    = match($act->color ?? 'blue') {
                    'green'  => '#d1fae5', 'red'    => '#fee2e2',
                    'yellow' => '#fef3c7', 'orange' => '#ffedd5',
                    'purple' => '#ede9fe', 'teal'   => '#ccfbf1',
                    'pink'   => '#fce7f3', default  => '#dbeafe',
                };
                $fg    = match($act->color ?? 'blue') {
                    'green'  => '#059669', 'red'    => '#dc2626',
                    'yellow' => '#d97706', 'orange' => '#ea580c',
                    'purple' => '#7c3aed', 'teal'   => '#0d9488',
                    'pink'   => '#db2777', default  => '#2563eb',
                };
            @endphp
            <a href="{{ $act->url }}" class="activity-item text-reset text-decoration-none">
                <div class="activity-icon-wrap" style="background:{{ $bg }}">
                    <i class="ti {{ $act->icon ?? 'ti-bell' }}" style="color:{{ $fg }}"></i>
                </div>
                <div class="flex-grow-1 min-width-0">
                    <div class="small fw-medium text-dark" style="line-height:1.3">{{ Str::limit($act->description, 70) }}</div>
                    <div class="text-muted" style="font-size:.68rem">{{ $act->created_at->diffForHumans() }}</div>
                </div>
                <i class="ti ti-arrow-up-right text-muted ms-2 mt-1" aria-hidden="true"></i>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- My Recent Contributions --}}
    <div class="card mb-3">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title fw-semibold mb-0">{{ __('My Contributions') }}</h3>
            <a href="{{ route('contributions.index') }}" class="small" style="color:#6366f1;text-decoration:none">{{ __('View All') }} →</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter mb-0" style="font-size:.83rem">
                <thead>
                    <tr>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Expected') }}</th>
                        <th class="text-end">{{ __('Paid') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $c)
                    <tr>
                        <td class="text-muted">{{ $c->period_start->format('M Y') }}</td>
                        <td>{{ Str::title(str_replace('_',' ',$c->type)) }}</td>
                        <td>@include('contributions._status', ['status' => $c->status])</td>
                        <td class="text-end">{{ number_format($c->expected_amount, 0) }}</td>
                        <td class="text-end fw-medium {{ $c->status==='paid' ? 'text-success' : '' }}">{{ number_format($c->paid_amount, 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No contributions yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ AI INSIGHTS SECTION ═══ --}}

    {{-- AI 1 + 3: My Loan Risk Score & Group Health --}}
    @if($aiRisk || $aiHealth)
    <div class="row g-3 mb-3">
        @if($aiRisk)
        <div class="col-12 col-sm-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ti ti-shield-check fs-4 text-{{ $aiRisk['color'] }}"></i>
                        <div class="fw-semibold small text-uppercase text-muted">{{ __('My Loan Risk Score') }}</div>
                    </div>
                    <div class="d-flex align-items-end gap-3 mb-2">
                        <div class="display-5 fw-bold text-{{ $aiRisk['color'] }}" style="line-height:1">{{ $aiRisk['score'] }}</div>
                        <div>
                            <span class="badge bg-{{ $aiRisk['color'] }}-lt text-{{ $aiRisk['color'] }} fs-6">{{ __($aiRisk['label']) }}</span>
                            <div class="text-muted" style="font-size:.7rem">{{ __('/ 100') }}</div>
                        </div>
                    </div>
                    <div class="progress mb-2" style="height:6px">
                        <div class="progress-bar bg-{{ $aiRisk['color'] }}" style="width:{{ $aiRisk['score'] }}%"></div>
                    </div>
                    <div class="small text-muted">
                        {{ __('Payment rate') }}: <strong>{{ $aiRisk['factors']['payment_rate'] }}%</strong> ·
                        {{ __('Overdue') }}: <strong>{{ $aiRisk['factors']['overdue_count'] }}</strong>
                        @if($aiRisk['factors']['loan_repayment_rate'] !== null)
                        · {{ __('Loan repaid') }}: <strong>{{ $aiRisk['factors']['loan_repayment_rate'] }}%</strong>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
        @if($aiHealth)
        <div class="col-12 col-sm-6">
            <div class="card h-100 border-0 shadow-sm border-{{ $aiHealth['status_label']['color'] }}" style="border-left:4px solid !important">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ti {{ $aiHealth['status_label']['icon'] }} fs-4 text-{{ $aiHealth['status_label']['color'] }}"></i>
                        <div class="fw-semibold small text-uppercase text-muted">{{ __('Group Health') }}</div>
                        <span class="badge bg-{{ $aiHealth['status_label']['color'] }}-lt text-{{ $aiHealth['status_label']['color'] }} ms-auto">{{ __($aiHealth['status_label']['label']) }}</span>
                    </div>
                    <div class="progress mb-2" style="height:6px">
                        <div class="progress-bar bg-{{ $aiHealth['status_label']['color'] }}" style="width:{{ $aiHealth['score'] }}%"></div>
                    </div>
                    @foreach($aiHealth['lines'] as $line)
                    <div class="small text-muted mb-1">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- AI 2: Cash Flow Forecast --}}
    @if($aiForecast && count($aiForecast['months']) > 0)
    <div class="card mb-3">
        <div class="card-header border-0 pb-0">
            <i class="ti ti-chart-bar me-1 text-indigo"></i>
            <span class="fw-semibold">{{ __('3-Month Cash Flow Forecast') }}</span>
            <span class="text-muted small ms-2">{{ __('AI projection based on schedules & history') }}</span>
        </div>
        <div class="card-body pt-2">
            @php
                $forecastVals = array_column($aiForecast['months'], 'projected_balance');
                $maxForecast  = count($forecastVals) > 0 ? max(max($forecastVals), 1) : 1;
            @endphp
            <div class="row g-2 mb-3">
                @foreach($aiForecast['months'] as $fm)
                <div class="col-4 text-center">
                    <div class="small text-muted mb-1">{{ $fm['month'] }}</div>
                    <div class="position-relative d-flex flex-column align-items-center">
                        <div style="width:100%;background:#f1f5f9;border-radius:6px;height:80px;display:flex;align-items:flex-end;overflow:hidden">
                            <div style="width:100%;background:{{ $fm['net'] >= 0 ? '#6366f1' : '#ef4444' }};border-radius:4px 4px 0 0;height:{{ max(6, round($fm['projected_balance'] / $maxForecast * 100)) }}%;transition:height .3s"></div>
                        </div>
                        <div class="fw-bold small mt-1" style="color:{{ $fm['net'] >= 0 ? '#6366f1' : '#ef4444' }}">{{ number_format($fm['projected_balance']) }}</div>
                        <div style="font-size:.63rem;color:#9ca3af">{{ __('RWF') }}</div>
                    </div>
                    <div style="font-size:.65rem;margin-top:4px">
                        <span class="text-success">↑{{ number_format($fm['expected_in']) }}</span>
                        <span class="text-danger ms-1">↓{{ number_format($fm['expected_out']) }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="d-flex gap-3 justify-content-center small text-muted">
                <span><span style="display:inline-block;width:10px;height:10px;background:#6366f1;border-radius:2px;margin-right:4px"></span>{{ __('Projected Balance') }}</span>
                <span class="text-success">↑ {{ __('In') }}</span>
                <span class="text-danger">↓ {{ __('Out') }}</span>
            </div>
            <div class="mt-2 pt-2 border-top small text-muted">
                {{ __('Current balance') }}: <strong>{{ number_format($aiForecast['current_balance']) }} RWF</strong>
            </div>
        </div>
    </div>
    @endif

    {{-- AI 5: Member Insights Chat --}}
    <div class="card mb-3" id="aiChatCard">
        <div class="card-header border-0 pb-0">
            <div class="d-flex align-items-center gap-2">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center">
                    <i class="ti ti-sparkles" style="color:#fff;font-size:.9rem"></i>
                </div>
                <div>
                    <div class="fw-semibold">{{ __('Ask AI about my account') }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ __('Powered by your real group data') }}</div>
                </div>
            </div>
        </div>
        <div class="card-body pt-2">
            {{-- Chat messages --}}
            <div id="chatMessages" style="min-height:80px;max-height:260px;overflow-y:auto;margin-bottom:.75rem">
                <div class="chat-bubble chat-bubble-ai" id="chatWelcome">
                    <i class="ti ti-sparkles me-1" style="color:#8b5cf6"></i>
                    {{ __('Hi! Ask me anything about your savings, loans, share-out or contributions.') }}
                </div>
            </div>
            {{-- Quick questions --}}
            <div class="d-flex flex-wrap gap-1 mb-2" id="quickQuestions">
                @foreach([
                    __('My savings balance'),
                    __('Can I borrow?'),
                    __('My next payment'),
                    __('My share-out'),
                    __('My profit share'),
                ] as $q)
                <button class="btn btn-sm btn-outline-secondary py-0 quick-q" style="font-size:.72rem" data-q="{{ $q }}">{{ $q }}</button>
                @endforeach
            </div>
            {{-- Input --}}
            <div class="input-group">
                <input type="text" id="chatInput" class="form-control form-control-sm"
                    placeholder="{{ __('Type your question…') }}"
                    maxlength="200" autocomplete="off">
                <button class="btn btn-primary btn-sm" id="chatSend">
                    <i class="ti ti-send"></i>
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
(function () {
    const ring    = document.getElementById('donutRing');
    const tooltip = document.getElementById('donutTooltip');
    if (!ring || !tooltip) return;

    const d  = ring.dataset;
    const s1 = parseFloat(d.s1);
    const s2 = parseFloat(d.s2);
    const s3 = parseFloat(d.s3);
    const s4 = parseFloat(d.s4);

    const segments = [
        { from: 0,  to: s1, label: d.seg1Label, val: d.seg1Val, color: d.seg1Color },
        { from: s1, to: s2, label: d.seg2Label, val: d.seg2Val, color: d.seg2Color },
        { from: s2, to: s3, label: d.seg3Label, val: d.seg3Val, color: d.seg3Color },
        { from: s3, to: s4, label: d.seg4Label, val: d.seg4Val, color: d.seg4Color },
    ];

    function getSegmentAt(pct) {
        for (const seg of segments) {
            if (pct >= seg.from && pct < seg.to) return seg;
        }
        return null;
    }

    ring.addEventListener('mousemove', function (e) {
        const rect   = ring.getBoundingClientRect();
        const cx     = rect.left + rect.width  / 2;
        const cy     = rect.top  + rect.height / 2;
        const dx     = e.clientX - cx;
        const dy     = e.clientY - cy;
        const r      = Math.sqrt(dx * dx + dy * dy);
        const outerR = rect.width / 2;
        const innerR = outerR * 0.56;

        // Only react when hovering the actual ring band
        if (r < innerR || r > outerR) {
            tooltip.style.display = 'none';
            return;
        }

        // Angle: 0° = top, clockwise; conic-gradient starts at 12-o'clock
        let angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
        if (angle < 0) angle += 360;
        const pct = angle / 360 * 100;

        const seg = getSegmentAt(pct);
        if (!seg || seg.from === seg.to) {
            tooltip.style.display = 'none';
            return;
        }

        tooltip.style.display    = 'block';
        tooltip.style.borderLeftColor = seg.color;
        tooltip.innerHTML = `<span style="font-weight:600;color:${seg.color}">${seg.label}</span><br>
                             <span style="font-size:.85em;opacity:.85">${seg.val}</span>`;

        // Position tooltip near cursor, keep it on screen
        const tw = tooltip.offsetWidth;
        const th = tooltip.offsetHeight;
        let tx = e.clientX + 14;
        let ty = e.clientY - th / 2;
        if (tx + tw > window.innerWidth  - 8) tx = e.clientX - tw - 14;
        if (ty < 4) ty = 4;
        if (ty + th > window.innerHeight - 4) ty = window.innerHeight - th - 4;
        tooltip.style.left = tx + 'px';
        tooltip.style.top  = ty + 'px';
    });

    ring.addEventListener('mouseleave', function () {
        tooltip.style.display = 'none';
    });

    // ── Click / tap: show info panel + highlight legend row ──────────────
    const infoPanel = document.getElementById('donutInfoPanel');
    const infoLabel = document.getElementById('donutInfoLabel');
    const infoValue = document.getElementById('donutInfoValue');
    const infoPct   = document.getElementById('donutInfoPct');
    const totalVal  = {{ $ovTotalExp }};

    function showInfoPanel(seg) {
        if (!infoPanel || !seg || seg.from === seg.to) return;
        infoPanel.style.display    = 'block';
        infoPanel.style.borderLeftColor = seg.color;
        infoLabel.textContent      = seg.label;
        infoLabel.style.color      = seg.color;
        infoValue.textContent      = Number(seg.val.replace(/,/g,'')).toLocaleString();
        infoValue.style.color      = seg.color;
        const pct = totalVal > 0 ? ((seg.to - seg.from)).toFixed(1) : 0;
        infoPct.textContent        = pct + '% ' + '{{ __("of total expected") }}';

        // Highlight legend row
        document.querySelectorAll('.donut-legend-row').forEach(r => r.style.background = '');
        const idx = segments.indexOf(seg) + 1;
        const row = document.querySelector(`.donut-legend-row[data-seg="${idx}"]`);
        if (row) row.style.background = seg.color + '18';
    }

    ring.addEventListener('click', function (e) {
        const rect   = ring.getBoundingClientRect();
        const cx     = rect.left + rect.width  / 2;
        const cy     = rect.top  + rect.height / 2;
        const dx     = e.clientX - cx;
        const dy     = e.clientY - cy;
        const r      = Math.sqrt(dx * dx + dy * dy);
        const outerR = rect.width / 2;
        const innerR = outerR * 0.56;
        if (r < innerR || r > outerR) return;

        let angle = Math.atan2(dy, dx) * (180 / Math.PI) + 90;
        if (angle < 0) angle += 360;
        const pct = angle / 360 * 100;
        const seg = getSegmentAt(pct);
        showInfoPanel(seg);
    });

    // Legend row click handler (for rows with onclick="donutClickLegend(n)")
    window.donutClickLegend = function(idx) {
        showInfoPanel(segments[idx]);
    };
})();

// ── AI Chat ───────────────────────────────────────────────────────────────
(function () {
    const input   = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSend');
    const msgs    = document.getElementById('chatMessages');
    const quickBtns = document.querySelectorAll('.quick-q');
    if (!input || !sendBtn || !msgs) return;

    function addBubble(text, type, links) {
        const div = document.createElement('div');
        div.className = 'chat-bubble chat-bubble-' + type;

        // Convert **bold** markdown to <strong>
        let html = text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n•/g, '<br>•');
        div.innerHTML = html;

        if (links && links.length) {
            links.forEach(function(l) {
                if (l.url) {
                    const a = document.createElement('a');
                    a.href = l.url;
                    a.className = 'chat-bubble-link d-block';
                    a.innerHTML = '<i class="ti ti-external-link"></i>' + l.label;
                    div.appendChild(a);
                }
            });
        }
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function sendMessage(text) {
        if (!text.trim()) return;
        addBubble(text, 'user', []);
        input.value = '';
        sendBtn.disabled = true;

        const spinner = document.createElement('div');
        spinner.className = 'chat-bubble chat-bubble-ai text-muted';
        spinner.id = 'chatTyping';
        spinner.innerHTML = '<i class="ti ti-dots me-1"></i>{{ __("Thinking…") }}';
        msgs.appendChild(spinner);
        msgs.scrollTop = msgs.scrollHeight;

        fetch('{{ route("ai.chat") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ message: text })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            const typing = document.getElementById('chatTyping');
            if (typing) typing.remove();
            if (data.error) {
                addBubble(data.error, 'ai', []);
            } else {
                addBubble(data.answer, 'ai', data.links || []);
            }
        })
        .catch(function() {
            const typing = document.getElementById('chatTyping');
            if (typing) typing.remove();
            addBubble('{{ __("Sorry, something went wrong. Please try again.") }}', 'ai', []);
        })
        .finally(function() {
            sendBtn.disabled = false;
        });
    }

    sendBtn.addEventListener('click', function() { sendMessage(input.value); });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); sendMessage(input.value); }
    });
    quickBtns.forEach(function(btn) {
        btn.addEventListener('click', function() { sendMessage(btn.dataset.q); });
    });
})();
</script>
@endpush
@endsection
