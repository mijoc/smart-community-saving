@extends('layouts.app')
@section('title', 'Overview')
@section('content')

<x-page_header :title="__('Organization overview')" :pretitle="__('Dashboard')">
    <x-slot name="actions">
        @can('create', App\Models\Contribution::class)
        <a href="{{ route('payments.create') }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i> {{ __('Record payment') }}
        </a>
        @endcan
        @can('create', App\Models\CashbookEntry::class)
        <a href="{{ route('cashbook.create', ['type' => 'income']) }}" class="btn btn-success">
            <i class="ti ti-arrow-down-circle me-1"></i> {{ __('Deposit') }}
        </a>
        <a href="{{ route('cashbook.create', ['type' => 'expense']) }}" class="btn btn-danger">
            <i class="ti ti-arrow-up-circle me-1"></i> {{ __('Withdrawal') }}
        </a>
        @endcan
        @if(auth()->user()->hasAnyRole(['super_admin','group_admin','treasurer','secretary']))
        <form method="POST" action="{{ route('arrears.run') }}" class="d-inline"
              onsubmit="return confirm('{{ __('Calculate late fees for overdue contributions in the active group now?') }}')">
            @csrf
            <input type="hidden" name="group_id" value="{{ session('active_group_id') }}">
            <button type="submit" class="btn btn-outline-warning"
                    title="{{ __('Run the arrears engine now. The automatic daily run is not affected.') }}">
                <i class="ti ti-calculator me-1"></i> {{ __('Calculate late fees') }}
            </button>
        </form>
        @endif
    </x-slot>
</x-page_header>

