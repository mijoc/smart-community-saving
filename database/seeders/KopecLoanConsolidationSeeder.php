<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bank "top-up" consolidation for Athanase's two loans:
 *
 *   Loan A  KP-LOAN-KP002-202606A  disbursed 2026-06-01  principal 2,000,000
 *   Loan B  KP-LOAN-KP002-202606B  disbursed 2026-06-07  principal 3,000,000
 *
 * Loan B is the top-up.  At top-up time the outstanding balance of Loan A
 * (2,000,000) is rolled into Loan B so the member carries ONE loan of
 * 5,000,000 starting 2026-06-07.  Interest accrues from Jun 7 → first
 * monthly anniversary Jul 7.  Loan A is closed as "consolidated".
 */
class KopecLoanConsolidationSeeder extends Seeder
{
    public function run(): void
    {
        $loanA = DB::table('loans')->where('reference', 'KP-LOAN-KP002-202606A')->first();
        $loanB = DB::table('loans')->where('reference', 'KP-LOAN-KP002-202606B')->first();

        if (! $loanA || ! $loanB) {
            $this->command->error('Could not find Athanase loans — run KopecLoanSeeder first.');
            return;
        }

        $priorOutstanding  = (float) $loanA->outstanding;          // 2,000,000
        $newPrincipal      = (float) $loanB->principal + $priorOutstanding;  // 5,000,000
        $consolidatedRef   = 'KP-LOAN-KP002-202606';               // clean single reference

        // ── 1. Close Loan A ──────────────────────────────────────────────────
        DB::table('loans')->where('id', $loanA->id)->update([
            'status'     => 'consolidated',
            'notes'      => trim(($loanA->notes ?? '') . ' | Consolidated into ' . $consolidatedRef . ' on 2026-06-07'),
            'updated_at' => now()->toDateTimeString(),
        ]);

        // Delete any accruals that may have been added to Loan A
        DB::table('loan_interest_accruals')->where('loan_id', $loanA->id)->delete();

        // ── 2. Upgrade Loan B into the consolidated loan ─────────────────────
        DB::table('loans')->where('id', $loanB->id)->update([
            'reference'            => $consolidatedRef,
            'principal'            => $newPrincipal,           // 5,000,000
            'outstanding'          => $newPrincipal,           // no interest yet
            'total_interest'       => 0,                       // fresh start
            'total_repayable'      => $newPrincipal,
            'prior_outstanding'    => $priorOutstanding,       // 2,000,000 (audit)
            'consolidated_loan_ids'=> json_encode([$loanA->id]),
            'notes'                => 'Top-up loan: rolls in KP-LOAN-KP002-202606A (2,000,000 outstanding on 2026-06-07). Combined principal 5,000,000. Interest from 2026-06-07.',
            'updated_at'           => now()->toDateTimeString(),
        ]);

        // Delete any premature accruals on Loan B (first month not due until Jul 7)
        DB::table('loan_interest_accruals')->where('loan_id', $loanB->id)->delete();

        // ── 3. Summary ───────────────────────────────────────────────────────
        echo PHP_EOL;
        echo "  CONSOLIDATED LOAN (Athanase HABYARIMANA)\n";
        echo "  ────────────────────────────────────────────────────────\n";
        printf("  Old Loan A  %-28s  %10s  → CLOSED (consolidated)\n",
            'KP-LOAN-KP002-202606A', number_format(2_000_000));
        printf("  Old Loan B  %-28s  %10s  → rolled into top-up\n",
            'KP-LOAN-KP002-202606B', number_format(3_000_000));
        echo "  ────────────────────────────────────────────────────────\n";
        printf("  New Loan    %-28s  %10s  disbursed 2026-06-07\n",
            $consolidatedRef, number_format($newPrincipal));
        echo "              prior_outstanding = " . number_format($priorOutstanding) . " (Loan A rolled in)\n";
        echo "              First interest due: 2026-07-07 (5% of 5,000,000 = 250,000)\n";
        echo PHP_EOL;
    }
}
