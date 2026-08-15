<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * KOPEC loan interest model:
 *   Interest = Principal × Rate%  (one-time flat, no term multiplier)
 *   Total Repayable = Principal + Interest
 *   No fixed monthly schedule — member pays when they can.
 */
class KopecLoanSeeder extends Seeder
{
    private const RATE = 5;          // 5 % charged once on the principal
    private const GROUP = 4;
    private const ADMIN = 1;

    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $loanIds = DB::table('loans')->where('group_id', self::GROUP)->pluck('id');
        DB::table('loan_repayments')->whereIn('loan_id', $loanIds)->delete();
        DB::table('loans')->where('group_id', self::GROUP)->delete();

        $now = now()->toDateTimeString();

        // ---------------------------------------------------------------
        // 1. Innocent (13) — 1,000,000 from 07/04/2026, no payments made
        // ---------------------------------------------------------------
        $this->loan($now, [
            'member_id'    => 13,
            'reference'    => 'KP-LOAN-KP001-202604',
            'principal'    => 1_000_000,
            'purpose'      => 'Business investment',
            'disbursed_on' => '2026-04-07',
            'requested_on' => '2026-04-05',
            'approved_on'  => '2026-04-06',
            'notes'        => 'No repayment made to date',
        ]);

        // ---------------------------------------------------------------
        // 2. Athanase (14) — 2,000,000 from 01/06/2026, no payments
        // ---------------------------------------------------------------
        $this->loan($now, [
            'member_id'    => 14,
            'reference'    => 'KP-LOAN-KP002-202606A',
            'principal'    => 2_000_000,
            'purpose'      => 'Agriculture',
            'disbursed_on' => '2026-06-01',
            'requested_on' => '2026-05-30',
            'approved_on'  => '2026-05-31',
        ]);

        // ---------------------------------------------------------------
        // 3. Athanase (14) — 3,000,000 from 07/06/2026, no payments
        // ---------------------------------------------------------------
        $this->loan($now, [
            'member_id'    => 14,
            'reference'    => 'KP-LOAN-KP002-202606B',
            'principal'    => 3_000_000,
            'purpose'      => 'Trade capital',
            'disbursed_on' => '2026-06-07',
            'requested_on' => '2026-06-05',
            'approved_on'  => '2026-06-06',
        ]);

        // ---------------------------------------------------------------
        // 4. Salvator (20) — 1,000,000 from 12/06/2026 (yesterday)
        // ---------------------------------------------------------------
        $this->loan($now, [
            'member_id'    => 20,
            'reference'    => 'KP-LOAN-KP008-202606',
            'principal'    => 1_000_000,
            'purpose'      => 'Working capital',
            'disbursed_on' => '2026-06-12',
            'requested_on' => '2026-06-11',
            'approved_on'  => '2026-06-12',
        ]);

        // ---------------------------------------------------------------
        // 5. Joel Uwamungu (16) — 500,000 from 01/05/2026, no payments
        // ---------------------------------------------------------------
        $this->loan($now, [
            'member_id'    => 16,
            'reference'    => 'KP-LOAN-KP004-202605',
            'principal'    => 500_000,
            'purpose'      => 'School fees',
            'disbursed_on' => '2026-05-01',
            'requested_on' => '2026-04-29',
            'approved_on'  => '2026-04-30',
            'notes'        => 'No repayment made to date',
        ]);

        // ---------------------------------------------------------------
        // 6. Jean Claude (15) — 500,000 disbursed Jan 2026
        //    Interest = 500,000 × 5% = 25,000 (charged once)
        //    Total repayable = 525,000
        //    She repaid the full principal (500,000); only 25,000 interest
        //    (June) still outstanding.
        // ---------------------------------------------------------------
        $principalC  = 500_000;
        $interestC   = (int) round($principalC * self::RATE / 100); // 25,000
        $repayableC  = $principalC + $interestC;                     // 525,000
        $repaidC     = $principalC;                                  // paid principal only
        $outstandingC = $repayableC - $repaidC;                      // 25,000

