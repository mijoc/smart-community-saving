<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashbookEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'group_id', 'type', 'category', 'amount',
        'method', 'channel_ref', 'counterparty', 'occurred_on', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'amount'      => 'decimal:2',
    ];

    public const INCOME_CATEGORIES = [
        'bank_interest'      => 'Bank interest',
        'donation'           => 'Donation',
        'grant'              => 'Grant',
        'fundraising'        => 'Fundraising',
        'asset_sale'         => 'Asset sale',
        'refund'             => 'Refund',
        'attendance_fine'    => 'Attendance fine',
        'other_income'       => 'Other income',
        'balance_adjustment' => 'Balance adjustment',
    ];

    public const EXPENSE_CATEGORIES = [
        'stationery'         => 'Stationery',
        'transport'          => 'Transport',
        'refreshments'       => 'Refreshments',
        'bank_charge'        => 'Bank charges',
        'equipment'          => 'Equipment',
        'rent'               => 'Rent',
        'salary_stipend'     => 'Salary / stipend',
        'staff_withdraw'     => 'Staff withdrawal',
        'utilities'          => 'Utilities',
        'rotation_payout'    => 'Rotation payout',
        'share_out'          => 'Share-out / dividend',
        'other_expense'      => 'Other expense',
        'balance_adjustment' => 'Balance adjustment',
    ];

    /**
     * Categories excluded from the profit/loss calculation.
     * These entries affect the cash balance but are pure balance-sheet
     * adjustments — not real operating income or expense.
     */
    public const NON_PROFIT_CATEGORIES = ['balance_adjustment'];

    /**
     * Regularization is a private admin-only expense used to correct the
     * group cash position. It is intentionally not part of the normal
     * expense category picker.
     */
    public const REGULARIZATION_CATEGORY = 'regularization';

    public static function categoriesFor(string $type): array
    {
        return $type === 'income' ? self::INCOME_CATEGORIES : self::EXPENSE_CATEGORIES;
    }

    public static function categoryLabel(string $type, ?string $category): string
    {
        if ($category === self::REGULARIZATION_CATEGORY) {
            return 'Regularization';
        }

        return self::categoriesFor($type)[$category] ?? (string) $category;
    }

    public function isRegularization(): bool
    {
        return $this->category === self::REGULARIZATION_CATEGORY;
    }

    public function group(): BelongsTo    { return $this->belongsTo(Group::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
