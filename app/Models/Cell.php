<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    protected $primaryKey = 'code';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $fillable   = ['code', 'name', 'sector_code'];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_code', 'code');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'cell_code', 'code');
    }
}
