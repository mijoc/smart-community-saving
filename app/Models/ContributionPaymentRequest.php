<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionPaymentRequest extends Model
{
    protected $fillable = [
        'contribution_id', 'member_id', 'group_id',
        'amount', 'method', 'channel_ref', 'paid_on', 'notes', 'proof_path',
        'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_path ? SystemSetting::publicUrl('/storage/'.$this->proof_path) : null;
    }

    public function getProofIsImageAttribute(): bool
    {
        if (! $this->proof_path) return false;
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->proof_path);
    }

    public function getProofIsPdfAttribute(): bool
    {
        if (! $this->proof_path) return false;
        return str_ends_with(strtolower($this->proof_path), '.pdf');
    }

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_on'     => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public static function pendingCountForGroup(int $groupId): int
    {
        return static::where('group_id', $groupId)
            ->where('status', 'pending_review')
            ->count();
    }
}
