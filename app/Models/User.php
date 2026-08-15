<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'phone', 'avatar_path', 'is_active', 'member_id', 'locale', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'preferences'       => 'array',
        ];
    }

    /** Convenience helper to read a single preference with a default. */
    public function pref(string $key, mixed $default = false): mixed
    {
        return $this->preferences[$key] ?? $default;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Groups this user has been assigned to as STAFF
     * (group_admin / treasurer / secretary).
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isGroupAdmin(): bool
    {
        return $this->hasRole('group_admin');
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin() || $this->isGroupAdmin();
    }

    public function belongsToGroup(int $groupId): bool
    {
        if ($this->member && $this->member->groups()->where('groups.id', $groupId)->exists()) {
            return true;
        }

        return $this->groups()->where('groups.id', $groupId)->exists();
    }

    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where(function (Builder $q) use ($groupId) {
            $q->whereHas('member.groups', fn (Builder $q) => $q->where('groups.id', $groupId))
                ->orWhereHas('groups', fn (Builder $q) => $q->where('groups.id', $groupId));
        });
    }

    /** Members with no staff/admin role cannot change their login username. */
    public function canChangeUsername(): bool
    {
        if (! $this->hasRole('member')) {
            return true;
        }

        return $this->hasAnyRole(['super_admin', 'group_admin', 'treasurer', 'secretary']);
    }

    /**
     * All groups this user can access in the UI:
     *  - super_admin → every group
     *  - staff (group_admin / treasurer / secretary) → their assigned groups
     *  - member → groups they belong to via the member record
     */
    public function accessibleGroups(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Group::orderBy('name')->get();
        }

        $staffGroups = $this->groups()->orderBy('name')->get();

        if ($this->member_id && $this->member) {
            $memberGroups = $this->member->groups()->orderBy('name')->get();
            return $staffGroups->concat($memberGroups)->unique('id')->values();
        }

        return $staffGroups;
    }

    /**
     * Is this group assigned to the user at all (staff or member)?
     * Used by the group switcher only — for *viewing data* prefer
     * canAccessGroup(), which also requires the group to be the active one.
     */
    public function isAssignedToGroup(int $groupId): bool
    {
        if ($this->isSuperAdmin()) return true;
        return $this->accessibleGroups()->contains('id', $groupId);
    }

    /**
     * Can this user view info for the given group right now?
     *  - super_admin → always yes
     *  - everyone else → only if the group is the one they are
     *    currently switched into via the active-group switcher
     */
    public function canAccessGroup(int $groupId): bool
    {
        if ($this->isSuperAdmin()) return true;
        $activeId = (int) session('active_group_id');
        return $activeId === $groupId
            && $this->accessibleGroups()->contains('id', $groupId);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            $url = SystemSetting::publicUrl('/storage/'.$this->avatar_path);
            if ($url) {
                return $url;
            }
        }
        if ($this->member?->photo_path) {
            $url = SystemSetting::publicUrl('/storage/'.$this->member->photo_path);
            if ($url) {
                return $url;
            }
        }
        $initials = collect(explode(' ', $this->name))->take(2)->map(fn ($w) => strtoupper(substr($w, 0, 1)))->join('');
        return 'https://ui-avatars.com/api/?name='.urlencode($initials).'&background=206bc4&color=fff&bold=true';
    }
}
