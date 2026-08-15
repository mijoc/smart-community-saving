<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id', 'meeting_date', 'title', 'agenda',
        'late_fine', 'absent_fine', 'status', 'created_by',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'late_fine'    => 'decimal:2',
        'absent_fine'  => 'decimal:2',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Total fines accrued for this meeting (sum of fine_amount across rows).
     */
    public function getFinesTotalAttribute(): float
    {
        return (float) $this->attendances->sum('fine_amount');
    }

    /**
     * Total fines already paid in for this meeting.
     */
    public function getFinesPaidAttribute(): float
    {
        return (float) $this->attendances->sum('paid_amount');
    }

    public function getFinesOutstandingAttribute(): float
    {
        return max(0, $this->fines_total - $this->fines_paid);
    }
}
