<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInterestAccrual extends Model
{
    protected $fillable = [
        'loan_id', 'period', 'balance_before',
        'rate_pct', 'interest_amount', 'balance_after',
    ];

    protected $casts = [
        'period'          => 'date',
        'balance_before'  => 'decimal:2',
        'rate_pct'        => 'decimal:3',
        'interest_amount' => 'decimal:2',
        'balance_after'   => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
