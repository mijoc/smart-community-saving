<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'group_id', 'member_id', 'contribution_id',
        'amount', 'method', 'channel_ref', 'paid_on', 'notes', 'received_by',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'amount'  => 'decimal:2',
    ];

    public function group(): BelongsTo        { return $this->belongsTo(Group::class); }
    public function member(): BelongsTo       { return $this->belongsTo(Member::class); }
    public function contribution(): BelongsTo { return $this->belongsTo(Contribution::class); }
    public function receiver(): BelongsTo     { return $this->belongsTo(User::class, 'received_by'); }
}
