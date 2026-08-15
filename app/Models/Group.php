<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'village', 'cell', 'sector', 'district', 'region', 'country',
        'province_code', 'district_code', 'sector_code', 'cell_code', 'village_code',
        'currency', 'formed_on', 'cycle_starts_on', 'cycle_ends_on', 'status', 'created_by',
    ];

    protected $casts = [
        'formed_on'       => 'date',
        'cycle_starts_on' => 'date',
        'cycle_ends_on'   => 'date',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'group_member')
            ->withPivot(['position', 'joined_at', 'left_at', 'share_count', 'is_active'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('is_active', true);
    }

    /**
     * Staff users (group_admin / treasurer / secretary) assigned to this group.
     */
    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('role_in_group')
            ->withTimestamps();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(GroupRule::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ContributionSchedule::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function arrears(): HasMany
    {
        return $this->hasMany(Arrear::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        $rule = $this->rules->firstWhere('key', $key);
        if (! $rule) return $default;
        return match ($rule->type) {
            'numeric', 'percent', 'days' => is_numeric($rule->value) ? $rule->value + 0 : $default,
            'boolean' => filter_var($rule->value, FILTER_VALIDATE_BOOL),
            default   => $rule->value,
        };
    }
}
