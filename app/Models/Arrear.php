<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arrear extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'member_id', 'contribution_id',
        'outstanding_amount', 'late_fee_applied', 'days_overdue',
        'first_overdue_on', 'last_evaluated_on', 'status', 'notes',
    ];

    protected $casts = [
        'first_overdue_on'   => 'date',
        'last_evaluated_on'  => 'date',
        'outstanding_amount' => 'decimal:2',
        'late_fee_applied'   => 'decimal:2',
    ];

    public function group(): BelongsTo        { return $this->belongsTo(Group::class); }
    public function member(): BelongsTo       { return $this->belongsTo(Member::class); }
    public function contribution(): BelongsTo { return $this->belongsTo(Contribution::class); }
}