@if(! empty($personal))
<div class="card mt-3 border-primary-subtle">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">{{ __('My summary') }} {{ ($activeGroup ?? null) ? '· '.$activeGroup->name : '' }}</h3>
        <div class="card-actions">
            <a href="{{ route('treasury.member', $personal['member']) }}" class="btn btn-sm btn-outline-success">
                <i class="ti ti-building-bank me-1"></i>{{ __('My equity & share-out') }}
            </a>
            <a href="{{ route('passbooks.show', $personal['member']) }}" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-book me-1"></i>{{ __('My passbook') }}
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row row-cards">
            @foreach([
                [__('Pending'),  $personal['stats']['pending'],                          'yellow', 'ti-clock'],
                [__('Overdue'),  $personal['stats']['overdue'],                          'red',    'ti-alert-triangle'],
                [__('Paid'),     $personal['stats']['paid'],                             'green',  'ti-check'],
                [__('Arrears'),  number_format($personal['stats']['arrears'], 0),        'pink',   'ti-receipt-2'],
            ] as [$label,$val,$color,$icon])
            <div class="col-sm-6 col-lg-3">
                <div class="d-flex align-items-center">
                    <div class="bg-{{ $color }}-lt rounded p-2 me-2"><i class="ti {{ $icon }} fs-3 text-{{ $color }}"></i></div>
                    <div>
                        <div class="text-muted small text-uppercase">{{ $label }}</div>
                        <div class="h3 mb-0">{{ $val }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="row row-cards mt-3">
    @php
        $isSuper = auth()->user()->isSuperAdmin();
        // Each card: [label, value, icon, color, optional sub-label]
        // When sub-label is present the card shows "<label>: <value>" on the
        // top line and the sub-label as the big value underneath.
        $cards = [
            [__('Total expected'),        number_format($stats['total_expected'] ?? 0, 0),                'ti-coin',            'lime'],
            [__('Group profit'),          number_format($stats['group_profit'] ?? 0, 0),                  'ti-trending-up',     'indigo'],
            [__('Current balance'),       number_format($stats['current_balance'] ?? 0, 0),               'ti-wallet',          'green'],
            [__('Pending loans'),         number_format($stats['pending_loans'] ?? 0),                    'ti-cash-banknote',   'orange', number_format($stats['pending_loans_amount'] ?? 0, 0)],
            [__('Pending contributions'), number_format($stats['contributions_pending']),                 'ti-clipboard-list',  'yellow', number_format($stats['contributions_pending_amount'] ?? 0, 0)],
            [__('Punishments'), number_format($stats['attendance_outstanding'] ?? 0, 0), 'ti-calendar-x',      'red'],
        ];
        if ($isSuper) {
            $cards[] = [__('Active groups'), number_format($stats['groups_count']),                       'ti-users-group',     'primary'];
        }
        $cards = array_merge($cards, [
            [__('Active members'),        number_format($stats['members_count']),                         'ti-users',           'azure'],
            [__('Overdue contributions'), number_format($stats['contributions_overdue']),                 'ti-alert-triangle',  'red', number_format($stats['contributions_overdue_amount'] ?? 0, 0)],
            [__('Open arrears'),          number_format($stats['open_arrears_amount'], 0),                'ti-receipt-2',       'pink'],
            [__('Collected this month'),  number_format($stats['collected_this_month'], 0),               'ti-cash',            'green'],
            [__('Other income (mo)'),     number_format($stats['other_income_month'] ?? 0, 0),            'ti-arrow-down-circle','teal'],
            [__('Expenses (mo)'),         number_format($stats['expenses_month']     ?? 0, 0),            'ti-arrow-up-circle', 'red'],
        ]);
    @endphp
    @foreach($cards as $ci => $card)
    @php
        [$label, $value, $icon, $color] = $card;
        $amount = $card[4] ?? null;
        $hint   = $card[5] ?? null;
    @endphp
    <div class="col-sm-6 col-lg-4">
        <div class="card stat-card" id="stat-card-{{ $ci }}" data-stat-index="{{ $ci }}">
            <div class="card-body d-flex align-items-center">
                <div class="bg-{{ $color }}-lt rounded p-3 me-3"><i class="ti {{ $icon }} fs-2 text-{{ $color }}"></i></div>
                <div>
                    @if($amount !== null)
                        <div class="text-muted small text-uppercase">{{ $label }}: <span class="text-body fw-semibold">{{ $value }}</span></div>
                        <div class="h1 mb-0">{{ $amount }}</div>
                    @else
                        <div class="text-muted small text-uppercase">{{ $label }}</div>
                        <div class="h1 mb-0">{{ $value }}</div>
                    @endif
                    @if($hint)
                        <div class="text-muted small mt-1">{{ $hint }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Fund Breakdown Donut Chart ─────────────────────────────────────── --}}
