<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends Model
{
    protected $primaryKey = 'code';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $fillable   = ['code', 'name', 'cell_code'];

    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class, 'cell_code', 'code');
    }
}
