<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $primaryKey = 'code';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $fillable   = ['code', 'name', 'district_code'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_code', 'code');
    }

    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class, 'sector_code', 'code');
    }
}
