<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeds TABA YACU (group_id=4) with:
 *  - Monthly contributions Jul 2024 → Jun 2026 (50,000/member)
 *  - Payments for all paid months
 *  - Arrears matching the real outstanding amounts
 *  - 6 active loans + 1 cleared loan
 *  - Loan interest accruals
 *  - One cashbook income entry (prior activities) to bring balance to 1,353,710
 */
class TabaYacuSeeder extends Seeder
{
    const GROUP_ID   = 4;
    const ADMIN_ID   = 2;
    const AMOUNT     = 50000;
    const GRACE_DAYS = 5;
    const LATE_FEE_PCT = 5;

    // member_id => [loan_principal, interest_rate_pct, loan_status]
    // 0 principal means no loan
    const LOANS = [
        13 => ['principal' => 1000000, 'rate' => 15,  'status' => 'disbursed'],
        14 => ['principal' => 5000000, 'rate' =>  5,  'status' => 'disbursed'],
        15 => ['principal' => 1500000, 'rate' =>  6.6667, 'status' => 'disbursed'],
        16 => ['principal' =>  500000, 'rate' =>  5,  'status' => 'paid'],
        17 => ['principal' =>       0, 'rate' =>  0,  'status' => null],
        18 => ['principal' => 1000000, 'rate' => 14,  'status' => 'disbursed'],
        19 => ['principal' =>       0, 'rate' =>  0,  'status' => null],
        20 => ['principal' => 1000000, 'rate' =>  5,  'status' => 'disbursed'],
    ];

    // member_id => [month (Y-m), late_fee_amount, total_outstanding]
    // These are contributions that are OVERDUE (not paid)
    const OVERDUE = [
        13 => [
            ['month' => '2026-03', 'late_fee' => 2500,  'total' => 52500],
            ['month' => '2026-04', 'late_fee' => 2500,  'total' => 52500],
            ['month' => '2026-05', 'late_fee' => 2500,  'total' => 52500],
        ],
        14 => [
            ['month' => '2024-08', 'late_fee' => 39500, 'total' => 89500],
        ],
        18 => [
            ['month' => '2026-05', 'late_fee' => 2500,  'total' => 52500],
        ],
    ];

    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $now = Carbon::now()->toDateTimeString();
        $gid  = self::GROUP_ID;
        $aid  = self::ADMIN_ID;

