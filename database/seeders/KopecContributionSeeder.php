<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KopecContributionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::table('arrears')->where('group_id', 4)->delete();
        DB::table('payments')->where('group_id', 4)->delete();
        DB::table('contributions')->where('group_id', 4)->delete();

        $groupId    = 4;
        $scheduleId = 4;
        $amount     = 50000;
        $graceDays  = 2;
        $lateFeePct = 5;
        $now        = now()->toDateTimeString();
        $lateFee    = (int) round($amount * $lateFeePct / 100); // 2 500

        // member_id => member_no
        $members = [
            13 => 'KP001', // Innocent Munyantore
            14 => 'KP002', // Athanase HABYARIMANA
            15 => 'KP003', // Jean Claude Ikitegetse
            16 => 'KP004', // Joel Uwamungu
            17 => 'KP005', // Aline UWAMWEZI
            18 => 'KP006', // Emmanuel
            19 => 'KP007', // Jean Paul
            20 => 'KP008', // Salvator
        ];

        // Exceptions by member_id → [Y-m => special]
        //   'overdue'     = not paid, fully overdue
        //   'partial:N'   = N RWF paid, rest outstanding (arrears)
        $exceptions = [
            13 => ['2026-03' => 'overdue', '2026-04' => 'overdue', '2026-05' => 'overdue'],
            14 => ['2026-04' => 'partial:13000', '2026-05' => 'overdue'],
            18 => ['2026-05' => 'overdue'],
        ];

        $cursor = Carbon::create(2024, 7, 1);
        $last   = Carbon::create(2026, 6, 1);

        while ($cursor->lte($last)) {
            $ym          = $cursor->format('Y-m');
            $periodStart = $cursor->copy()->startOfMonth()->toDateString();
            $periodEnd   = $cursor->copy()->endOfMonth()->toDateString();
            $dueOn       = $cursor->copy()->endOfMonth()->addDays($graceDays)->toDateString();
            $notYetDue   = Carbon::parse($dueOn)->isFuture();

            foreach ($members as $memberId => $memberNo) {
                $special = $exceptions[$memberId][$ym] ?? null;

                if ($notYetDue && $special === null) {
                    $status     = 'pending';
                    $paidAmount = 0;
                    $fee        = 0;
                    $paidOn     = null;
                } elseif ($special === 'overdue') {
                    $status     = 'overdue';
                    $paidAmount = 0;
                    $fee        = $lateFee;
                    $paidOn     = null;
                } elseif ($special && str_starts_with($special, 'partial:')) {
                    $paidAmount = (int) substr($special, 8);
                    $status     = 'partial';
                    $fee        = 0;
                    $paidOn     = $cursor->copy()->day(10)->toDateString();
                } else {
                    $status     = 'paid';
                    $paidAmount = $amount;
                    $fee        = 0;
                    $paidOn     = $cursor->copy()->day(15)->toDateString();
                }

                $contribId = DB::table('contributions')->insertGetId([
                    'group_id'                => $groupId,
                    'member_id'               => $memberId,
                    'contribution_schedule_id'=> $scheduleId,
                    'type'                    => 'savings',
                    'expected_amount'         => $amount,
                    'paid_amount'             => $paidAmount,
                    'late_fee_amount'         => $fee,
                    'status'                  => $status,
                    'period_start'            => $periodStart,
                    'period_end'              => $periodEnd,
                    'due_on'                  => $dueOn,
                    'paid_on'                 => $paidOn,
                    'notes'                   => null,
                    'created_by'              => 1,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]);

                // Payment record
                if ($paidAmount > 0) {
                    DB::table('payments')->insert([
                        'reference'       => "KP-PAY-{$memberNo}-{$cursor->format('Ym')}",
                        'group_id'        => $groupId,
                        'member_id'       => $memberId,
                        'contribution_id' => $contribId,
                        'amount'          => $paidAmount,
                        'method'          => 'cash',
                        'channel_ref'     => null,
                        'paid_on'         => $paidOn,
                        'notes'           => null,
                        'received_by'     => 1,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                }

                // Arrears for overdue / partial
                if ($status === 'overdue' || $status === 'partial') {
                    $outstanding = $status === 'partial'
                        ? ($amount - $paidAmount)  // 37 000 for Athanase April
                        : ($amount + $fee);         // 52 500 for full overdue

                    $daysOverdue = max(0, (int) Carbon::parse($dueOn)->diffInDays(now()));

                    DB::table('arrears')->insert([
                        'group_id'          => $groupId,
                        'member_id'         => $memberId,
                        'contribution_id'   => $contribId,
                        'outstanding_amount'=> $outstanding,
                        'late_fee_applied'  => $fee,
                        'days_overdue'      => $daysOverdue,
                        'first_overdue_on'  => $dueOn,
                        'last_evaluated_on' => now()->toDateString(),
                        'status'            => 'open',
                        'notes'             => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                }
            }

            $cursor->addMonth();
        }

        DB::statement('PRAGMA foreign_keys = ON');

        echo "Done!\n";
        echo "Contributions : " . DB::table('contributions')->where('group_id', 4)->count() . "\n";
        echo "Payments      : " . DB::table('payments')->where('group_id', 4)->count() . "\n";
        echo "Arrears       : " . DB::table('arrears')->where('group_id', 4)->count() . "\n";
        echo "\nStatus breakdown:\n";
        DB::table('contributions')->where('group_id', 4)
            ->selectRaw('status, COUNT(*) as cnt, SUM(paid_amount) as total_paid')
            ->groupBy('status')->orderBy('status')->get()
            ->each(fn($r) => print("  {$r->status}: {$r->cnt} rows, paid=" . number_format($r->total_paid) . " RWF\n"));
        echo "\nArrears by member:\n";
        DB::table('arrears as a')
            ->join('members as m','m.id','a.member_id')
            ->where('a.group_id', 4)
            ->selectRaw('m.full_name, COUNT(*) as cnt, SUM(a.outstanding_amount) as total_out')
            ->groupBy('m.full_name')->get()
            ->each(fn($r) => print("  {$r->full_name}: {$r->cnt} arrear(s), outstanding=" . number_format($r->total_out) . " RWF\n"));
    }
}
