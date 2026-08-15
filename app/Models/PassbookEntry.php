<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PassbookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'member_id', 'entry_date', 'description', 'category',
        'debit', 'credit', 'balance', 'source_type', 'source_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit'      => 'decimal:2',
        'credit'     => 'decimal:2',
        'balance'    => 'decimal:2',
    ];

    public function group(): BelongsTo  { return $this->belongsTo(Group::class); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function source(): MorphTo   { return $this->morphTo(); }
}
