<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
// ContributionPaymentRequest is referenced via the HasMany relationship below

class Contribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'member_id', 'contribution_schedule_id', 'type',
        'expected_amount', 'paid_amount', 'late_fee_amount',
        'period_start', 'period_end', 'due_on', 'paid_on',
        'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'due_on'          => 'date',
        'paid_on'         => 'date',
        'expected_amount' => 'decimal:2',
        'paid_amount'     => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function arrear(): HasOne
    {
        return $this->hasOne(Arrear::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(ContributionPaymentRequest::class);
    }

    public function balance(): float
    {
        return (float) bcsub((string) ($this->expected_amount + $this->late_fee_amount), (string) $this->paid_amount, 2);
    }

    public function refreshStatus(): void
    {
        $expected = (float) $this->expected_amount + (float) $this->late_fee_amount;
        $paid     = (float) $this->paid_amount;

        if ($paid <= 0) {
            $this->status = $this->due_on?->isPast() ? 'overdue' : 'pending';
        } elseif ($paid < $expected) {
            $this->status = 'partial';
        } else {
            $this->status  = 'paid';
            $this->paid_on = $this->paid_on ?? now()->toDateString();
        }
    }
}
