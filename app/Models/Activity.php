<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Route;

class Activity extends Model
{
    protected $fillable = [
        'group_id', 'actor_user_id', 'type', 'icon', 'color',
        'description', 'subject_type', 'subject_id', 'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the most useful page for this activity.
     *
     * Activities may reference different models, so keep the route mapping
     * here rather than making every notification view understand each type.
     * Unknown or route-less activity types fall back to the activity feed.
     */
    public function getUrlAttribute(): string
    {
        $fallback = route('activity.index');

        $routeName = match ($this->subject_type) {
            CashbookEntry::class => 'cashbook.show',
            Contribution::class => 'contributions.show',
            Group::class => 'groups.show',
            Loan::class => 'loans.show',
            Meeting::class => 'meetings.show',
            Member::class => 'members.show',
            Payment::class => 'payments.show',
            Rotation::class => 'rotations.show',
            default => null,
        };

        if (! $routeName || ! $this->subject_id || ! Route::has($routeName)) {
            return $fallback;
        }

        try {
            return route($routeName, $this->subject_id);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
