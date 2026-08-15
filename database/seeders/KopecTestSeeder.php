<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * KopecTestSeeder — clean-slate test data for the KOPEC / TABA YACU group (id=4).
 *
 * Members (from group_member pivot):
 *   13 KP001 Innocent Munyantore      → paid NONE
 *   14 KP002 Athanase HABYARIMANA     → paid March + April
 *   15 KP003 Jean Claude Ikitegetse   → paid March only
 *   16 KP004 Joel Uwamungu            → paid all  + loan 50,000 disbursed 2026-05-04
 *   17 KP005 Aline UWAMWEZI           → paid all  + loan 100,000 disbursed 2026-04-03
 *   18 KP006 Emmanuel                 → paid all
 *   19 KP007 Jean Paul                → paid all
 *   20 KP008 Salvator                 → paid all
 */
class KopecTestSeeder extends Seeder
{
    private const GROUP_ID    = 4;
    private const SCHEDULE_ID = 4;
    private const AMOUNT      = 50000;

    // member_id → months paid (0=Mar, 1=Apr, 2=May, 3=Jun)
    private const PAID_MAP = [
        13 => [],           // Innocent — none
        14 => [0, 1],       // Athanase — March + April
        15 => [0],          // Jean Claude — March only
        16 => [0, 1, 2, 3], // Joel — all
        17 => [0, 1, 2, 3], // Aline — all
        18 => [0, 1, 2, 3], // Emmanuel — all
        19 => [0, 1, 2, 3], // Jean Paul — all
        20 => [0, 1, 2, 3], // Salvator — all
    ];

    private const MONTHS = [
        ['start' => '2026-03-01', 'end' => '2026-03-31', 'due' => '2026-04-02', 'label' => '202603'],
        ['start' => '2026-04-01', 'end' => '2026-04-30', 'due' => '2026-05-02', 'label' => '202604'],
        ['start' => '2026-05-01', 'end' => '2026-05-31', 'due' => '2026-06-02', 'label' => '202605'],
        ['start' => '2026-06-01', 'end' => '2026-06-30', 'due' => '2026-07-02', 'label' => '202606'],
    ];

    // member_no lookup (needed for references)
    private const MEMBER_NO = [
        13 => 'KP001', 14 => 'KP002', 15 => 'KP003', 16 => 'KP004',
        17 => 'KP005', 18 => 'KP006', 19 => 'KP007', 20 => 'KP008',
    ];

