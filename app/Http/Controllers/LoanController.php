<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Member;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use App\Services\LoanService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Loan::class, 'loan');
    }

    public function index(Request $request)
    {
        $q = Loan::query()->with(['group:id,name', 'member:id,full_name,member_no']);
        $this->scopeToActiveGroup($q);

        // Members default to their own loans, but can switch to all loans in
        // the active group via `?view=group`.
        $u = auth()->user();
        if ($u->hasRole('member') && $u->member_id && $request->string('view')->toString() !== 'group') {
            $q->where('member_id', $u->member_id);
        }

        if ($g = $request->integer('group_id'))          $q->where('group_id', $g);
        if ($m = $request->integer('member_id'))         $q->where('member_id', $m);
        if ($s = $request->string('status')->toString()) $q->where('status', $s);

        $loans = $q->orderByDesc('requested_on')->paginate(20)->withQueryString();

        return view('loans.index', [
            'loans'  => $loans,
            'groups' => $this->accessibleGroupOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $u = auth()->user();
        $groups = $this->accessibleGroupOptions();
        $activeId = session('active_group_id');

        // Anyone with a staff role (incl. group_admin / treasurer / secretary)
        // can request a loan on behalf of any member in the active group, even
        // if they also happen to be a member themselves. The "self only"
        // restriction only applies to *pure* members.
        $isStaff = $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);

        if (! $isStaff && $u->hasRole('member') && $u->member_id) {
            // Pure member — always requests for themselves.
            $members = Member::where('id', $u->member_id)->get(['id', 'full_name', 'member_no']);
        } else {
            $members = Member::query()
                ->when(! $u->isSuperAdmin() || $activeId, function ($q) use ($groups, $activeId) {
                    $ids = $activeId ? [$activeId] : $groups->pluck('id')->all();
                    $q->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $ids));
                })
                ->orderBy('full_name')->get(['id', 'full_name', 'member_no']);
        }

        return view('loans.create', [
            'groups'  => $groups,
            'members' => $members,
            'lockMember' => ! $isStaff && $u->hasRole('member'),
        ]);
    }

    public function store(Request $request, LoanService $svc)
    {
        $data = $request->validate([
            'group_id'          => ['required', 'exists:groups,id'],
            'member_id'         => ['required', 'exists:members,id'],
            'principal'         => ['required', 'numeric', 'min:1'],
            'interest_rate_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_model'    => ['nullable', 'in:compound'],
            'term_months'       => ['nullable', 'integer', 'min:1', 'max:120'],
            'requested_on'      => ['nullable', 'date', 'before_or_equal:today'],
            'purpose'           => ['nullable', 'string', 'max:1000'],
        ]);

        // Always compound — flat is no longer supported.
        $data['interest_model'] = 'compound';

        $u = auth()->user();

        // Same rule as the form: staff (super_admin / group_admin / treasurer
        // / secretary) may request a loan for any member of their active
        // group. Only *pure* members are restricted to themselves.
        $isStaff = $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);

        if (! $isStaff && $u->hasRole('member')) {
            if ($u->member_id !== (int) $data['member_id']) abort(403);
        }
        if (! $u->canAccessGroup((int) $data['group_id'])) abort(403);

        $loan = Loan::create(array_merge($data, [
            'reference'    => $svc->nextReference(),
            'status'       => 'requested',
            'requested_on' => $data['requested_on'] ?? now()->toDateString(),
        ]));

        $member = Member::find($loan->member_id);
        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.requested',
            description: "{$member?->full_name} requested a loan ({$loan->reference})",
            subject: $loan,
            icon: 'cash-banknote',
            color: 'purple',
            data: [
                'principal' => number_format((float) $loan->principal, 2),
                'term'      => $loan->isCompound() ? 'compound/rolling' : "{$loan->term_months} months",
                'rate'      => "{$loan->interest_rate_pct}%",
            ],
        );

        return redirect()->route('loans.show', $loan)
            ->with('status', "Loan request {$loan->reference} submitted.");
    }

    public function show(Loan $loan)
    {
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);

        $loan->load(['group', 'member', 'approver', 'repayments.recorder', 'repayments.approver', 'accruals']);

        $ledger = $this->buildLoanLedger($loan);

        // Pending repayments (shown separately for admin review)
        $pendingRepayments = $loan->repayments->where('status', 'pending')->values();

        // Accruals with their billing window for the payment form (interest_only picker).
        // FIFO allocation: distribute ALL interest paid (any payment type) to oldest months first,
        // so the list only shows months that genuinely still have unpaid interest.
        $disbursedDay = $loan->disbursed_on ? (int) $loan->disbursed_on->format('d') : 1;

        $totalInterestPaid = (float) $loan->repayments
            ->where('status', 'approved')
            ->sum('interest_portion');

        $poolLeft = $totalInterestPaid; // decremented as we consume oldest months

        $accrualOptions = $loan->accruals
            ->sortBy('period')
            ->map(function ($a) use ($disbursedDay, &$poolLeft) {
                $accrued = (float) $a->interest_amount;

                if ($poolLeft >= $accrued) {
                    $poolLeft -= $accrued;
                    return null; // month fully covered by prior payments
                }

                $paidThisMonth = $poolLeft;
                $remaining     = round($accrued - $paidThisMonth, 2);
                $poolLeft      = 0; // pool exhausted

                $period = \Carbon\Carbon::parse($a->period);
                $start  = $period->copy()->subMonthNoOverflow()->day($disbursedDay);
                $end    = $period->copy()->day($disbursedDay);

                return [
                    'period'   => $a->period,
                    'label'    => $start->format('d/m') . ' – ' . $end->format('d/m/Y'),
                    'amount'   => $remaining,
                    'original' => $accrued,
                    'partial'  => $paidThisMonth > 0,
                ];
            })
            ->filter()
            ->values();

        // Active compound loans for same member/group that could be consolidated
        // into this loan (only relevant while status = 'approved').
        $consolidationCandidates = collect();
        if ($loan->status === 'approved' && $loan->isCompound()) {
            $consolidationCandidates = Loan::where('member_id', $loan->member_id)
                ->where('group_id',  $loan->group_id)
                ->where('id', '!=', $loan->id)
                ->whereIn('status', ['disbursed', 'repaying'])
                ->get(['id', 'reference', 'principal', 'outstanding', 'disbursed_on']);
        }

        return view('loans.show', [
            'loan'                    => $loan,
            'ledger'                  => $ledger,
            'pendingRepayments'       => $pendingRepayments,
            'accrualOptions'          => $accrualOptions,
            'consolidationCandidates' => $consolidationCandidates,
        ]);
    }

    private function buildLoanLedger(Loan $loan): \Illuminate\Support\Collection
    {
        $entries = collect();
        $running = 0.0;

        // ── 1. Disbursement always first ─────────────────────────────────────
        if ($loan->disbursed_on) {
            $running = (float) $loan->principal;
            $entries->push([
                'date'        => $loan->disbursed_on,
                'type'        => 'disbursement',
                'description' => 'Loan Given',
                'capital'     => 0,
                'interest'    => 0,
                'payment'     => 0,
                'balance'     => $running,
                'meta'        => null,
            ]);

            // For flat loans: inject a "Flat interest charged" row immediately
            // after disbursement so the ledger running balance matches
            // total_repayable and the Interest Added column isn't always zero.
            if (! $loan->isCompound() && (float) $loan->total_interest > 0) {
                $flatInterest = (float) $loan->total_interest;
                $running      = round($running + $flatInterest, 2);
                $rate         = rtrim(rtrim(number_format((float) $loan->interest_rate_pct, 3), '0'), '.');
                $term         = $loan->term_months ? "{$loan->term_months} mo" : '—';
                $entries->push([
                    'date'        => $loan->disbursed_on,
                    'type'        => 'flat_interest',
                    'description' => 'Flat Interest Charged',
                    'capital'     => (float) $loan->principal,
                    'interest'    => $flatInterest,
                    'payment'     => 0,
                    'balance'     => $running,
                    'meta'        => "{$rate}% × {$term}",
                ]);
            }
        }

        // ── 2. Merge accruals + repayments, sorted purely by date ────────────
        // Accruals: sort by period (start-of-month).
        // Repayments: sort by paid_on.
        // Within the same date, accruals come before repayments (interest
        // is charged before payments are applied on the same day).
        $events = collect();

        $disbursedDay = $loan->disbursed_on ? (int) $loan->disbursed_on->format('d') : 1;

        foreach ($loan->accruals as $a) {
            $period = \Carbon\Carbon::parse($a->period);
            $events->push([
                'ts'           => $period->timestamp * 10 + 1,
                'kind'         => 'accrual',
                'data'         => $a,
                'period_start' => $period->copy()->subMonthNoOverflow()->day($disbursedDay),
                'period_end'   => $period->copy()->day($disbursedDay),
            ]);
        }
        foreach ($loan->repayments->where('status', 'approved') as $r) {
            $paidOn = \Carbon\Carbon::parse($r->paid_on);
            $events->push([
                'ts'   => $paidOn->timestamp * 10 + 2,
                'kind' => 'repayment',
                'data' => $r,
            ]);
        }

        foreach ($events->sortBy('ts') as $ev) {
            if ($ev['kind'] === 'accrual') {
                $a       = $ev['data'];
                $running = (float) $a->balance_after;
                $entries->push([
                    'date'         => \Carbon\Carbon::parse($a->period),
                    'period_start' => $ev['period_start'],
                    'period_end'   => $ev['period_end'],
                    'type'         => 'accrual',
                    'description'  => 'Monthly Interest',
                    'capital'      => (float) $a->balance_before,
                    'interest'     => (float) $a->interest_amount,
                    'payment'      => 0,
                    'balance'      => $running,
                    'meta'         => rtrim(rtrim(number_format((float) $a->rate_pct, 3), '0'), '.') . '%',
                ]);
            } else {
                $r      = $ev['data'];
                $before = $running;
                $running -= (float) $r->amount;
                $interest_p  = (float) $r->interest_portion;
                $principal_p = (float) $r->principal_portion;
                if ($interest_p > 0 && $principal_p == 0) {
                    $desc = 'Interest Payment';
                } elseif ($principal_p > 0 && $interest_p == 0) {
                    $desc = 'Principal Payment';
                } else {
                    $desc = 'Payment';
                }
                $entries->push([
                    'date'        => \Carbon\Carbon::parse($r->paid_on),
                    'type'        => 'repayment',
                    'description' => $desc,
                    'capital'     => $before,
                    'interest'    => 0,
                    'payment'     => (float) $r->amount,
                    'balance'     => $running,
                    'meta'        => str_replace('_', ' ', $r->method) . ($r->reference ? ' · ' . $r->reference : ''),
                ]);
            }
        }

        return $entries->values();
    }

    /**
     * Delete a loan. The policy decides who can do this and on which states
     * (super_admin: any state; group_admin: only requested/rejected). The
     * loan_repayments rows are cascade-deleted by the FK. The whole
     * operation is wrapped in a transaction and recorded in the activity
     * feed so the deletion is auditable.
     */
    public function destroy(Loan $loan)
    {
        $snapshot = [
            'reference'      => $loan->reference,
            'member'         => $loan->member?->full_name,
            'group_id'       => $loan->group_id,
            'status'         => $loan->status,
            'principal'      => number_format((float) $loan->principal, 2),
            'amount_repaid'  => number_format((float) $loan->amount_repaid, 2),
        ];

        DB::transaction(function () use ($loan) {
            // Explicitly delete repayments — the migration declares an FK
            // cascade, but we don't want to rely on the DB driver having
            // foreign-key enforcement enabled (e.g. SQLite without the
            // `foreign_keys` PRAGMA).
            $loan->repayments()->delete();
            $loan->delete();
        });

        ActivityLogger::log(
            groupId: $snapshot['group_id'],
            type: 'loan.deleted',
            description: "deleted loan {$snapshot['reference']} ({$snapshot['member']}) — was {$snapshot['status']}",
            subject: null,
            icon: 'trash',
            color: 'red',
            data: $snapshot,
        );

        return redirect()->route('loans.index')
            ->with('status', "Loan {$snapshot['reference']} deleted.");
    }

    // ─── Decision actions (group_admin / treasurer) ───────────────────────

    public function approve(Loan $loan, LoanService $svc)
    {
        $this->authorize('decide', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);
        if ($loan->status !== 'requested') return back()->with('status', 'Loan is not pending review.');

        $svc->approve($loan, auth()->id());
        ActivityLogger::log(
            groupId: $loan->group_id, type: 'loan.approved',
            description: "approved loan {$loan->reference} for {$loan->member?->full_name}",
            subject: $loan, icon: 'check', color: 'green',
            data: ['principal' => number_format((float) $loan->principal, 2)],
        );
        return back()->with('status', "Loan {$loan->reference} approved.");
    }

    public function reject(Request $request, Loan $loan, LoanService $svc)
    {
        $this->authorize('decide', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);
        if ($loan->status !== 'requested') return back()->with('status', 'Loan is not pending review.');

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $svc->reject($loan, $data['rejection_reason'] ?? null);
        ActivityLogger::log(
            groupId: $loan->group_id, type: 'loan.rejected',
            description: "rejected loan {$loan->reference} for {$loan->member?->full_name}",
            subject: $loan, icon: 'x', color: 'red',
            data: array_filter(['reason' => $data['rejection_reason'] ?? null]),
        );
        return back()->with('status', "Loan {$loan->reference} rejected.");
    }

    public function disburse(Request $request, Loan $loan, LoanService $svc)
    {
        $this->authorize('decide', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);

        $data = $request->validate([
            'disbursed_on'        => ['required', 'date'],
            'consolidate_loan_ids' => ['nullable', 'array'],
            'consolidate_loan_ids.*' => ['integer'],
        ]);

        $consolidateIds = $data['consolidate_loan_ids'] ?? [];
        $svc->disburse($loan, $data['disbursed_on'], $consolidateIds);

        $loan->refresh();
        $desc = "disbursed loan {$loan->reference} to {$loan->member?->full_name}";
        if (! empty($consolidateIds)) {
            $refs = implode(', ', array_map(fn ($id) => 'L-' . str_pad($id, 5, '0', STR_PAD_LEFT), $consolidateIds));
            $desc .= " (rolled in: {$refs})";
        }
        ActivityLogger::log(
            groupId: $loan->group_id, type: 'loan.disbursed',
            description: $desc,
            subject: $loan, icon: 'send', color: 'teal',
            data: [
                'principal'       => number_format((float) $loan->principal, 2),
                'prior_rollover'  => number_format((float) ($loan->prior_outstanding ?? 0), 2),
                'date'            => $data['disbursed_on'],
            ],
        );
        return back()->with('status', "Loan {$loan->reference} disbursed.");
    }

    public function recordRepayment(Request $request, Loan $loan, LoanService $svc)
    {
        $this->authorize('record', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);

        $data = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'paid_on'        => ['required', 'date'],
            'method'         => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'reference'      => ['nullable', 'string', 'max:60'],
            'notes'          => ['nullable', 'string'],
            'payment_type'   => ['required', 'in:full,interest_only,principal_only'],
            'accrual_period' => ['nullable', 'date'],
            'proof_file'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);

        if ($request->hasFile('proof_file')) {
            $data['proof_file'] = $request->file('proof_file')
                ->store('repayment-proofs', 'public');
        } else {
            unset($data['proof_file']);
        }

        $u = auth()->user();
        $isMember = $u->hasRole('member') && ! $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);
        $pending  = $isMember;

        $rep = $svc->recordRepayment($loan, $data, $u->id, $pending);

        if ($pending) {
            ActivityLogger::log(
                groupId: $loan->group_id,
                type: 'loan.repayment_submitted',
                description: "{$loan->member?->full_name} submitted a repayment of {$data['amount']} on loan {$loan->reference} (awaiting approval)",
                subject: $loan,
                icon: 'clock',
                color: 'yellow',
                data: ['amount' => number_format((float) $data['amount'], 2), 'type' => $data['payment_type']],
            );
            return back()->with('status', 'Repayment submitted and awaiting admin approval.');
        }

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: $loan->status === 'paid' ? 'loan.fullyrepaid' : 'loan.repaid',
            description: $loan->status === 'paid'
                ? "fully repaid loan {$loan->reference} ({$loan->member?->full_name})"
                : "recorded a repayment on loan {$loan->reference} ({$loan->member?->full_name})",
            subject: $loan,
            icon: $loan->status === 'paid' ? 'circle-check' : 'wallet',
            color: $loan->status === 'paid' ? 'green' : 'cyan',
            data: [
                'amount'      => number_format((float) $data['amount'], 2),
                'method'      => $data['method'],
                'type'        => $data['payment_type'],
                'outstanding' => number_format((float) $loan->outstanding, 2),
            ],
        );
        return back()->with('status', 'Repayment recorded.');
    }

    public function deleteRepayment(Request $request, Loan $loan, \App\Models\LoanRepayment $repayment)
    {
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);
        if (! auth()->user()->hasAnyRole(['super_admin', 'group_admin'])) abort(403);
        if ($repayment->loan_id !== $loan->id) abort(404);

        \Illuminate\Support\Facades\DB::transaction(function () use ($loan, $repayment) {
            // Reverse the loan balance only for approved repayments that already altered it
            if ($repayment->status === 'approved') {
                $newAmountRepaid     = max(0, round((float) $loan->amount_repaid - (float) $repayment->amount, 2));
                $loan->amount_repaid = $newAmountRepaid;

                if ($loan->isCompound()) {
                    // Compound: rebuild from the accruals table so the result is correct
                    // even if the stored outstanding was already corrupted before this delete.
                    // reorder() overrides the relationship's default ASC ordering so we
                    // always get the most-recent accrual, not the oldest.
                    $lastAccrual = $loan->accruals()->reorder()->orderByDesc('period')->first();

                    if ($lastAccrual) {
                        // Sum approved repayments after the last accrual period,
                        // excluding the repayment currently being deleted (not yet removed from DB).
                        $repaymentsAfter = $loan->repayments()
                            ->where('status', 'approved')
                            ->where('id', '!=', $repayment->id)
                            ->whereDate('paid_on', '>', $lastAccrual->period)
                            ->sum('amount');

                        $loan->outstanding = max(0, round(
                            (float) $lastAccrual->balance_after - (float) $repaymentsAfter, 2
                        ));
                    } else {
                        // No accruals yet — outstanding is just the original principal.
                        $loan->outstanding = (float) $loan->principal;
                    }
                } else {
                    // Flat: outstanding = total_repayable − amount_repaid (recalculate cleanly).
                    $loan->outstanding = max(0, round((float) $loan->total_repayable - $newAmountRepaid, 2));
                }

                // Revert status: if nothing repaid → disbursed; still some owed → repaying
                if ($loan->amount_repaid <= 0) {
                    $loan->status = 'disbursed';
                } elseif ($loan->outstanding > 0) {
                    $loan->status = 'repaying';
                }
                $loan->save();
            }

            // Remove proof file from storage
            if ($repayment->proof_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($repayment->proof_file);
            }

            \App\Services\ActivityLogger::log(
                type: 'loan.repayment_deleted',
                description: auth()->user()->name . " deleted a " . $repayment->status . " repayment of "
                    . number_format((float) $repayment->amount, 2) . " on loan {$loan->reference} ({$loan->member?->full_name})",
                groupId: $loan->group_id,
                data: ['amount' => $repayment->amount, 'status_was' => $repayment->status],
            );

            $repayment->delete();
        });

        return back()->with('success', 'Repayment deleted and loan balance updated.');
    }

    public function approveRepayment(Request $request, Loan $loan, \App\Models\LoanRepayment $repayment, LoanService $svc)
    {
        $this->authorize('approveRepayment', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);
        if ($repayment->loan_id !== $loan->id) abort(404);

        $svc->approveRepayment($repayment, auth()->id());

        $loan->refresh();
        ActivityLogger::log(
            groupId: $loan->group_id,
            type: $loan->status === 'paid' ? 'loan.fullyrepaid' : 'loan.repaid',
            description: $loan->status === 'paid'
                ? "approved & fully repaid loan {$loan->reference} ({$loan->member?->full_name})"
                : "approved repayment of " . number_format((float) $repayment->amount, 2) . " on loan {$loan->reference} ({$loan->member?->full_name})",
            subject: $loan,
            icon: $loan->status === 'paid' ? 'circle-check' : 'check',
            color: $loan->status === 'paid' ? 'green' : 'teal',
            data: ['amount' => number_format((float) $repayment->amount, 2), 'outstanding' => number_format((float) $loan->outstanding, 2)],
        );
        return back()->with('status', 'Repayment approved and applied to loan.');
    }

    public function rejectRepayment(Request $request, Loan $loan, \App\Models\LoanRepayment $repayment, LoanService $svc)
    {
        $this->authorize('approveRepayment', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);
        if ($repayment->loan_id !== $loan->id) abort(404);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $svc->rejectRepayment($repayment, $data['rejection_reason'] ?? null, auth()->id());

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.repayment_rejected',
            description: "rejected a repayment of " . number_format((float) $repayment->amount, 2) . " on loan {$loan->reference} ({$loan->member?->full_name})",
            subject: $loan,
            icon: 'x',
            color: 'red',
            data: array_filter(['amount' => number_format((float) $repayment->amount, 2), 'reason' => $data['rejection_reason'] ?? null]),
        );
        return back()->with('status', 'Repayment rejected.');
    }

    /**
     * Manually accrue one month of compound interest on a single loan.
     * Visible to group_admin and treasurer only. Idempotent per month.
     */
    public function accrueInterest(Request $request, Loan $loan, LoanService $svc)
    {
        $u = auth()->user();
        if (! $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) abort(403);
        if (! $u->canAccessGroup($loan->group_id)) abort(403);

        if (! $loan->isCompound()) {
            return back()->with('error', 'Manual accrual is only available for compound-model loans.');
        }
        if (! in_array($loan->status, ['disbursed', 'repaying'])) {
            return back()->with('error', 'Loan must be active (disbursed or repaying) to accrue interest.');
        }

        $periodInput = $request->input('period');
        $period = $periodInput
            ? \Carbon\Carbon::createFromFormat('Y-m', $periodInput)->startOfMonth()
            : now()->startOfMonth();

        $accrual = $svc->accrueMonthlyInterest($loan, $period);

        if ($accrual === null) {
            return back()->with('status', "Interest already accrued for {$period->format('M Y')} on loan {$loan->reference}.");
        }

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.interest_accrued',
            description: "accrued monthly interest on loan {$loan->reference} ({$loan->member?->full_name})",
            subject: $loan,
            icon: 'trending-up',
            color: 'orange',
            data: [
                'period'          => $period->format('M Y'),
                'interest_amount' => number_format($accrual->interest_amount, 2),
                'balance_after'   => number_format($accrual->balance_after, 2),
            ],
        );

        return back()->with('status', sprintf(
            'Interest accrued for %s: +%s added → outstanding now %s.',
            $period->format('M Y'),
            number_format($accrual->interest_amount, 2),
            number_format($accrual->balance_after, 2)
        ));
    }

    /**
     * Manually trigger the loan interest engine for the active group.
     * Handles two models:
     *   - flat (due_on based): late fees via ArrearsService::runLoanLateFees
     *   - compound: monthly accrual via LoanService::backfillMissingAccruals
     * Admin / treasurer only.
     */
    public function applyInterestPenalties(Request $request, \App\Services\ArrearsService $arrearsSvc, \App\Services\LoanService $loanSvc)
    {
        $u = auth()->user();
        if (! $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) abort(403);

        $groupId = $request->integer('group_id') ?: session('active_group_id') ?: null;
        if ($groupId && ! $u->canAccessGroup($groupId)) abort(403);

        // Flat loans: late fees on overdue (past due_on)
        $l = $arrearsSvc->runLoanLateFees($groupId);

        // Compound loans: accrue all missing monthly periods up to today
        $compoundQuery = \App\Models\Loan::query()
            ->where('interest_model', 'compound')
            ->whereIn('status', ['disbursed', 'repaying'])
            ->where('outstanding', '>', 0);
        if ($groupId) $compoundQuery->where('group_id', $groupId);

        $compoundEvaluated = 0;
        $compoundCharged   = 0;
        $compoundQuery->each(function ($loan) use ($loanSvc, &$compoundEvaluated, &$compoundCharged) {
            $compoundEvaluated++;
            $n = $loanSvc->backfillMissingAccruals($loan);
            $compoundCharged += $n;
        });

        $totalEvaluated = $l['evaluated'] + $compoundEvaluated;
        $totalCharged   = $l['fees_applied'] + $compoundCharged;

        return back()->with('status',
            "Loan interest applied — evaluated {$totalEvaluated} loan(s), recorded {$totalCharged} new interest period(s)."
        );
    }

    /**
     * Rebuild the loan's outstanding balance from the interest accruals table.
     * Useful when a delete/edit operation leaves the stored balance out of sync.
     * Admin / treasurer only. Works for compound loans only.
     */
    public function recalculateBalance(Request $request, Loan $loan)
    {
        $u = auth()->user();
        if (! $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) abort(403);
        if (! $u->canAccessGroup($loan->group_id)) abort(403);

        if (! $loan->isCompound()) {
            return back()->with('error', 'Balance recalculation is only available for compound-interest loans.');
        }

        // Last accrual's balance_after is the compounded balance up to that point.
        // Use reorder() to override the relationship's default ASC ordering.
        $lastAccrual = $loan->accruals()->reorder()->orderByDesc('period')->first();

        if (! $lastAccrual) {
            return back()->with('error', 'No accruals found for this loan — nothing to recalculate from.');
        }

        // Subtract any approved repayments recorded after the last accrual period.
        $repaymentsAfter = $loan->repayments()
            ->where('status', 'approved')
            ->whereDate('paid_on', '>', $lastAccrual->period)
            ->sum('amount');

        $correct = max(0, round((float) $lastAccrual->balance_after - (float) $repaymentsAfter, 2));
        $before  = (float) $loan->outstanding;

        $loan->outstanding = $correct;
        $loan->save();

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.balance_recalculated',
            description: auth()->user()->name . " recalculated balance on loan {$loan->reference}: {$before} → {$correct}",
            subject: $loan,
            icon: 'refresh',
            color: 'blue',
            data: ['before' => $before, 'after' => $correct],
        );

        return back()->with('status', sprintf(
            'Balance recalculated: %s → %s (from last accrual %s).',
            number_format($before, 2),
            number_format($correct, 2),
            \Carbon\Carbon::parse($lastAccrual->period)->format('M Y')
        ));
    }

    public function updateDisbursedOn(Request $request, Loan $loan, LoanService $svc)
    {
        $u = auth()->user();
        if (! $u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) abort(403);
        if (! $u->canAccessGroup($loan->group_id)) abort(403);
        if (! in_array($loan->status, ['disbursed', 'repaying'])) {
            return back()->with('error', 'Disbursement date can only be changed on active loans.');
        }

        $data = $request->validate([
            'disbursed_on' => ['required', 'date'],
        ]);

        $old = $loan->disbursed_on?->toDateString() ?? '—';
        $loan->update(['disbursed_on' => $data['disbursed_on']]);

        // Backfill any missed accruals if disbursement date is now in the past.
        if ($loan->isCompound()) {
            $loan->refresh();
            $svc->backfillMissingAccruals($loan);
        }

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.disbursed',
            description: "updated disbursement date for loan {$loan->reference} ({$loan->member?->full_name}) from {$old} to {$data['disbursed_on']}",
            subject: $loan,
            icon: 'calendar-event',
            color: 'blue',
            data: ['old_date' => $old, 'new_date' => $data['disbursed_on']],
        );

        return back()->with('status', "Disbursement date updated to {$data['disbursed_on']}.");
    }

    /**
     * Manually flag an active loan as defaulted.
     * Only group_admin / super_admin (enforced by LoanPolicy::markDefaulted).
     */
    public function markDefaulted(Request $request, Loan $loan)
    {
        $this->authorize('markDefaulted', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = trim(($loan->notes ? $loan->notes . ' | ' : '')
            . 'Manually flagged as defaulted on ' . now()->toDateString()
            . (($data['notes'] ?? null) ? ': ' . $data['notes'] : ''));

        $loan->update(['status' => 'defaulted', 'notes' => $note]);

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.defaulted',
            description: auth()->user()->name . " flagged loan {$loan->reference} ({$loan->member?->full_name}) as defaulted",
            subject: $loan,
            icon: 'alert-triangle',
            color: 'red',
            data: [
                'reference'   => $loan->reference,
                'member'      => $loan->member?->full_name,
                'outstanding' => number_format((float) $loan->outstanding, 2),
                'reason'      => $data['notes'] ?? null,
            ],
        );

        return back()->with('status', "Loan {$loan->reference} flagged as defaulted.");
    }

    /**
     * Write off a defaulted (or still-active) loan.
     * Only group_admin / super_admin (enforced by LoanPolicy::writeOff).
     * The outstanding balance is zeroed; the loan is closed permanently.
     */
    public function writeOff(Request $request, Loan $loan)
    {
        $this->authorize('writeOff', $loan);
        if (! auth()->user()->canAccessGroup($loan->group_id)) abort(403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $note = trim(($loan->notes ? $loan->notes . ' | ' : '')
            . 'Written off on ' . now()->toDateString()
            . (($data['notes'] ?? null) ? ': ' . $data['notes'] : ''));

        $amountWrittenOff = (float) $loan->outstanding;

        $loan->update([
            'status'      => 'written_off',
            'outstanding' => 0,
            'notes'       => $note,
        ]);

        ActivityLogger::log(
            groupId: $loan->group_id,
            type: 'loan.written_off',
            description: auth()->user()->name . " wrote off loan {$loan->reference} ({$loan->member?->full_name}) — {$amountWrittenOff} forgiven",
            subject: $loan,
            icon: 'circle-x',
            color: 'dark',
            data: [
                'reference'       => $loan->reference,
                'member'          => $loan->member?->full_name,
                'amount_forgiven' => number_format($amountWrittenOff, 2),
                'reason'          => $data['notes'] ?? null,
            ],
        );

        return back()->with('status', "Loan {$loan->reference} written off. Outstanding balance cleared.");
    }
}
