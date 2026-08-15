<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotationPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'rotation_turn_id', 'rotation_id', 'group_id', 'member_id',
        'amount', 'paid_on', 'method', 'reference',
        'cashbook_entry_id', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function turn(): BelongsTo     { return $this->belongsTo(RotationTurn::class, 'rotation_turn_id'); }
    public function rotation(): BelongsTo { return $this->belongsTo(Rotation::class); }
    public function group(): BelongsTo    { return $this->belongsTo(Group::class); }
    public function member(): BelongsTo   { return $this->belongsTo(Member::class); }
    public function cashbook(): BelongsTo { return $this->belongsTo(CashbookEntry::class, 'cashbook_entry_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
