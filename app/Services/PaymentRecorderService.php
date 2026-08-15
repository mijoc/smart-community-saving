<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRecorderService
{
    public function __construct(protected ArrearsService $arrears) {}

    /**
     * Record a payment, allocate it across the contribution(s), and write passbook entries.
     */
    public function record(array $data, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($data, $userId) {
            $payment = Payment::create([
                'reference'       => $data['reference']  ?? 'PMT-'.strtoupper(Str::random(10)),
                'group_id'        => $data['group_id'],
                'member_id'       => $data['member_id'],
                'contribution_id' => $data['contribution_id'] ?? null,
                'amount'          => $data['amount'],
                'method'          => $data['method'] ?? 'cash',
                'channel_ref'     => $data['channel_ref'] ?? null,
                'paid_on'         => $data['paid_on'] ?? now()->toDateString(),
                'notes'           => $data['notes']   ?? null,
                'received_by'     => $userId,
            ]);

            if ($payment->contribution_id) {
                $this->applyToContribution($payment);
            } else {
                // No specific contribution → write a generic credit to the passbook
                $this->writePassbook($payment, category: 'savings', credit: (float) $payment->amount, debit: 0, description: 'Payment received');
            }

            return $payment;
        });
    }

    protected function applyToContribution(Payment $payment): void
    {
        /** @var Contribution $c */
        $c = Contribution::lockForUpdate()->find($payment->contribution_id);
        if (! $c) return;

        $c->paid_amount = (float) $c->paid_amount + (float) $payment->amount;
        $c->refreshStatus();
        $c->save();

        $this->writePassbook(
            $payment,
            category: $c->type === 'late_fee' ? 'late_fee' : $c->type,
            credit: (float) $payment->amount,
            debit: 0,
            description: ucfirst(str_replace('_', ' ', $c->type)).' contribution — period '.$c->period_start->format('Y-m-d')
        );

        $this->arrears->recomputeFor($c);
    }

    protected function writePassbook(Payment $p, string $category, float $credit, float $debit, string $description): void
    {
        $lastBalance = (float) (PassbookEntry::where('group_id', $p->group_id)
            ->where('member_id', $p->member_id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('balance') ?? 0);

        PassbookEntry::create([
            'group_id'    => $p->group_id,
            'member_id'   => $p->member_id,
            'entry_date'  => $p->paid_on,
            'description' => $description,
            'category'    => $category,
            'debit'       => $debit,
            'credit'      => $credit,
            'balance'     => $lastBalance + $credit - $debit,
            'source_type' => Payment::class,
            'source_id'   => $p->id,
            'created_by'  => $p->received_by,
        ]);
    }
}
