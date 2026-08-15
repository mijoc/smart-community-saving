<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id', 'member_id', 'reference',
        'principal', 'interest_rate_pct', 'interest_model', 'term_months',
        'purpose', 'status',
        'requested_on', 'approved_on', 'approved_by', 'disbursed_on', 'due_on',
        'total_interest', 'total_repayable', 'amount_repaid', 'outstanding', 'late_fee_amount',
        'prior_outstanding', 'consolidated_loan_ids',
        'rejection_reason', 'notes',
    ];

    protected $casts = [
        'principal'              => 'decimal:2',
        'interest_rate_pct'      => 'decimal:3',
        'term_months'            => 'integer',
        'requested_on'           => 'date',
        'approved_on'            => 'date',
        'disbursed_on'           => 'date',
        'due_on'                 => 'date',
        'total_interest'         => 'decimal:2',
        'total_repayable'        => 'decimal:2',
        'amount_repaid'          => 'decimal:2',
        'outstanding'            => 'decimal:2',
        'late_fee_amount'        => 'decimal:2',
        'prior_outstanding'      => 'decimal:2',
        'consolidated_loan_ids'  => 'array',
    ];

    public function group(): BelongsTo       { return $this->belongsTo(Group::class); }
    public function member(): BelongsTo      { return $this->belongsTo(Member::class); }
    public function approver(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }
    public function repayments(): HasMany        { return $this->hasMany(LoanRepayment::class); }
    public function approvedRepayments(): HasMany { return $this->hasMany(LoanRepayment::class)->where('status', 'approved'); }
    public function pendingRepayments(): HasMany  { return $this->hasMany(LoanRepayment::class)->where('status', 'pending')->orderBy('paid_on'); }
    public function accruals(): HasMany      { return $this->hasMany(LoanInterestAccrual::class)->orderBy('period'); }

    public function isCompound(): bool { return $this->interest_model === 'compound'; }

    /** Monthly interest amount on current outstanding (used in views). */
    public function monthlyInterestDue(): float
    {
        return round((float) $this->outstanding * (float) $this->interest_rate_pct / 100, 2);
    }

    /** Unpaid accrued interest = total accrued - interest already repaid (approved only). */
    public function unpaidAccruedInterest(): float
    {
        $totalAccrued = (float) $this->accruals()->sum('interest_amount');
        $totalPaid    = (float) $this->approvedRepayments()->sum('interest_portion');
        return max(0, round($totalAccrued - $totalPaid, 2));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['disbursed', 'repaying']);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'requested'    => 'yellow',
            'approved'     => 'azure',
            'rejected'     => 'red',
            'disbursed'    => 'blue',
            'repaying'     => 'indigo',
            'paid'         => 'green',
            'consolidated' => 'teal',
            'defaulted'    => 'red',
            'written_off'  => 'dark',
            default        => 'secondary',
        };
    }
}
