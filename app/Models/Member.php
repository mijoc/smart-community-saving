<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_no', 'first_name', 'last_name', 'full_name', 'gender', 'date_of_birth',
        'national_id', 'phone', 'email', 'photo_path', 'village', 'district', 'address',
        'province_code', 'district_code', 'sector_code', 'cell_code', 'village_code',
        'next_of_kin_name', 'next_of_kin_phone', 'occupation', 'status', 'joined_on', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_on'     => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Member $m) {
            $m->full_name = trim($m->first_name.' '.$m->last_name);
        });
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_member')
            ->withPivot(['position', 'joined_at', 'left_at', 'share_count', 'is_active'])
            ->withTimestamps();
    }

    public function activeGroups(): BelongsToMany
    {
        return $this->groups()->wherePivot('is_active', true);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
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

    public function passbookEntries(): HasMany
    {
        return $this->hasMany(PassbookEntry::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo_path) {
            $url = SystemSetting::publicUrl('/storage/'.$this->photo_path);
            if ($url) {
                return $url;
            }
        }
        if ($this->user?->avatar_path) {
            $url = SystemSetting::publicUrl('/storage/'.$this->user->avatar_path);
            if ($url) {
                return $url;
            }
        }
        $initials = strtoupper(substr($this->first_name, 0, 1).substr($this->last_name, 0, 1));
        return 'https://ui-avatars.com/api/?name='.urlencode($initials).'&background=4263eb&color=fff&bold=true';
    }
}
