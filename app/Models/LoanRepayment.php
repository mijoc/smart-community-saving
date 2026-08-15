<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'amount', 'principal_portion', 'interest_portion',
        'paid_on', 'method', 'reference', 'recorded_by', 'notes',
        'proof_file',
        'status', 'payment_type', 'accrual_period',
        'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'principal_portion'  => 'decimal:2',
        'interest_portion'   => 'decimal:2',
        'paid_on'            => 'date',
        'accrual_period'     => 'date',
        'approved_at'        => 'datetime',
    ];

    public function loan(): BelongsTo     { return $this->belongsTo(Loan::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'pending'  => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default    => 'secondary',
        };
    }

    public function paymentTypeLabel(): string
    {
        return match ($this->payment_type) {
            'interest_only'  => 'Interest only',
            'principal_only' => 'Principal only',
            'partial'        => 'Partial',
            default          => 'Full payment',
        };
    }
}
