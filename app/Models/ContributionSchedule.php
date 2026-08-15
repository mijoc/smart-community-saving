<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContributionSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'name', 'type', 'frequency', 'amount',
        'start_date', 'end_date', 'next_due_on', 'last_generated_on',
        'grace_days', 'late_fee_pct', 'late_fee_flat', 'is_active',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'next_due_on'        => 'date',
        'last_generated_on'  => 'date',
        'is_active'          => 'boolean',
        'amount'             => 'decimal:2',
        'late_fee_pct'       => 'decimal:2',
        'late_fee_flat'      => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function advance(Carbon $from): Carbon
    {
        return match ($this->frequency) {
            'weekly'      => $from->copy()->addWeek(),
            'fortnightly' => $from->copy()->addWeeks(2),
            'monthly'     => $from->copy()->addMonth(),
            'quarterly'   => $from->copy()->addMonths(3),
            default       => $from->copy()->addWeek(),
        };
    }

    public function periodEndFor(Carbon $start): Carbon
    {
        return $this->advance($start)->subDay();
    }
}
