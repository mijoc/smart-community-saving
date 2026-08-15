<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\ContributionPaymentRequest;
use App\Models\Member;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ActivityLogger;
use App\Services\PaymentRecorderService;
use Illuminate\Http\Request;

class ContributionPaymentRequestController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $q = ContributionPaymentRequest::query()
            ->with(['contribution', 'member:id,full_name,member_no', 'reviewer:id,name']);

        $this->scopeToActiveGroup($q);

        if ($u->hasRole('member') && $u->member_id) {
            $q->where('member_id', $u->member_id);
        }

        if ($s = $request->string('status')->toString()) {
            $q->where('status', $s);
        } else {
            if (! $u->hasRole('member')) {
                $q->where('status', 'pending_review');
            }
        }

        $requests = $q->orderByDesc('created_at')->paginate(25)->withQueryString();

        $pendingCount = 0;
        if (! $u->hasRole('member') && ($activeId = session('active_group_id'))) {
            $pendingCount = ContributionPaymentRequest::pendingCountForGroup($activeId);
        }

        return view('payment-requests.index', compact('requests', 'pendingCount'));
    }

    public function store(Request $request)
    {
        $u = auth()->user();

        $data = $request->validate([
            'contribution_id' => ['required', 'exists:contributions,id'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['required', 'in:cash,mobile_money,bank,cheque,other'],
            'channel_ref'     => ['nullable', 'string', 'max:120'],
            'paid_on'         => ['required', 'date', 'before_or_equal:today'],
            'notes'           => ['nullable', 'string', 'max:500'],
            'proof_file'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        $contribution = Contribution::findOrFail($data['contribution_id']);

        if (! $u->canAccessGroup($contribution->group_id)) {
            abort(403);
        }

        if ($u->hasRole('member') && $u->member_id !== $contribution->member_id) {
            abort(403, 'You can only request payment for your own contributions.');
        }

        if (! in_array($contribution->status, ['pending', 'partial', 'overdue'])) {
            return back()->with('error', 'This contribution is already '.$contribution->status.'.');
        }

        if ($data['amount'] > $contribution->balance()) {
            return back()->withInput()->with('error', 'Amount exceeds the outstanding balance of '.number_format($contribution->balance(), 2).'.');
        }

        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer'])) {
            $payment = app(PaymentRecorderService::class)->record([
                'group_id'        => $contribution->group_id,
                'member_id'       => $contribution->member_id,
                'contribution_id' => $contribution->id,
                'amount'          => $data['amount'],
                'method'          => $data['method'],
                'channel_ref'     => $data['channel_ref'] ?? null,
                'paid_on'         => $data['paid_on'],
                'notes'           => $data['notes'] ?? null,
            ], auth()->id());

            ActivityLogger::log(
                groupId:     $contribution->group_id,
                type:        'payment.created',
                description: "recorded a payment of ".number_format($data['amount'], 2)." for {$contribution->member?->full_name}",
                subject:     $payment,
                icon:        'cash',
                color:       'green',
            );

            return redirect()
                ->route('contributions.show', $contribution)
                ->with('status', 'Payment recorded successfully.');
        }

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $proofPath = $request->file('proof_file')
                ->store('payment-proofs', 'public');
        }

        $req = ContributionPaymentRequest::create([
            'contribution_id' => $contribution->id,
            'member_id'       => $contribution->member_id,
            'group_id'        => $contribution->group_id,
            'amount'          => $data['amount'],
            'method'          => $data['method'],
            'channel_ref'     => $data['channel_ref'] ?? null,
            'paid_on'         => $data['paid_on'],
            'notes'           => $data['notes'] ?? null,
            'proof_path'      => $proofPath,
            'status'          => 'pending_review',
        ]);

        ActivityLogger::log(
            groupId:     $contribution->group_id,
            type:        'payment_request.submitted',
            description: "{$contribution->member?->full_name} submitted a payment request of ".number_format($data['amount'], 2),
            subject:     $req,
            icon:        'clock',
            color:       'yellow',
        );

        // Notify group admins & treasurers that a new request needs review
        UserNotification::notifyGroupRoles(
            groupId: $contribution->group_id,
            roles:   ['group_admin', 'treasurer', 'super_admin'],
            type:    'payment_request.submitted',
            title:   "{$contribution->member?->full_name} submitted a payment request",
            body:    number_format($data['amount'], 2).' via '.str_replace('_',' ',$data['method']).' — needs your approval.',
            link:    route('payment-requests.index', [], false),
            icon:    'clock',
            color:   'yellow',
        );

        return redirect()
            ->route('contributions.show', $contribution)
            ->with('status', 'Payment request submitted — awaiting approval.');
    }

    public function approve(ContributionPaymentRequest $paymentRequest, PaymentRecorderService $svc)
    {
        $this->authorize('review', ContributionPaymentRequest::class);

        if (! auth()->user()->canAccessGroup($paymentRequest->group_id)) abort(403);

        if (! $paymentRequest->isPending()) {
            return back()->with('error', 'This request has already been reviewed.');
        }

        $contribution = $paymentRequest->contribution;

        if ($paymentRequest->amount > $contribution->balance()) {
            return back()->with('error', 'Amount exceeds current outstanding balance of '.number_format($contribution->balance(), 2).'. Please reject and ask the member to resubmit.');
        }

        $payment = $svc->record([
            'group_id'        => $paymentRequest->group_id,
            'member_id'       => $paymentRequest->member_id,
            'contribution_id' => $paymentRequest->contribution_id,
            'amount'          => $paymentRequest->amount,
            'method'          => $paymentRequest->method,
            'channel_ref'     => $paymentRequest->channel_ref,
            'paid_on'         => $paymentRequest->paid_on->toDateString(),
            'notes'           => $paymentRequest->notes,
        ], auth()->id());

        $paymentRequest->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLogger::log(
            groupId:     $paymentRequest->group_id,
            type:        'payment_request.approved',
            description: "approved payment request of ".number_format($paymentRequest->amount, 2)." for {$paymentRequest->member?->full_name}",
            subject:     $payment,
            icon:        'circle-check',
            color:       'green',
        );

        // Notify the member personally
        $memberUser = User::where('member_id', $paymentRequest->member_id)->first();
        if ($memberUser) {
            UserNotification::notify(
                userId: $memberUser->id,
                type:   'payment_request.approved',
                title:  '✅ Payment request approved',
                body:   'Your payment of '.number_format($paymentRequest->amount, 2).' has been approved and recorded to your passbook.',
                link:   route('contributions.show', $paymentRequest->contribution_id, false),
                icon:   'circle-check',
                color:  'green',
            );
        }

        return back()->with('status', 'Payment request approved and payment recorded.');
    }

    public function reject(Request $request, ContributionPaymentRequest $paymentRequest)
    {
        $this->authorize('review', ContributionPaymentRequest::class);

        if (! auth()->user()->canAccessGroup($paymentRequest->group_id)) abort(403);

        if (! $paymentRequest->isPending()) {
            return back()->with('error', 'This request has already been reviewed.');
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $paymentRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        ActivityLogger::log(
            groupId:     $paymentRequest->group_id,
            type:        'payment_request.rejected',
            description: "rejected payment request of ".number_format($paymentRequest->amount, 2)." from {$paymentRequest->member?->full_name}",
            subject:     $paymentRequest,
            icon:        'circle-x',
            color:       'red',
        );

        // Notify the member personally
        $memberUser = User::where('member_id', $paymentRequest->member_id)->first();
        if ($memberUser) {
            UserNotification::notify(
                userId: $memberUser->id,
                type:   'payment_request.rejected',
                title:  '❌ Payment request rejected',
                body:   'Your payment of '.number_format($paymentRequest->amount, 2).' was rejected. Reason: '.$data['rejection_reason'],
                link:   route('contributions.show', $paymentRequest->contribution_id, false),
                icon:   'circle-x',
                color:  'red',
            );
        }

        return back()->with('status', 'Payment request rejected.');
    }
}
