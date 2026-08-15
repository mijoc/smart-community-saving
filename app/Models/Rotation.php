<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id', 'name', 'frequency', 'recipients_per_turn',
        'disbursement_method', 'disbursement_pct', 'disbursement_amount',
        'starts_on', 'next_turn_on', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'starts_on'           => 'date',
        'next_turn_on'        => 'date',
        'recipients_per_turn' => 'integer',
        'disbursement_pct'    => 'decimal:3',
        'disbursement_amount' => 'decimal:2',
    ];

    public function group(): BelongsTo   { return $this->belongsTo(Group::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function members(): HasMany { return $this->hasMany(RotationMember::class)->orderBy('position'); }
    public function turns(): HasMany   { return $this->hasMany(RotationTurn::class)->orderBy('sequence_no'); }
    public function payouts(): HasMany { return $this->hasMany(RotationPayout::class); }

    public function frequencyLabel(): string
    {
        return ucfirst($this->frequency);
    }

    public function disbursementLabel(): string
    {
        return match ($this->disbursement_method) {
            'full'       => 'Full cash on hand',
            'percentage' => rtrim(rtrim((string) $this->disbursement_pct, '0'), '.').'% of cash on hand',
            'fixed'      => number_format((float) $this->disbursement_amount, 2).' per turn',
            default      => $this->disbursement_method,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'active'    => 'green',
            'completed' => 'blue',
            'cancelled' => 'red',
            default     => 'secondary',
        };
    }
}
