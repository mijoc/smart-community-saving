<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The vsla:accrue-loan-interest --period=2026-07 command correctly added the
 * 3rd month for Innocent and 2nd month for Joel (both anniversaries passed),
 * but prematurely charged loans that haven't reached their first month yet:
 *   - Athanase A (disbursed Jun 1):  first month due Jul 1 — not yet
 *   - Athanase B (disbursed Jun 7):  first month due Jul 7 — not yet
 *   - Salvator   (disbursed Jun 12): first month due Jul 12 — not yet
 *   - Jean Claude (only 25k interest outstanding) — no compound on interest-only
 *
 * This seeder deletes those premature Jul-2026 accruals and reverts outstanding.
 */
class KopecAccrualCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $premature = [
            'KP-LOAN-KP002-202606A' => 2_000_000,  // Athanase A  → revert to principal
            'KP-LOAN-KP002-202606B' => 3_000_000,  // Athanase B  → revert to principal
            'KP-LOAN-KP008-202606'  => 1_000_000,  // Salvator    → revert to principal
            'KP-LOAN-KP003-202601'  => 25_000,     // Jean Claude → revert to 25k interest only
        ];

        foreach ($premature as $ref => $correctOutstanding) {
            $loan = DB::table('loans')->where('reference', $ref)->first();
            if (! $loan) continue;

            // Delete the July 2026 accrual for this loan
            $deleted = DB::table('loan_interest_accruals')
                ->where('loan_id', $loan->id)
                ->whereYear('period', 2026)
                ->whereMonth('period', 7)
                ->delete();

            // Revert outstanding and total_interest
            $totalInterest = DB::table('loan_interest_accruals')
                ->where('loan_id', $loan->id)
                ->sum('interest_amount');

            $lastAccrual = DB::table('loan_interest_accruals')
                ->where('loan_id', $loan->id)
                ->orderByDesc('period')
                ->first();

            $outstanding = $lastAccrual
                ? (float) $lastAccrual->balance_after
                : $correctOutstanding;

            DB::table('loans')->where('id', $loan->id)->update([
                'total_interest'  => $totalInterest,
                'total_repayable' => $loan->principal + $totalInterest,
                'outstanding'     => $outstanding ?: $correctOutstanding,
                'updated_at'      => now()->toDateTimeString(),
            ]);

            printf("  Reverted %-26s → outstanding: %s  (deleted %d Jul accrual)\n",
                $ref, number_format($outstanding ?: $correctOutstanding), $deleted);
        }

        echo "\n--- Final accrual ledger ---\n";
        printf("  %-22s | %-7s | %10s × %4s = %8s  →  %10s\n",
            'Member', 'Period', 'Bal.Before', 'Rate', 'Interest', 'Bal.After');
        echo '  ' . str_repeat('-', 74) . "\n";
        DB::table('loan_interest_accruals as a')
            ->join('loans as l', 'l.id', 'a.loan_id')
            ->join('members as m', 'm.id', 'l.member_id')
            ->orderBy('m.full_name')->orderBy('a.period')
            ->get(['m.full_name','a.period','a.balance_before','a.rate_pct','a.interest_amount','a.balance_after'])
            ->each(fn($r) => printf("  %-22s | %s | %10s × %3.0f%% = %8s  →  %10s\n",
                $r->full_name, substr($r->period, 0, 7),
                number_format($r->balance_before), $r->rate_pct,
                number_format($r->interest_amount), number_format($r->balance_after)));

        echo "\n--- Final outstanding ---\n";
        DB::table('loans as l')
            ->join('members as m', 'm.id', 'l.member_id')
            ->where('l.group_id', 4)->orderBy('l.disbursed_on')
            ->get(['m.full_name','l.disbursed_on','l.principal','l.total_interest','l.outstanding'])
            ->each(fn($r) => printf("  %-22s | disbursed %s | P=%10s  I=%8s  Out=%11s\n",
                $r->full_name, $r->disbursed_on,
                number_format($r->principal), number_format($r->total_interest),
                number_format($r->outstanding)));
    }
}