        // 1. Contribution schedule
        $schedId = DB::table('contribution_schedules')->insertGetId([
            'group_id'          => $gid,
            'name'              => 'Monthly Savings',
            'type'              => 'savings',
            'frequency'         => 'monthly',
            'amount'            => self::AMOUNT,
            'start_date'        => '2024-07-01',
            'end_date'          => null,
            'next_due_on'       => '2026-07-05',
            'last_generated_on' => '2026-06-01',
            'grace_days'        => self::GRACE_DAYS,
            'late_fee_pct'      => self::LATE_FEE_PCT,
            'late_fee_flat'     => 0,
            'is_active'         => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // 2. Members for this group
        $members = [13, 14, 15, 16, 17, 18, 19, 20];

        // Build overdue lookup: member_id → month_string → [late_fee, total]
        $overdueMap = [];
        foreach (self::OVERDUE as $mid => $months) {
            foreach ($months as $m) {
                $overdueMap[$mid][$m['month']] = $m;
            }
        }

        // 3. Generate months Jul 2024 – Jun 2026
        $start  = Carbon::create(2024, 7, 1);
        $end    = Carbon::create(2026, 6, 1);
        $cursor = $start->copy();
        $today  = Carbon::today();

        $payRef = 1;
        while ($cursor->lte($end)) {
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd   = $cursor->copy()->endOfMonth();
            $dueOn       = $periodEnd->copy()->addDays(self::GRACE_DAYS);
            $monthKey    = $cursor->format('Y-m');
            $isCurrent   = ($cursor->year === 2026 && $cursor->month === 6);

            foreach ($members as $mid) {
                $isOverdue = !$isCurrent && isset($overdueMap[$mid][$monthKey]);
                $overdueInfo = $overdueMap[$mid][$monthKey] ?? null;

                if ($isCurrent) {
                    // June 2026 – everyone pending
                    DB::table('contributions')->insert([
                        'group_id'                  => $gid,
                        'member_id'                 => $mid,
                        'contribution_schedule_id'  => $schedId,
                        'type'                      => 'savings',
                        'expected_amount'           => self::AMOUNT,
                        'paid_amount'               => 0,
                        'late_fee_amount'           => 0,
                        'period_start'              => $periodStart->toDateString(),
                        'period_end'                => $periodEnd->toDateString(),
                        'due_on'                    => $dueOn->toDateString(),
                        'paid_on'                   => null,
                        'status'                    => 'pending',
                        'notes'                     => null,
                        'created_by'                => $aid,
                        'created_at'                => $now,
                        'updated_at'                => $now,
                    ]);
                } elseif ($isOverdue) {
                    // Overdue contribution
                    $contribId = DB::table('contributions')->insertGetId([
                        'group_id'                  => $gid,
                        'member_id'                 => $mid,
                        'contribution_schedule_id'  => $schedId,
                        'type'                      => 'savings',
                        'expected_amount'           => self::AMOUNT,
                        'paid_amount'               => 0,
                        'late_fee_amount'           => $overdueInfo['late_fee'],
                        'period_start'              => $periodStart->toDateString(),
                        'period_end'                => $periodEnd->toDateString(),
                        'due_on'                    => $dueOn->toDateString(),
                        'paid_on'                   => null,
                        'status'                    => 'overdue',
                        'notes'                     => null,
                        'created_by'                => $aid,
                        'created_at'                => $now,
                        'updated_at'                => $now,
                    ]);

                    // Arrear record
                    DB::table('arrears')->insert([
                        'group_id'          => $gid,
                        'member_id'         => $mid,
                        'contribution_id'   => $contribId,
                        'outstanding_amount'=> $overdueInfo['total'],
                        'late_fee_applied'  => $overdueInfo['late_fee'],
                        'days_overdue'      => max(1, $dueOn->diffInDays($today)),
                        'first_overdue_on'  => $dueOn->toDateString(),
                        'last_evaluated_on' => $today->toDateString(),
                        'status'            => 'open',
                        'notes'             => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                } else {
                    // Paid contribution — pay 1-4 days before due, but cap to period_end
                    // so payments never spill into the following month.
                    $paidOn = $dueOn->copy()->subDays(rand(1, 4));
                    if ($paidOn->gt($periodEnd)) {
                        $paidOn = $periodEnd->copy()->subDays(rand(0, 3));
                    }
                    $contribId = DB::table('contributions')->insertGetId([
                        'group_id'                  => $gid,
                        'member_id'                 => $mid,
                        'contribution_schedule_id'  => $schedId,
                        'type'                      => 'savings',
                        'expected_amount'           => self::AMOUNT,
                        'paid_amount'               => self::AMOUNT,
                        'late_fee_amount'           => 0,
                        'period_start'              => $periodStart->toDateString(),
                        'period_end'                => $periodEnd->toDateString(),
                        'due_on'                    => $dueOn->toDateString(),
                        'paid_on'                   => $paidOn->toDateString(),
                        'status'                    => 'paid',
                        'notes'                     => null,
                        'created_by'                => $aid,
                        'created_at'                => $now,
                        'updated_at'                => $now,
                    ]);

                    // Payment record
                    $ref = 'KP-SAV-'.sprintf('%03d', $mid).'-'.str_replace('-', '', $monthKey);
                    DB::table('payments')->insert([
                        'reference'     => $ref,
                        'group_id'      => $gid,
                        'member_id'     => $mid,
                        'contribution_id'=> $contribId,
                        'amount'        => self::AMOUNT,
                        'method'        => 'cash',
                        'channel_ref'   => null,
                        'paid_on'       => $paidOn->toDateString(),
                        'notes'         => null,
                        'received_by'   => $aid,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);

                    $payRef++;
                }
            }

            $cursor->addMonth();
        }

        // 4. Loans
        $loanDates = [
            13 => '2024-11-01',
            14 => '2024-10-01',
            15 => '2024-12-01',
            16 => '2024-09-01',
            18 => '2025-03-01',
            20 => '2025-02-01',
        ];

        $loanIds = [];
        foreach (self::LOANS as $mid => $loan) {
            if ($loan['principal'] === 0) continue;

            $principal     = $loan['principal'];
            $rate          = $loan['rate'];
            $totalInterest = round($principal * ($rate / 100), 2);
            $totalRepayable= $principal + $totalInterest;
            $disbDate      = $loanDates[$mid] ?? '2025-01-01';
            $status        = $loan['status'];
            $isRepaid      = ($status === 'paid');
            // Joel repaid principal only; interest (if any) remains outstanding on the loan.
            $amtRepaid     = $isRepaid ? $principal : 0;
            // 'outstanding' must mirror LoanService::approve()'s convention: total_repayable
            // (principal + interest) minus whatever has actually been repaid — NOT just
            // principal. Otherwise "Full payment" on the loan page silently drops interest.
            $outstanding   = max(0, round($totalRepayable - $amtRepaid, 2));
            $ref           = 'KP-LN-'.sprintf('%03d', $mid).'-'.str_replace('-', '', substr($disbDate, 0, 7));

            $loanId = DB::table('loans')->insertGetId([
                'group_id'         => $gid,
                'member_id'        => $mid,
                'reference'        => $ref,
                'principal'        => $principal,
                'interest_rate_pct'=> $rate,
                'term_months'      => null,
                'purpose'          => 'Business / personal use',
                'status'           => $isRepaid ? 'paid' : 'disbursed',
                'requested_on'     => Carbon::parse($disbDate)->subDays(5)->toDateString(),
                'approved_on'      => Carbon::parse($disbDate)->subDays(3)->toDateString(),
                'approved_by'      => $aid,
                'disbursed_on'     => $disbDate,
                'due_on'           => null,
                'total_interest'   => $totalInterest,
                'total_repayable'  => $totalRepayable,
                'amount_repaid'    => $amtRepaid,
                'outstanding'      => $outstanding,
                'rejection_reason' => null,
                'notes'            => null,
                'interest_model'   => 'flat',
                'prior_outstanding'=> null,
                'consolidated_loan_ids' => null,
                'late_fee_amount'  => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
                'deleted_at'       => null,
            ]);
            // 'paid' = fully repaid (principal cleared); status must match
            // TreasuryService filter: ['disbursed','repaying','paid',...]

            $loanIds[$mid] = $loanId;

            // Loan repayment for Joel (principal only – interest still outstanding)
            if ($isRepaid) {
                DB::table('loan_repayments')->insert([
                    'loan_id'           => $loanId,
                    'amount'            => $principal,
                    'principal_portion' => $principal,
                    'interest_portion'  => 0,
                    'paid_on'           => Carbon::parse($disbDate)->addMonths(4)->toDateString(),
                    'method'            => 'cash',
                    'reference'         => $ref.'-RPY',
                    'recorded_by'       => $aid,
                    'notes'             => 'Principal repaid; interest pending',
                    'status'            => 'approved',
                    'payment_type'      => 'principal',
                    'accrual_period'    => null,
                    'approved_by'       => $aid,
                    'approved_at'       => $now,
                    'rejection_reason'  => null,
                    'proof_file'        => null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            // Loan interest accrual (outstanding interest for active; unpaid interest for Joel)
            DB::table('loan_interest_accruals')->insert([
                'loan_id'        => $loanId,
                'period'         => $disbDate,
                'balance_before' => $principal,
                'rate_pct'       => $rate,
                'interest_amount'=> $totalInterest,
                'balance_after'  => $principal + $totalInterest,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // 5. Cashbook income – prior group activities bringing balance to 1,353,710
        // Calculation: contributions received (8,950,000) + Joel repayment (500,000)
        //              - loans disbursed (10,000,000) = -550,000
        // Target balance: 1,353,710  →  adjustment = 1,903,710
        DB::table('cashbook_entries')->insert([
            'reference'    => 'KP-ADJ-202407',
            'group_id'     => $gid,
            'type'         => 'income',
            'category'     => 'other_income',
            'amount'       => 1903710,
            'method'       => 'cash',
            'channel_ref'  => null,
            'counterparty' => 'Prior group activities & opening fund',
            'occurred_on'  => '2024-07-01',
            'notes'        => 'Opening fund balance from prior group activities before system recording',
            'recorded_by'  => $aid,
            'created_at'   => $now,
            'updated_at'   => $now,
            'deleted_at'   => null,
        ]);

        // 6. Rebuild passbook running balances
        $this->rebuildPassbook($gid, $schedId, $members, $now);

        DB::statement('PRAGMA foreign_keys = ON');

        $this->command->info('TABA YACU seeded successfully.');
        $this->command->info('Contributions: '.DB::table('contributions')->where('group_id',$gid)->count());
        $this->command->info('Payments: '.DB::table('payments')->where('group_id',$gid)->count());
        $this->command->info('Loans: '.DB::table('loans')->where('group_id',$gid)->count());
        $this->command->info('Arrears: '.DB::table('arrears')->where('group_id',$gid)->count());
    }

    private function rebuildPassbook(int $gid, int $schedId, array $members, string $now): void
    {
        // Build passbook entries per member: one row per paid contribution, ordered by date
        foreach ($members as $mid) {
            $running = 0;
            $contribs = DB::table('contributions')
                ->where('group_id', $gid)
                ->where('member_id', $mid)
                ->where('status', 'paid')
                ->orderBy('period_start')
                ->get();

            foreach ($contribs as $c) {
                $running += $c->paid_amount;
                DB::table('passbook_entries')->insert([
                    'group_id'    => $gid,
                    'member_id'   => $mid,
                    'entry_date'  => $c->paid_on,
                    'description' => 'Monthly savings - '.Carbon::parse($c->period_start)->format('M Y'),
                    'category'    => 'savings',
                    'credit'      => $c->paid_amount,
                    'debit'       => 0,
                    'balance'     => $running,
                    'source_type' => 'contribution',
                    'source_id'   => $c->id,
                    'notes'       => null,
                    'created_by'  => $gid,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }
}