@php
$donutSegments = [
    ['label' => __('Current Balance'),        'value' => (float)($stats['current_balance'] ?? 0),                  'color' => '#2fb344', 'card_color' => 'green',  'index' => 2],
    ['label' => __('Loans Outstanding'),      'value' => (float)($stats['pending_loans_amount'] ?? 0),             'color' => '#f76707', 'card_color' => 'orange', 'index' => 3],
    ['label' => __('Pending Contributions'),  'value' => (float)($stats['contributions_pending_amount'] ?? 0),     'color' => '#f59f00', 'card_color' => 'yellow', 'index' => 4],
    ['label' => __('Open Arrears'),           'value' => (float)($stats['open_arrears_amount'] ?? 0),              'color' => '#e64980', 'card_color' => 'pink',   'index' => 9],
    ['label' => __('Expenses (this month)'),  'value' => (float)($stats['expenses_month'] ?? 0),                   'color' => '#d63939', 'card_color' => 'red',    'index' => 11],
];
$donutTotal = array_sum(array_column($donutSegments, 'value'));
@endphp
@if($donutTotal > 0)
<div class="row row-cards mt-3" id="donut-section">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-chart-donut me-2 text-indigo"></i>{{ __('Group Fund Breakdown') }}</h3>
                <div class="card-actions">
                    <span class="text-muted small">{{ __('Click a segment to highlight the matching card') }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">

                    {{-- Donut chart --}}
                    <div class="flex-shrink-0 d-flex justify-content-center" style="width:100%;max-width:260px;margin:0 auto">
                        <div style="position:relative;width:220px;height:220px">
                            <canvas id="fundDonutChart" width="220" height="220"></canvas>
                            <div id="donut-center-label" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none">
                                <div class="text-muted small" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">{{ __('Total') }}</div>
                                <div class="fw-bold" style="font-size:1rem;line-height:1.2">{{ number_format($donutTotal, 0) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Legend list --}}
                    <div class="flex-grow-1 w-100">
                        <div id="donut-legend" style="border-radius:8px;overflow:hidden;border:1px solid #e8eaed">
                            {{-- Header --}}
                            <div class="d-flex align-items-center px-3 py-2" style="background:#f8fafc;border-bottom:1px solid #e8eaed">
                                <div style="width:12px"></div>
                                <div class="flex-grow-1 ms-3 small text-muted fw-semibold text-uppercase" style="letter-spacing:.04em">{{ __('Category') }}</div>
                                <div class="text-end small text-muted fw-semibold text-uppercase ms-2" style="min-width:110px">{{ __('Amount') }}</div>
                                <div class="text-end small text-muted fw-semibold text-uppercase ms-2 d-none d-sm-block" style="min-width:50px">%</div>
                            </div>
                            @php $visibleIndex = 0; @endphp
                            @foreach($donutSegments as $seg)
                            @if($seg['value'] > 0)
                            @php $pct = $donutTotal > 0 ? round($seg['value'] / $donutTotal * 100, 1) : 0; @endphp
                            <div class="donut-legend-item d-flex align-items-center px-3 py-2"
                                 data-segment-index="{{ $visibleIndex }}"
                                 data-card-index="{{ $seg['index'] }}"
                                 onclick="highlightSegment({{ $visibleIndex }}, {{ $seg['index'] }})"
                                 style="border-bottom:1px solid #f0f2f4;cursor:pointer;transition:background .15s;border-left:3px solid {{ $seg['color'] }}">
                                {{-- Color dot --}}
                                <span class="rounded-circle flex-shrink-0" style="width:12px;height:12px;background:{{ $seg['color'] }}"></span>
                                {{-- Label + bar --}}
                                <div class="flex-grow-1 ms-3 min-w-0">
                                    <div class="small fw-semibold text-truncate" style="max-width:180px">{{ $seg['label'] }}</div>
                                    <div class="progress mt-1" style="height:4px;background:#eee;border-radius:4px">
                                        <div class="progress-bar" style="width:{{ $pct }}%;background:{{ $seg['color'] }};border-radius:4px"></div>
                                    </div>
                                </div>
                                {{-- Amount --}}
                                <div class="text-end fw-semibold small ms-2" style="min-width:110px;color:{{ $seg['color'] }}">
                                    {{ number_format($seg['value'], 0) }}
                                </div>
                                {{-- Percent --}}
                                <div class="text-end small text-muted ms-2 d-none d-sm-block" style="min-width:50px">
                                    {{ $pct }}%
                                </div>
                            </div>
                            @php $visibleIndex++; @endphp
                            @endif
                            @endforeach
                            {{-- Total row --}}
                            <div class="d-flex align-items-center px-3 py-2" style="background:#f8fafc;border-left:3px solid #4f46e5">
                                <span class="rounded-circle flex-shrink-0" style="width:12px;height:12px;background:#4f46e5"></span>
                                <div class="flex-grow-1 ms-3 small fw-bold text-uppercase" style="letter-spacing:.04em">{{ __('Total') }}</div>
                                <div class="text-end fw-bold small ms-2" style="min-width:110px;color:#4f46e5">{{ number_format($donutTotal, 0) }}</div>
                                <div class="text-end small text-muted ms-2 d-none d-sm-block" style="min-width:50px">100%</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const segments = @json(array_values(array_filter($donutSegments, fn($s) => $s['value'] > 0)));
    if (!segments.length) return;

    const labels  = segments.map(s => s.label);
    const values  = segments.map(s => s.value);
    const colors  = segments.map(s => s.color);
    const alphas  = segments.map(s => s.color + 'cc');

    const ctx = document.getElementById('fundDonutChart');
    if (!ctx) return;

    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: alphas,
                borderColor: colors,
                borderWidth: 2,
                hoverBackgroundColor: colors,
                hoverBorderWidth: 3,
            }]
        },
        options: {
            cutout: '68%',
            responsive: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const val = ctx.parsed;
                            const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                            const pct   = total > 0 ? (val/total*100).toFixed(1) : 0;
                            return ' ' + val.toLocaleString() + '  (' + pct + '%)';
                        }
                    }
                }
            },
            onClick: function(evt, elements) {
                if (elements.length) {
                    const idx = elements[0].index;
                    const seg = segments[idx];
                    highlightSegment(idx, seg.index);
                }
            }
        }
    });

    window.highlightSegment = function(segIdx, cardIdx) {
        // Reset all cards
        document.querySelectorAll('.stat-card').forEach(c => {
            c.style.boxShadow = '';
            c.style.transform = '';
        });
        // Reset legend rows — restore original left border color
        document.querySelectorAll('.donut-legend-item').forEach((el, i) => {
            el.style.background = '';
            el.style.borderLeftWidth = '3px';
            el.style.borderLeftColor = segments[i] ? segments[i].color : '#ddd';
        });
        // Reset chart colors
        chart.data.datasets[0].backgroundColor = segments.map(s => s.color + 'cc');
        chart.data.datasets[0].borderWidth = segments.map(() => 2);

        // Dim other segments, brighten selected
        const newBg = segments.map((s, i) => i === segIdx ? s.color : s.color + '33');
        const newBw = segments.map((_, i) => i === segIdx ? 4 : 1);
        chart.data.datasets[0].backgroundColor = newBg;
        chart.data.datasets[0].borderWidth = newBw;
        chart.update('none');

        // Highlight matching legend row
        const legendItem = document.querySelector(`.donut-legend-item[data-segment-index="${segIdx}"]`);
        if (legendItem) {
            legendItem.style.background = segments[segIdx].color + '15';
            legendItem.style.borderLeftWidth = '5px';
            legendItem.style.borderLeftColor = segments[segIdx].color;
        }

        // Scroll to & pulse-highlight the matching stat card
        const card = document.getElementById('stat-card-' + cardIdx);
        if (card) {
            card.style.boxShadow = '0 0 0 3px ' + segments[segIdx].color;
            card.style.transform = 'scale(1.03)';
            card.style.transition = 'all .25s';
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => { card.style.boxShadow = ''; card.style.transform = ''; }, 2500);
        }

        // Update donut center label
        const cl = document.getElementById('donut-center-label');
        if (cl) {
            cl.querySelector('.fw-bold').textContent = values[segIdx].toLocaleString();
            cl.querySelector('.text-muted').textContent = labels[segIdx];
        }
    };

    // Store the initial total for center label reset.
    // `values` and `labels` are declared above and reused here.
    const total  = values.reduce((a,b)=>a+b,0);

    // Reset center label on chart area click (no segment)
    ctx.addEventListener('click', function(e) {
        const pts = chart.getElementsAtEventForMode(e,'nearest',{intersect:true},false);
        if (!pts.length) {
            document.querySelectorAll('.stat-card').forEach(c => { c.style.boxShadow=''; c.style.transform=''; });
            document.querySelectorAll('.donut-legend-item').forEach(el => { el.style.border='2px solid transparent'; el.style.background=''; });
            chart.data.datasets[0].backgroundColor = segments.map(s => s.color + 'cc');
            chart.data.datasets[0].borderWidth = segments.map(() => 2);
            chart.update('none');
            const cl = document.getElementById('donut-center-label');
            if (cl) { cl.querySelector('.fw-bold').textContent = total.toLocaleString(); cl.querySelector('.text-muted').textContent = '{{ __("Total") }}'; }
        }
    });
})();
</script>
@endpush

