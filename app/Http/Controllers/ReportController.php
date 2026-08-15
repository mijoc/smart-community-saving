<?php

namespace App\Http\Controllers;

use App\Models\Arrear;
use App\Models\CashbookEntry;
use App\Models\Contribution;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use App\Models\Rotation;
use App\Models\RotationPayout;
use App\Services\ReportExportService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;

/**
 * ReportController
 * ----------------
 * Exposes one entry-point — `reports.export` — that takes a report key
 * (cashbook, loans, contributions, …) plus a target format (pdf|xlsx|docx|csv)
 * and produces a downloadable file using ReportExportService.
 *
 * Each {@see exportXxx} helper mirrors the matching `index()` action's
 * filters and authorisation rules so the downloaded file always reflects
 * what the user actually sees on screen.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportExportService $exporter) {}

    public function export(Request $request, string $report, string $format)
    {
        $method = 'export'.str_replace(' ', '', ucwords(str_replace('_', ' ', $report)));

        if (! method_exists($this, $method)) {
            abort(404, "Unknown report: {$report}");
        }

        return $this->{$method}($request, $format);
    }

    // ==================================================================
    //  Cashbook
    // ==================================================================
    protected function exportCashbook(Request $request, string $format)
    {
        $this->authorize('viewAny', CashbookEntry::class);

        $q = CashbookEntry::query()->with(['group:id,name', 'recorder:id,name']);
        $this->scopeToActiveGroup($q);
        if (! auth()->user()->hasAnyRole(['super_admin', 'group_admin'])) {
            $q->where('category', '!=', CashbookEntry::REGULARIZATION_CATEGORY);
        }

        if ($g    = $request->integer('group_id'))            $q->where('group_id', $g);
        if ($t    = $request->string('type')->toString())     $q->where('type', $t);
        if ($cat  = $request->string('category')->toString()) $q->where('category', $cat);
        if ($from = $request->string('from')->toString())     $q->whereDate('occurred_on', '>=', $from);
        if ($to   = $request->string('to')->toString())       $q->whereDate('occurred_on', '<=', $to);

        $entries = $q->orderByDesc('occurred_on')->orderByDesc('id')->get();

        $income  = (float) $entries->where('type', 'income')->sum('amount');
        $expense = (float) $entries->where('type', 'expense')->sum('amount');

        $headers = ['Date', 'Reference', 'Group', 'Type', 'Category', 'Counterparty',
                    'Method', 'Channel ref', 'Amount', 'Recorded by', 'Notes'];

        $rows = $entries->map(fn ($e) => [
            optional($e->occurred_on)->format('Y-m-d'),
            $e->reference,
            $e->group?->name,
            ucfirst($e->type),
            CashbookEntry::categoryLabel($e->type, $e->category),
            $e->counterparty,
            ucfirst(str_replace('_', ' ', (string) $e->method)),
            $e->channel_ref,
            number_format((float) $e->amount, 2, '.', ''),
            $e->recorder?->name,
            $e->notes,
        ])->all();

        return $this->exporter->export($format, 'Cashbook report', $headers, $rows, [
            'Filters'     => $this->describeFilters($request, ['type', 'category', 'from', 'to', 'group_id']),
            'Total income'  => number_format($income, 0),
            'Total expense' => number_format($expense, 0),
            'Net'           => number_format($income - $expense, 0),
            'Records'       => (string) count($rows),
        ], 'cashbook-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Loans
    // ==================================================================
    protected function exportLoans(Request $request, string $format)
    {
        $this->authorize('viewAny', Loan::class);

        $q = Loan::query()->with(['group:id,name', 'member:id,full_name,member_no']);
        $this->scopeToActiveGroup($q);

        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }
        if ($g = $request->integer('group_id'))          $q->where('group_id', $g);
        if ($m = $request->integer('member_id'))         $q->where('member_id', $m);
        if ($s = $request->string('status')->toString()) $q->where('status', $s);

        $loans = $q->orderByDesc('requested_on')->get();

        $headers = ['Reference', 'Group', 'Member', 'Member #', 'Status', 'Principal',
                    'Interest %', 'Term (mo)', 'Total interest', 'Total repayable',
                    'Repaid', 'Outstanding', 'Requested', 'Approved', 'Disbursed', 'Due', 'Purpose'];

        $rows = $loans->map(fn ($l) => [
            $l->reference,
            $l->group?->name,
            $l->member?->full_name,
            $l->member?->member_no,
            ucfirst(str_replace('_', ' ', (string) $l->status)),
            number_format((float) $l->principal,        2, '.', ''),
            number_format((float) $l->interest_rate_pct, 2, '.', ''),
            (int) $l->term_months,
            number_format((float) $l->total_interest,   2, '.', ''),
            number_format((float) $l->total_repayable,  2, '.', ''),
            number_format((float) $l->amount_repaid,    2, '.', ''),
            number_format((float) $l->outstanding,      2, '.', ''),
            optional($l->requested_on)->format('Y-m-d'),
            optional($l->approved_on)->format('Y-m-d'),
            optional($l->disbursed_on)->format('Y-m-d'),
            optional($l->due_on)->format('Y-m-d'),
            $l->purpose,
        ])->all();

        $totals = [
            'Loans'         => (string) $loans->count(),
            'Total principal'   => number_format((float) $loans->sum('principal'), 0),
            'Total outstanding' => number_format((float) $loans->sum('outstanding'), 0),
            'Total repaid'      => number_format((float) $loans->sum('amount_repaid'), 0),
            'Filters'           => $this->describeFilters($request, ['status', 'group_id', 'member_id']),
        ];

        return $this->exporter->export($format, 'Loans report', $headers, $rows, $totals,
            'loans-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Contributions
    // ==================================================================
    protected function exportContributions(Request $request, string $format)
    {
        $this->authorize('viewAny', Contribution::class);

        $q = Contribution::query()->with(['group:id,name', 'member:id,full_name,member_no']);
        $this->scopeToActiveGroup($q);

        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }
        if ($g    = $request->integer('group_id'))          $q->where('group_id', $g);
        if ($m    = $request->integer('member_id'))         $q->where('member_id', $m);
        if ($s    = $request->string('status')->toString()) $q->where('status', $s);
        if ($t    = $request->string('type')->toString())   $q->where('type', $t);
        if ($from = $request->string('from')->toString())   $q->whereDate('due_on', '>=', $from);
        if ($to   = $request->string('to')->toString())     $q->whereDate('due_on', '<=', $to);

        $items = $q->orderByDesc('due_on')->get();

        $headers = ['Group', 'Member', 'Member #', 'Type', 'Period start', 'Period end',
                    'Due on', 'Expected', 'Paid', 'Late fee', 'Status', 'Paid on'];

        $rows = $items->map(fn ($c) => [
            $c->group?->name,
            $c->member?->full_name,
            $c->member?->member_no,
            ucfirst(str_replace('_', ' ', (string) $c->type)),
            optional($c->period_start)->format('Y-m-d'),
            optional($c->period_end)->format('Y-m-d'),
            optional($c->due_on)->format('Y-m-d'),
            number_format((float) $c->expected_amount, 2, '.', ''),
            number_format((float) $c->paid_amount,     2, '.', ''),
            number_format((float) $c->late_fee_amount, 2, '.', ''),
            ucfirst(str_replace('_', ' ', (string) $c->status)),
            optional($c->paid_on)->format('Y-m-d'),
        ])->all();

        return $this->exporter->export($format, 'Contributions report', $headers, $rows, [
            'Records'   => (string) $items->count(),
            'Expected'  => number_format((float) $items->sum('expected_amount'), 0),
            'Paid'      => number_format((float) $items->sum('paid_amount'), 0),
            'Late fees' => number_format((float) $items->sum('late_fee_amount'), 0),
            'Filters'   => $this->describeFilters($request, ['type', 'status', 'from', 'to', 'group_id', 'member_id']),
        ], 'contributions-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Payments
    // ==================================================================
    protected function exportPayments(Request $request, string $format)
    {
        $this->authorize('viewAny', Contribution::class);

        $q = Payment::query()->with(['group:id,name', 'member:id,full_name,member_no', 'receiver:id,name']);
        $this->scopeToActiveGroup($q);

        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }
        if ($g    = $request->integer('group_id'))        $q->where('group_id', $g);
        if ($m    = $request->integer('member_id'))       $q->where('member_id', $m);
        if ($from = $request->string('from')->toString()) $q->whereDate('paid_on', '>=', $from);
        if ($to   = $request->string('to')->toString())   $q->whereDate('paid_on', '<=', $to);

        $payments = $q->orderByDesc('paid_on')->get();

        $headers = ['Reference', 'Date', 'Group', 'Member', 'Member #',
                    'Amount', 'Method', 'Channel ref', 'Received by', 'Notes'];

        $rows = $payments->map(fn ($p) => [
            $p->reference,
            optional($p->paid_on)->format('Y-m-d'),
            $p->group?->name,
            $p->member?->full_name,
            $p->member?->member_no,
            number_format((float) $p->amount, 2, '.', ''),
            ucfirst(str_replace('_', ' ', (string) $p->method)),
            $p->channel_ref,
            $p->receiver?->name,
            $p->notes,
        ])->all();

        return $this->exporter->export($format, 'Payments report', $headers, $rows, [
            'Records'      => (string) $payments->count(),
            'Total amount' => number_format((float) $payments->sum('amount'), 0),
            'Filters'      => $this->describeFilters($request, ['from', 'to', 'group_id', 'member_id']),
        ], 'payments-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Arrears
    // ==================================================================
    protected function exportArrears(Request $request, string $format)
    {
        $this->authorize('viewAny', Contribution::class);

        $q = Arrear::query()->with([
            'group:id,name', 'member:id,full_name,member_no',
            'contribution:id,type,due_on,period_start',
        ]);
        $this->scopeToActiveGroup($q);

        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }
        if ($g = $request->integer('group_id'))          $q->where('group_id', $g);
        if ($s = $request->string('status')->toString()) $q->where('status', $s);

        $arrears = $q->orderByDesc('outstanding_amount')->get();

        $headers = ['Group', 'Member', 'Member #', 'Contribution type', 'Due on',
                    'First overdue', 'Days overdue', 'Late fee', 'Outstanding', 'Status'];

        $rows = $arrears->map(fn ($a) => [
            $a->group?->name,
            $a->member?->full_name,
            $a->member?->member_no,
            $a->contribution ? ucfirst(str_replace('_', ' ', (string) $a->contribution->type)) : '',
            optional($a->contribution?->due_on)->format('Y-m-d'),
            optional($a->first_overdue_on)->format('Y-m-d'),
            (int) $a->days_overdue,
            number_format((float) $a->late_fee_applied,   2, '.', ''),
            number_format((float) $a->outstanding_amount, 2, '.', ''),
            ucfirst((string) $a->status),
        ])->all();

        return $this->exporter->export($format, 'Arrears report', $headers, $rows, [
            'Records'           => (string) $arrears->count(),
            'Total outstanding' => number_format((float) $arrears->sum('outstanding_amount'), 0),
            'Total late fees'   => number_format((float) $arrears->sum('late_fee_applied'), 0),
            'Filters'           => $this->describeFilters($request, ['status', 'group_id']),
        ], 'arrears-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Rotations  (list of rotations with their summary)
    // ==================================================================
    protected function exportRotations(Request $request, string $format)
    {
        $this->authorize('viewAny', Rotation::class);

        $q = Rotation::query()->with(['group:id,name'])->withCount(['turns', 'members']);
        $this->scopeToActiveGroup($q);
        if ($g = $request->integer('group_id'))          $q->where('group_id', $g);
        if ($s = $request->string('status')->toString()) $q->where('status', $s);

        $rotations = $q->orderByDesc('starts_on')->get();

        $headers = ['Name', 'Group', 'Status', 'Frequency', 'Recipients/turn',
                    'Disbursement', 'Members', 'Turns', 'Starts on', 'Next turn on'];

        $rows = $rotations->map(function ($r) {
            $disb = match ($r->disbursement_method) {
                'full'       => 'Full cash on hand',
                'percentage' => number_format((float) $r->disbursement_pct, 0).' %',
                'fixed'      => number_format((float) $r->disbursement_amount, 0).' fixed',
                default      => $r->disbursement_method,
            };
            return [
                $r->name,
                $r->group?->name,
                ucfirst((string) $r->status),
                ucfirst((string) $r->frequency),
                (int) $r->recipients_per_turn,
                $disb,
                (int) $r->members_count,
                (int) $r->turns_count,
                optional($r->starts_on)->format('Y-m-d'),
                optional($r->next_turn_on)->format('Y-m-d'),
            ];
        })->all();

        return $this->exporter->export($format, 'Rotations report', $headers, $rows, [
            'Records' => (string) $rotations->count(),
            'Filters' => $this->describeFilters($request, ['status', 'group_id']),
        ], 'rotations-report-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Rotation payouts (every disbursement across rotations)
    // ==================================================================
    protected function exportRotationPayouts(Request $request, string $format)
    {
        $this->authorize('viewAny', Rotation::class);

        $q = RotationPayout::query()
            ->with(['rotation:id,name', 'member:id,full_name,member_no', 'group:id,name']);
        $this->scopeToActiveGroup($q);
        if ($g = $request->integer('group_id'))    $q->where('group_id', $g);
        if ($r = $request->integer('rotation_id')) $q->where('rotation_id', $r);

        $payouts = $q->orderByDesc('paid_on')->orderByDesc('id')->get();

        $headers = ['Date', 'Reference', 'Group', 'Rotation', 'Member',
                    'Member #', 'Amount', 'Method', 'Notes'];

        $rows = $payouts->map(fn ($p) => [
            optional($p->paid_on)->format('Y-m-d'),
            $p->reference,
            $p->group?->name,
            $p->rotation?->name,
            $p->member?->full_name,
            $p->member?->member_no,
            number_format((float) $p->amount, 2, '.', ''),
            ucfirst(str_replace('_', ' ', (string) $p->method)),
            $p->notes,
        ])->all();

        return $this->exporter->export($format, 'Rotation payouts report', $headers, $rows, [
            'Records'      => (string) $payouts->count(),
            'Total amount' => number_format((float) $payouts->sum('amount'), 0),
            'Filters'      => $this->describeFilters($request, ['rotation_id', 'group_id']),
        ], 'rotation-payouts-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Member passbook (single member, single group)
    // ==================================================================
    protected function exportPassbook(Request $request, string $format)
    {
        $memberId = $request->integer('member_id');
        $groupId  = $request->integer('group_id') ?: session('active_group_id');

        if (! $memberId || ! $groupId) abort(400, 'member_id and group_id are required.');

        $member = Member::findOrFail($memberId);
        $this->authorize('view', $member);

        $group = Group::findOrFail($groupId);

        $entries = PassbookEntry::where('member_id', $memberId)
            ->where('group_id', $groupId)
            ->orderBy('entry_date')->orderBy('id')->get();

        $headers = ['Date', 'Description', 'Category', 'Debit', 'Credit', 'Balance'];

        $rows = $entries->map(fn ($e) => [
            optional($e->entry_date)->format('Y-m-d'),
            $e->description,
            $e->category,
            (float) $e->debit  > 0 ? number_format((float) $e->debit,  2, '.', '') : '',
            (float) $e->credit > 0 ? number_format((float) $e->credit, 2, '.', '') : '',
            number_format((float) $e->balance, 2, '.', ''),
        ])->all();

        return $this->exporter->export(
            $format,
            "Passbook · {$member->full_name}",
            $headers,
            $rows,
            [
                'Member'   => $member->full_name.' (#'.$member->member_no.')',
                'Group'    => $group->name,
                'Currency' => $group->currency ?? '',
                'Records'  => (string) $entries->count(),
            ],
            'passbook-'.preg_replace('/\s+/', '_', $member->full_name).'-'.now()->format('Ymd-His'),
        );
    }

    // ==================================================================
    //  Treasury — member equity / balances for the active group
    // ==================================================================
    protected function exportTreasuryMembers(Request $request, string $format)
    {
        $treasury = app(TreasuryService::class);
        $u        = auth()->user();
        $activeId = session('active_group_id');

        if (! $activeId) abort(400, 'Pick a group first to export the treasury report.');

        $group = Group::findOrFail($activeId);
        $this->authorize('view', $group);

        $members = $group->members()->orderBy('full_name')->get(['members.id', 'members.full_name', 'members.member_no']);

        $headers = ['Member #', 'Member', 'Savings', 'Social fund', 'Fines/late',
                    'Total contributed', 'Loan principal left', 'Loan interest left',
                    'Arrears', 'Total debt', 'Profit share', 'Projected share-out'];

        $rows = [];
        foreach ($members as $m) {
            $s = $treasury->memberSummary($m, (int) $activeId);
            $rows[] = [
                $m->member_no,
                $m->full_name,
                number_format((float) ($s['savings_paid'] ?? 0), 2, '.', ''),
                number_format((float) ($s['social_fund_paid'] ?? 0), 2, '.', ''),
                number_format((float) ($s['fines_paid'] ?? 0), 2, '.', ''),
                number_format((float) ($s['total_contributed'] ?? 0), 2, '.', ''),
                number_format((float) ($s['loan_principal_outstanding'] ?? 0), 2, '.', ''),
                number_format((float) ($s['loan_interest_outstanding']  ?? 0), 2, '.', ''),
                number_format((float) ($s['arrears_outstanding'] ?? 0), 2, '.', ''),
                number_format((float) ($s['total_debt'] ?? 0), 2, '.', ''),
                number_format((float) ($s['profit_share'] ?? 0), 2, '.', ''),
                number_format((float) ($s['projected_share_out'] ?? 0), 2, '.', ''),
            ];
        }

        return $this->exporter->export($format, "Treasury — member equity ({$group->name})",
            $headers, $rows, [
                'Group'    => $group->name,
                'Currency' => $group->currency ?? '',
                'Members'  => (string) count($rows),
            ], 'treasury-members-'.preg_replace('/\s+/', '_', $group->name).'-'.now()->format('Ymd-His'));
    }

    // ==================================================================
    //  Group Loans Report — on-screen view
    // ==================================================================
    public function groupLoans(Request $request)
    {
        $this->authorize('viewAny', Loan::class);

        $groupOptions = $this->accessibleGroupOptions();
        $activeId     = session('active_group_id');
        $groupId      = $request->integer('group_id') ?: $activeId;

        $statuses = $request->string('status')->toString() ?: 'active';

        $q = Loan::query()
            ->with(['member:id,full_name,member_no,photo', 'group:id,name,currency'])
            ->orderBy('member_id')
            ->orderByDesc('disbursed_on');

        $this->scopeToActiveGroup($q);

        if ($groupId) $q->where('group_id', $groupId);

        if ($statuses === 'active') {
            $q->whereIn('status', ['disbursed', 'repaying']);
        } elseif ($statuses !== 'all') {
            $q->where('status', $statuses);
        }

        $loans = $q->get();

        // Group by member for the view
        $byMember = $loans->groupBy('member_id');

        $group = $groupId ? Group::find($groupId) : null;

        return view('reports.group_loans', [
            'byMember'     => $byMember,
            'loans'        => $loans,
            'group'        => $group,
            'groupOptions' => $groupOptions,
            'statuses'     => $statuses,
            'totals' => [
                'count'       => $loans->count(),
                'principal'   => $loans->sum('principal'),
                'outstanding' => $loans->sum('outstanding'),
                'repaid'      => $loans->sum('amount_repaid'),
                'interest'    => $loans->sum('total_interest'),
            ],
        ]);
    }

    // ==================================================================
    //  Group Loans — export (key: group_loans)
    // ==================================================================
    protected function exportGroupLoans(Request $request, string $format)
    {
        $this->authorize('viewAny', Loan::class);

        $activeId = session('active_group_id');
        $groupId  = $request->integer('group_id') ?: $activeId;
        $statuses = $request->string('status')->toString() ?: 'active';

        $q = Loan::query()
            ->with(['member:id,full_name,member_no', 'group:id,name,currency'])
            ->orderBy('member_id')
            ->orderByDesc('disbursed_on');

        $this->scopeToActiveGroup($q);
        if ($groupId) $q->where('group_id', $groupId);

        if ($statuses === 'active') {
            $q->whereIn('status', ['disbursed', 'repaying']);
        } elseif ($statuses !== 'all') {
            $q->where('status', $statuses);
        }

        $loans = $q->get();
        $group = $groupId ? Group::find($groupId) : null;

        $headers = ['Member #', 'Member', 'Loan Ref', 'Type', 'Status',
                    'Principal', 'Rate %', 'Disbursed', 'Term (mo)',
                    'Total Interest', 'Repaid', 'Outstanding'];

        $rows = [];
        $byMember = $loans->groupBy('member_id');

        foreach ($byMember as $memberId => $memberLoans) {
            foreach ($memberLoans as $l) {
                $rows[] = [
                    $l->member?->member_no,
                    $l->member?->full_name,
                    $l->reference,
                    $l->isCompound() ? 'Compound' : 'Flat',
                    ucfirst(str_replace('_', ' ', (string) $l->status)),
                    number_format((float) $l->principal,        2, '.', ''),
                    number_format((float) $l->interest_rate_pct, 2, '.', ''),
                    optional($l->disbursed_on)->format('Y-m-d'),
                    (int) $l->term_months,
                    number_format((float) $l->total_interest,   2, '.', ''),
                    number_format((float) $l->amount_repaid,    2, '.', ''),
                    number_format((float) $l->outstanding,      2, '.', ''),
                ];
            }
            // Member subtotal row
            $rows[] = [
                '', 'SUBTOTAL — '.$memberLoans->first()?->member?->full_name,
                '', '', '',
                number_format((float) $memberLoans->sum('principal'),     2, '.', ''),
                '', '', '',
                number_format((float) $memberLoans->sum('total_interest'),2, '.', ''),
                number_format((float) $memberLoans->sum('amount_repaid'), 2, '.', ''),
                number_format((float) $memberLoans->sum('outstanding'),   2, '.', ''),
            ];
        }

        $groupName = $group?->name ?? 'All groups';
        $currency  = $group?->currency ?? '';

        $totals = [
            'Group'              => $groupName,
            'Currency'           => $currency,
            'Status filter'      => $statuses,
            'Total loans'        => (string) $loans->count(),
            'Total principal'    => number_format((float) $loans->sum('principal'), 0),
            'Total outstanding'  => number_format((float) $loans->sum('outstanding'), 0),
            'Total repaid'       => number_format((float) $loans->sum('amount_repaid'), 0),
            'Total interest'     => number_format((float) $loans->sum('total_interest'), 0),
        ];

        return $this->exporter->export(
            $format,
            "Group Loans Report — {$groupName}",
            $headers, $rows, $totals,
            'group-loans-'.preg_replace('/\s+/', '_', $groupName).'-'.now()->format('Ymd-His')
        );
    }

    // ==================================================================
    //  Helpers
    // ==================================================================
    protected function describeFilters(Request $request, array $keys): string
    {
        $parts = [];
        foreach ($keys as $k) {
            $v = $request->input($k);
            if ($v === null || $v === '') continue;
            $parts[] = "{$k}={$v}";
        }
        return $parts ? implode(', ', $parts) : '— none —';
    }

    protected function cashbookCategoryLabel(string $type, ?string $category): string
    {
        if (! $category) return '';
        $map = CashbookEntry::categoriesFor($type);
        return $map[$category] ?? $category;
    }
}