    public function run(): void
    {
        $gid = self::GROUP_ID;
        $now = now()->toDateTimeString();

        // ── 1. WIPE ──────────────────────────────────────────────────────────
        $contribIds = DB::table('contributions')->where('group_id', $gid)->pluck('id');
        $loanIds    = DB::table('loans')->where('group_id', $gid)->pluck('id');

        DB::table('passbook_entries')->where('group_id', $gid)->delete();
        DB::table('arrears')->where('group_id', $gid)->delete();
        DB::table('payments')->whereIn('contribution_id', $contribIds)->delete();
        DB::table('contributions')->where('group_id', $gid)->delete();
        DB::table('loan_repayments')->whereIn('loan_id', $loanIds)->delete();
        DB::table('loan_interest_accruals')->whereIn('loan_id', $loanIds)->delete();
        DB::table('loans')->where('group_id', $gid)->delete();
        DB::table('cashbook_entries')->where('group_id', $gid)->delete();
        DB::table('activities')->where('group_id', $gid)->delete();

        // ── 2. CONTRIBUTIONS + PAYMENTS ──────────────────────────────────────
        $today = Carbon::today();

        foreach (self::PAID_MAP as $memberId => $paidMonths) {
            $mno = self::MEMBER_NO[$memberId];

            foreach (self::MONTHS as $idx => $period) {
                $isPaid  = in_array($idx, $paidMonths);
                $dueOn   = Carbon::parse($period['due']);
                $overdue = !$isPaid && $dueOn->lt($today);

                $status    = $isPaid ? 'paid' : ($overdue ? 'overdue' : 'pending');
                $paidAmt   = $isPaid ? self::AMOUNT : 0;
                $paidOn    = $isPaid
                    ? Carbon::parse($period['start'])->addDays(5)->toDateString()
                    : null;

                $contribId = DB::table('contributions')->insertGetId([
                    'group_id'                 => $gid,
                    'member_id'                => $memberId,
                    'contribution_schedule_id' => self::SCHEDULE_ID,
                    'type'                     => 'savings',
                    'expected_amount'          => self::AMOUNT,
                    'paid_amount'              => $paidAmt,
                    'late_fee_amount'          => 0,
                    'period_start'             => $period['start'],
                    'period_end'               => $period['end'],
                    'due_on'                   => $period['due'],
                    'paid_on'                  => $paidOn,
                    'status'                   => $status,
                    'notes'                    => null,
                    'created_by'               => 1,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]);

                if ($isPaid) {
                    DB::table('payments')->insert([
                        'reference'       => 'PAY-' . $mno . '-' . $period['label'],
                        'group_id'        => $gid,
                        'member_id'       => $memberId,
                        'contribution_id' => $contribId,
                        'amount'          => self::AMOUNT,
                        'method'          => 'cash',
                        'channel_ref'     => null,
                        'paid_on'         => $paidOn,
                        'notes'           => null,
                        'received_by'     => 1,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                }
            }
        }

        // Read loan interest rate from group rules (loan_interest_pct = 5% / month)
        $loanRate = (float) (DB::table('group_rules')
            ->where('group_id', $gid)
            ->where('key', 'loan_interest_pct')
            ->value('value') ?? 5);

        // ── 3. LOAN — Aline (id=17): 100,000 disbursed 2026-04-03 ───────────
        $this->insertLoan(
            memberId:  17,
            memberNo:  'KP005',
            principal: 100000,
            rate:      $loanRate,
            term:      6,
            disbursed: '2026-03-25',
            approved:  '2026-04-01',
            disburseOn:'2026-04-03',
            refSuffix: '202604',
            now:       $now,
        );

        // ── 4. LOAN — Joel (id=16): 50,000 disbursed 2026-05-04 ─────────────
        $this->insertLoan(
            memberId:  16,
            memberNo:  'KP004',
            principal: 50000,
            rate:      $loanRate,
            term:      6,
            disbursed: '2026-04-25',
            approved:  '2026-05-01',
            disburseOn:'2026-05-04',
            refSuffix: '202605',
            now:       $now,
        );

        // ── 5. RUN ARREARS ENGINE until all elapsed periods are charged ──────
        // The engine stamps one period per run; backdated data needs multiple
        // passes to catch up (max 12 — covers up to a year of back-periods).
        $arrears = app(\App\Services\ArrearsService::class);
        for ($i = 0; $i < 12; $i++) {
            $result = $arrears->run($gid);
            if (($result['fees_applied'] ?? 0) === 0) break;
        }

        $this->command->info('✓ Wiped: contributions, payments, loans, arrears, cashbook, passbook, activities');
        $this->command->info('✓ Created 32 contributions (8 members × 4 months: March–June 2026)');
        $this->command->info('  Innocent    → 0 paid (4 overdue/pending)');
        $this->command->info('  Athanase    → March + April paid');
        $this->command->info('  Jean Claude → March only paid');
        $this->command->info('  Others (5)  → all 4 months paid');
        $this->command->info("✓ Loan: Aline 100,000 RWF @ {$loanRate}%/mo flat, disbursed 2026-04-03 (not repaid)");
        $this->command->info("✓ Loan: Joel   50,000 RWF @ {$loanRate}%/mo flat, disbursed 2026-05-04 (not repaid)");
    }

    private function insertLoan(
        int    $memberId,
        string $memberNo,
        int    $principal,
        float  $rate,
        int    $term,
        string $disbursed,
        string $approved,
        string $disburseOn,
        string $refSuffix,
        string $now,
    ): void {
        // Flat rate: interest = principal × rate% (once — not multiplied by months)
        $totalInterest  = round($principal * ($rate / 100), 2);
        $totalRepayable = $principal + $totalInterest;
        $dueOn          = Carbon::parse($disburseOn)->addMonths($term)->toDateString();

        DB::table('loans')->insert([
            'group_id'          => self::GROUP_ID,
            'member_id'         => $memberId,
            'reference'         => 'KP-LN-' . $memberNo . '-' . $refSuffix,
            'principal'         => $principal,
            'interest_rate_pct' => $rate,
            'interest_model'    => 'flat',
            'term_months'       => $term,
            'purpose'           => 'Business',
            'status'            => 'disbursed',
            'requested_on'      => $disbursed,
            'approved_on'       => $approved,
            'approved_by'       => 1,
            'disbursed_on'      => $disburseOn,
            'due_on'            => $dueOn,
            'total_interest'    => $totalInterest,
            'total_repayable'   => $totalRepayable,
            'amount_repaid'     => 0,
            'outstanding'       => $principal,
            'late_fee_amount'   => 0,
            'prior_outstanding' => null,
            'consolidated_loan_ids' => null,
            'rejection_reason'  => null,
            'notes'             => null,
            'deleted_at'        => null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }
}