@php $isMember = auth()->user()->hasRole('member'); @endphp
{{-- ── This Month's Financial Summary ───────────────────────────────── --}}
@if($monthlyFinancial['opening_balance'] || $monthlyFinancial['contributions'] || $monthlyFinancial['closing_balance'])
<div class="row row-cards mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-report-money me-2 text-indigo"></i>
                    {{ __('This Month\'s Financial Summary') }}
                    <span class="text-muted ms-2 fw-normal small">{{ now()->format('F Y') }}</span>
                </h3>
                <div class="card-actions">
                    <a href="{{ route('reports.monthly') }}" class="btn btn-sm btn-outline-indigo">
                        <i class="ti ti-external-link me-1"></i>{{ __('Full report') }}
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter mb-0" style="font-size:.88rem">
                        <tbody>
                            {{-- Opening --}}
                            <tr style="background:#f8fafc">
                                <td class="ps-3 py-2 text-muted fw-semibold" style="width:55%">
                                    <i class="ti ti-circle-dot me-1 text-azure"></i>{{ __('Opening Balance (start of month)') }}
                                </td>
                                <td class="text-end pe-3 fw-bold text-azure">{{ number_format($monthlyFinancial['opening_balance'], 0) }}</td>
                            </tr>
                            {{-- IN flows --}}
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-down-right me-1 text-green"></i>{{ __('Member contributions received') }}
                                </td>
                                <td class="text-end pe-3 text-green fw-medium">+ {{ number_format($monthlyFinancial['contributions'], 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-down-right me-1 text-green"></i>{{ __('Loan repayments (principal)') }}
                                </td>
                                <td class="text-end pe-3 text-green fw-medium">+ {{ number_format($monthlyFinancial['repayments'], 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-down-right me-1 text-teal"></i>{{ __('Interest earned') }}
                                </td>
                                <td class="text-end pe-3 text-teal fw-medium">+ {{ number_format($monthlyFinancial['interest_earned'], 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-down-right me-1 text-cyan"></i>{{ __('Other income (cashbook)') }}
                                </td>
                                <td class="text-end pe-3 text-cyan fw-medium">+ {{ number_format($monthlyFinancial['other_income'], 0) }}</td>
                            </tr>
                            {{-- OUT flows --}}
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-up-right me-1 text-orange"></i>{{ __('Loans disbursed') }}
                                </td>
                                <td class="text-end pe-3 text-orange fw-medium">− {{ number_format($monthlyFinancial['disbursements'], 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-up-right me-1 text-pink"></i>{{ __('Savings withdrawals') }}
                                </td>
                                <td class="text-end pe-3 text-pink fw-medium">− {{ number_format($monthlyFinancial['withdrawals'], 0) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4 py-1 text-muted small">
                                    <i class="ti ti-arrow-up-right me-1 text-red"></i>{{ __('Expenses (cashbook)') }}
                                </td>
                                <td class="text-end pe-3 text-red fw-medium">− {{ number_format($monthlyFinancial['expenses'], 0) }}</td>
                            </tr>
                            {{-- Closing --}}
                            <tr style="background:#eef2ff;border-top:2px solid #4f46e5">
                                <td class="ps-3 py-2 fw-bold" style="color:#4f46e5">
                                    <i class="ti ti-circle-check-filled me-1"></i>{{ __('Closing Balance (end of month)') }}
                                </td>
                                <td class="text-end pe-3 fw-bold fs-5" style="color:#4f46e5">{{ number_format($monthlyFinancial['closing_balance'], 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row row-cards mt-2">
    <div class="{{ $isMember ? 'col-12' : 'col-lg-7' }}">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('Collections — last 12 months') }}</h3></div>
            <div class="card-body">
                <table class="table table-vcenter">
                    <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Collected') }}</th></tr></thead>
                    <tbody>
                        @forelse($monthly as $row)
                        <tr><td>{{ $row->month }}</td><td class="text-end">{{ number_format($row->total, 0) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted">{{ __('No collection data yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @unless($isMember)
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('Top groups (last 90 days)') }}</h3></div>
            <div class="list-group list-group-flush">
                @forelse($topGroups as $g)
                <a href="{{ route('groups.show', $g) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fw-semibold">{{ $g->name }}</div>
                            <div class="small text-muted">{{ $g->members_count }} members</div>
                        </div>
                        <span class="badge bg-green-lt fs-6">{{ number_format($g->collected_total ?? 0, 0) }}</span>
                    </div>
                </a>
                @empty
                <div class="list-group-item text-muted">{{ __('No groups yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
    @endunless
</div>

<div class="row row-cards mt-2">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('Recent payments') }}</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Member') }}</th><th>{{ __('Group') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Date') }}</th></tr></thead>
                    <tbody>
                        @forelse($recentPayments as $p)
                        <tr>
                            <td><a href="{{ route('payments.show', $p) }}">{{ $p->reference }}</a></td>
                            <td>{{ $p->member?->full_name }} <span class="text-muted small">{{ $p->member?->member_no }}</span></td>
                            <td>{{ $p->group?->name }}</td>
                            <td><span class="badge bg-blue-lt">{{ str_replace('_',' ',$p->method) }}</span></td>
                            <td class="text-end">{{ number_format($p->amount, 0) }}</td>
                            <td class="text-muted">{{ $p->paid_on->format('Y-m-d') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ __('No payments yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('Top open arrears') }}</h3>
                @can('viewAny', App\Models\Member::class)
                    @unless(auth()->user()->hasRole('member'))
                    <div class="card-actions">
                        <form method="POST" action="{{ route('arrears.run') }}">@csrf
                            <button class="btn btn-sm btn-outline-warning"><i class="ti ti-refresh me-1"></i>{{ __('Run engine') }}</button>
                        </form>
                    </div>
                    @endunless
                @endcan
            </div>
            <div class="list-group list-group-flush">
                @forelse($arrears as $a)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="fw-semibold">{{ $a->member?->full_name }}</div>
                            <div class="small text-muted">{{ $a->group?->name }} · {{ $a->days_overdue }}d overdue</div>
                        </div>
                        <span class="badge bg-red-lt fs-6">{{ number_format($a->outstanding_amount, 0) }}</span>
                    </div>
                </div>
                @empty
                <div class="list-group-item text-muted">{{ __('No open arrears.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ═══ AI INSIGHTS FOR ADMINS ═══ --}}
@if(!empty($aiAnomalies) || $aiHealth || $aiRiskSummary)
<div class="row row-cards mt-3">

    {{-- AI 3: Group Health Summary --}}
    @if($aiHealth)
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="ti ti-sparkles me-1 text-{{ $aiHealth['status_label']['color'] }}"></i>
                <span class="fw-semibold">{{ __('AI — Group Health Summary') }}</span>
                <span class="badge bg-{{ $aiHealth['status_label']['color'] }}-lt text-{{ $aiHealth['status_label']['color'] }} ms-2">{{ __($aiHealth['status_label']['label']) }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="display-4 fw-bold text-{{ $aiHealth['status_label']['color'] }}" style="line-height:1">{{ $aiHealth['score'] }}</div>
                    <div class="flex-grow-1">
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-{{ $aiHealth['status_label']['color'] }}" style="width:{{ $aiHealth['score'] }}%"></div>
                        </div>
                        <div class="small text-muted mt-1">{{ __('Health score') }} / 100</div>
                    </div>
                </div>
                @foreach($aiHealth['lines'] as $line)
                <div class="d-flex gap-2 mb-1">
                    <i class="ti ti-circle-dot text-{{ $aiHealth['status_label']['color'] }}" style="margin-top:2px;flex-shrink:0"></i>
                    <div class="small">{{ $line }}</div>
                </div>
                @endforeach
                <div class="row row-cards mt-2 g-2">
                    @foreach([
                        [__('Collection rate'), ($aiHealth['metrics']['rate'] ?? 0) . '%', 'success'],
                        [__('Overdue members'), $aiHealth['metrics']['overdueMembers'] ?? 0, 'warning'],
                        [__('Active loans'), $aiHealth['metrics']['activeLoans'] ?? 0, 'info'],
                    ] as [$lbl,$val,$col])
                    <div class="col-4 text-center">
                        <div class="fw-bold text-{{ $col }}">{{ $val }}</div>
                        <div class="small text-muted">{{ $lbl }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- AI 4: Anomaly Detection --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="ti ti-radar me-1 text-orange"></i>
                <span class="fw-semibold">{{ __('AI — Anomaly Detection') }}</span>
                @if(count($aiAnomalies ?? []) === 0)
                <span class="badge bg-success-lt text-success ms-2">{{ __('All clear') }}</span>
                @else
                <span class="badge bg-danger-lt text-danger ms-2">{{ count($aiAnomalies) }} {{ __('alert(s)') }}</span>
                @endif
            </div>
            <div class="card-body">
                @forelse($aiAnomalies ?? [] as $anomaly)
                <div class="alert alert-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }} d-flex gap-2 py-2 mb-2">
                    <i class="ti {{ $anomaly['icon'] }} mt-1 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold small">{{ __($anomaly['title']) }}</div>
                        <div style="font-size:.78rem">{{ $anomaly['message'] }}</div>
                    </div>
                </div>
                @empty
                <div class="d-flex align-items-center gap-3 text-success">
                    <i class="ti ti-circle-check fs-2"></i>
                    <div>
                        <div class="fw-semibold">{{ __('No anomalies detected') }}</div>
                        <div class="small text-muted">{{ __('All financial patterns look normal this month.') }}</div>
                    </div>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- AI 1: Member Risk Distribution --}}
    @if($aiRiskSummary)
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="ti ti-shield me-1 text-indigo"></i>
                <span class="fw-semibold">{{ __('AI — Member Loan Risk Summary') }}</span>
                <span class="text-muted small ms-2">{{ __('Based on payment history, arrears & loan repayment') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    @foreach([
                        ['low',    'success', __('Low Risk'),    'ti-shield-check'],
                        ['medium', 'warning', __('Medium Risk'), 'ti-shield-half'],
                        ['high',   'danger',  __('High Risk'),   'ti-shield-x'],
                    ] as [$lvl,$col,$lbl,$ico])
                    @php $cnt = $aiRiskSummary['counts'][$lvl] ?? 0; @endphp
                    <div class="col-4 text-center">
                        <div class="bg-{{ $col }}-lt rounded p-3">
                            <i class="ti {{ $ico }} fs-2 text-{{ $col }}"></i>
                            <div class="display-5 fw-bold text-{{ $col }} mt-1">{{ $cnt }}</div>
                            <div class="small text-muted">{{ $lbl }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @if(!empty($aiRiskSummary['top_risk']))
                <div class="fw-semibold small text-muted text-uppercase mb-2">{{ __('Members needing attention') }}</div>
                @foreach($aiRiskSummary['top_risk'] as $r)
                <div class="d-flex align-items-center gap-2 mb-1 p-2 rounded" style="background:#fafafa">
                    <span class="badge bg-{{ $r['color'] }}-lt text-{{ $r['color'] }}" style="min-width:60px;text-align:center">{{ $r['score'] }}/100</span>
                    <span class="small fw-medium">{{ $r['name'] }}</span>
                    <span class="badge bg-{{ $r['color'] }}-lt text-{{ $r['color'] }} ms-auto">{{ __($r['label']) }}</span>
                    <a href="{{ route('treasury.member', $r['member_id']) }}" class="btn btn-sm btn-ghost-secondary py-0 px-1"><i class="ti ti-external-link"></i></a>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>
    @endif

</div>
@endif

@endsection
