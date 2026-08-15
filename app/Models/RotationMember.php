<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotationMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'rotation_id', 'member_id', 'position', 'received_count', 'last_received_on',
    ];

    protected $casts = [
        'position'         => 'integer',
        'received_count'   => 'integer',
        'last_received_on' => 'date',
    ];

    public function rotation(): BelongsTo { return $this->belongsTo(Rotation::class); }
    public function member(): BelongsTo   { return $this->belongsTo(Member::class); }
}
