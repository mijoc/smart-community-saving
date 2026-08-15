<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id', 'member_id', 'status',
        'fine_amount', 'paid_amount', 'paid_on',
        'notes', 'recorded_by',
    ];

    protected $casts = [
        'paid_on'     => 'date',
        'fine_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public const STATUSES = [
        'present' => 'Present',
        'late'    => 'Late',
        'absent'  => 'Absent',
        'excused' => 'Excused',
    ];

    public const STATUS_COLORS = [
        'present' => 'green',
        'late'    => 'orange',
        'absent'  => 'red',
        'excused' => 'azure',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, (float) $this->fine_amount - (float) $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->fine_amount > 0 && $this->paid_amount >= $this->fine_amount;
    }
}
