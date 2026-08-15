<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RotationTurn extends Model
{
    use HasFactory;

    protected $fillable = [
        'rotation_id', 'sequence_no', 'scheduled_on', 'status',
        'disbursement_total', 'executed_on', 'executed_by', 'notes',
    ];

    protected $casts = [
        'scheduled_on'       => 'date',
        'executed_on'        => 'date',
        'sequence_no'        => 'integer',
        'disbursement_total' => 'decimal:2',
    ];

    public function rotation(): BelongsTo { return $this->belongsTo(Rotation::class); }
    public function executor(): BelongsTo { return $this->belongsTo(User::class, 'executed_by'); }
    public function payouts(): HasMany    { return $this->hasMany(RotationPayout::class); }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'scheduled' => 'yellow',
            'paid'      => 'green',
            'skipped'   => 'red',
            default     => 'secondary',
        };
    }
}