        $loanIdC = DB::table('loans')->insertGetId([
            'group_id'          => self::GROUP,
            'member_id'         => 15,
            'reference'         => 'KP-LOAN-KP003-202601',
            'principal'         => $principalC,
            'interest_rate_pct' => self::RATE,
            'interest_model'    => 'flat',
            'term_months'       => null,
            'purpose'           => 'House repair',
            'status'            => 'repaying',
            'requested_on'      => '2026-01-08',
            'approved_on'       => '2026-01-09',
            'approved_by'       => self::ADMIN,
            'disbursed_on'      => '2026-01-10',
            'due_on'            => null,
            'total_interest'    => $interestC,
            'total_repayable'   => $repayableC,
            'amount_repaid'     => $repaidC,
            'outstanding'       => $outstandingC,
            'late_fee_amount'   => 0,
            'rejection_reason'  => null,
            'notes'             => 'Principal fully repaid; 25,000 interest outstanding',
            'created_at'        => $now,
            'updated_at'        => $now,
            'deleted_at'        => null,
        ]);

        // Single repayment: principal paid in full on June 1
        DB::table('loan_repayments')->insert([
            'loan_id'          => $loanIdC,
            'amount'           => $principalC,
            'principal_portion'=> $principalC,
            'interest_portion' => 0,
            'paid_on'          => '2026-06-01',
            'method'           => 'cash',
            'reference'        => 'KP-REPR-KP003-202606',
            'recorded_by'      => self::ADMIN,
            'notes'            => 'Full principal repaid; interest (25,000) to be settled',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        DB::statement('PRAGMA foreign_keys = ON');

        // Print summary
        echo "Loans seeded!\n\n";
        echo sprintf("%-26s | %-26s | %-10s | %12s | %12s | %12s\n",
            'Member', 'Reference', 'Status', 'Principal', 'Interest', 'Outstanding');
        echo str_repeat('-', 100) . "\n";

        $loans = DB::table('loans as l')
            ->join('members as m', 'm.id', 'l.member_id')
            ->where('l.group_id', self::GROUP)
            ->orderBy('l.disbursed_on')
            ->get(['m.full_name','l.reference','l.principal','l.total_interest','l.outstanding','l.status']);

        foreach ($loans as $l) {
            printf("%-26s | %-26s | %-10s | %12s | %12s | %12s\n",
                $l->full_name, $l->reference, $l->status,
                number_format($l->principal),
                number_format($l->total_interest),
                number_format($l->outstanding));
        }

        echo "\nTotal outstanding: " . number_format(
            DB::table('loans')->where('group_id', self::GROUP)->sum('outstanding')
        ) . " RWF\n";
    }

    /** Insert a loan with the one-time flat interest model, no repayments (status=disbursed). */
    private function loan(string $now, array $data): void
    {
        $principal  = $data['principal'];
        $interest   = (int) round($principal * self::RATE / 100);
        $repayable  = $principal + $interest;

        DB::table('loans')->insert([
            'group_id'          => self::GROUP,
            'member_id'         => $data['member_id'],
            'reference'         => $data['reference'],
            'principal'         => $principal,
            'interest_rate_pct' => self::RATE,
            'interest_model'    => 'flat',
            'term_months'       => null,
            'purpose'           => $data['purpose'] ?? null,
            'status'            => 'disbursed',
            'requested_on'      => $data['requested_on'],
            'approved_on'       => $data['approved_on'],
            'approved_by'       => self::ADMIN,
            'disbursed_on'      => $data['disbursed_on'],
            'due_on'            => null,
            'total_interest'    => $interest,
            'total_repayable'   => $repayable,
            'amount_repaid'     => 0,
            'outstanding'       => $repayable,
            'late_fee_amount'   => 0,
            'rejection_reason'  => null,
            'notes'             => $data['notes'] ?? null,
            'created_at'        => $now,
            'updated_at'        => $now,
            'deleted_at'        => null,
        ]);
    }
}
