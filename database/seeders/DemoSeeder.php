<?php

namespace Database\Seeders;

use App\Models\ContributionSchedule;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use App\Services\ContributionGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // --- Users ---
            $accounts = [
                ['Super Admin',  'superadmin@vsla.test', 'superadmin', 'super_admin'],
                ['Group Admin',  'groupadmin@vsla.test', 'groupadmin', 'group_admin'],
                ['Treasurer',    'treasurer@vsla.test',  'treasurer', 'treasurer'],
                ['Secretary',    'secretary@vsla.test',  'secretary', 'secretary'],
                ['Member User',  'member@vsla.test',     'member', 'member'],
            ];
            foreach ($accounts as [$name, $email, $username, $role]) {
                $u = User::updateOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'username' => $username, 'password' => 'password', 'is_active' => true]
                );
                $u->syncRoles([$role]);
            }

            // --- Members ---
            $names = [
                ['Aisha',  'Nakato'],   ['Brian',  'Okello'],   ['Cynthia','Akello'],
                ['Daniel', 'Mugisha'],  ['Esther', 'Kabugo'],   ['Frank',  'Lubega'],
                ['Grace',  'Nansubuga'],['Henry',  'Wamala'],   ['Irene',  'Apio'],
                ['John',   'Ssempala'], ['Kate',   'Namuli'],   ['Luke',   'Kato'],
            ];
            $members = collect($names)->map(function ($n, $i) {
                return Member::updateOrCreate(
                    ['member_no' => 'M-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)],
                    [
                        'first_name' => $n[0], 'last_name' => $n[1],
                        'gender'     => $i % 2 ? 'male' : 'female',
                        'phone'      => '+2567'.rand(10000000, 99999999),
                        'village'    => ['Bweyogerere', 'Nakulabye', 'Kira', 'Wakiso'][rand(0, 3)],
                        'district'   => 'Wakiso',
                        'status'     => 'active',
                        'joined_on'  => now()->subMonths(rand(1, 12))->toDateString(),
                    ]
                );
            });

            // Link the demo "member" user to the first member
            $memberUser = User::where('email', 'member@vsla.test')->first();
            $memberUser->update(['member_id' => $members->first()->id]);

            // --- Groups ---
            $g1 = Group::updateOrCreate(['code' => 'G-0001'], [
                'name' => 'Wakiso Women United',  'currency' => 'UGX',
                'village' => 'Bweyogerere', 'district' => 'Wakiso',
                'formed_on' => now()->subYear(), 'cycle_starts_on' => now()->subMonths(6),
                'cycle_ends_on' => now()->addMonths(6), 'status' => 'active',
            ]);
            $g2 = Group::updateOrCreate(['code' => 'G-0002'], [
                'name' => 'Kira Boda-Boda Savers', 'currency' => 'UGX',
                'village' => 'Kira', 'district' => 'Wakiso',
                'formed_on' => now()->subMonths(8), 'cycle_starts_on' => now()->subMonths(3),
                'cycle_ends_on' => now()->addMonths(9), 'status' => 'active',
            ]);

            foreach ([$g1, $g2] as $g) {
                if ($g->rules()->count() === 0) {
                    foreach ([
                        ['key' => 'share_value',     'label' => 'Share value',           'value' => 1000, 'type' => 'numeric', 'is_system' => true],
                        ['key' => 'max_shares',      'label' => 'Max shares per meeting','value' => 5,    'type' => 'numeric', 'is_system' => true],
                        ['key' => 'social_fund_pct', 'label' => 'Social fund %',          'value' => 10,   'type' => 'percent', 'is_system' => true],
                        ['key' => 'late_fee_pct',    'label' => 'Late fee %',             'value' => 5,    'type' => 'percent', 'is_system' => true],
                        ['key' => 'grace_days',      'label' => 'Grace days',             'value' => 2,    'type' => 'days',    'is_system' => true],
                        ['key' => 'meeting_day',     'label' => 'Meeting day',            'value' => 'Saturday', 'type' => 'string', 'is_system' => true],
                        ['key' => 'loan_max_multiplier','label' => 'Loan max × savings',  'value' => 3,    'type' => 'numeric', 'is_system' => true],
                        ['key' => 'loan_interest_pct','label' => 'Loan interest % / mo',  'value' => 5,    'type' => 'percent', 'is_system' => true],
                        ['key' => 'attendance_late_fine','label' => 'Attendance late fine','value' => 500,  'type' => 'numeric', 'is_system' => true, 'description' => 'Money fine charged to a member who arrives late on contribution day.'],
                        ['key' => 'attendance_absent_fine','label' => 'Attendance absent fine','value' => 2000,'type' => 'numeric', 'is_system' => true, 'description' => 'Money fine charged to a member who misses contribution day.'],
                    ] as $r) $g->rules()->create($r);
                }

                // Backfill the attendance fine rules even on groups that
                // already had rules from a previous seed run.
                foreach ([
                    ['key' => 'attendance_late_fine',   'label' => 'Attendance late fine',   'value' => 500,  'type' => 'numeric', 'is_system' => true, 'description' => 'Money fine charged to a member who arrives late on contribution day.'],
                    ['key' => 'attendance_absent_fine', 'label' => 'Attendance absent fine', 'value' => 2000, 'type' => 'numeric', 'is_system' => true, 'description' => 'Money fine charged to a member who misses contribution day.'],
                ] as $r) {
                    $g->rules()->firstOrCreate(['key' => $r['key']], $r);
                }
            }

            // --- Staff assignments: which user accounts manage which groups ---
            // Group Admin manages BOTH groups so we can demo the multi-group switcher
            // Treasurer & Secretary manage just the first group
            $groupAdmin = User::where('email', 'groupadmin@vsla.test')->first();
            $treasurer  = User::where('email', 'treasurer@vsla.test')->first();
            $secretary  = User::where('email', 'secretary@vsla.test')->first();

            $g1->staffUsers()->syncWithoutDetaching([
                $groupAdmin->id => ['role_in_group' => 'group_admin'],
                $treasurer->id  => ['role_in_group' => 'treasurer'],
                $secretary->id  => ['role_in_group' => 'secretary'],
            ]);
            $g2->staffUsers()->syncWithoutDetaching([
                $groupAdmin->id => ['role_in_group' => 'group_admin'],
            ]);

            // Many-to-many: distribute members across both groups (deliberately overlap some)
            $g1Members = $members->slice(0, 8);
            $g2Members = $members->slice(4, 8); // overlap 4..7

            $sync1 = $g1Members->mapWithKeys(fn ($m, $i) => [$m->id => [
                'position'   => match ($i) { 0 => 'chairperson', 1 => 'secretary', 2 => 'treasurer', default => 'member' },
                'joined_at'  => $m->joined_on, 'is_active' => true, 'share_count' => rand(0, 10),
            ]])->all();
            $g1->members()->sync($sync1);

            $sync2 = $g2Members->mapWithKeys(fn ($m, $i) => [$m->id => [
                'position'   => match ($i) { 0 => 'chairperson', 1 => 'secretary', 2 => 'treasurer', default => 'member' },
                'joined_at'  => $m->joined_on, 'is_active' => true, 'share_count' => rand(0, 10),
            ]])->all();
            $g2->members()->sync($sync2);

            // --- Schedules ---
            ContributionSchedule::updateOrCreate(
                ['group_id' => $g1->id, 'name' => 'Weekly Savings'],
                ['type' => 'savings', 'frequency' => 'weekly', 'amount' => 5000,
                 'start_date' => Carbon::now()->subWeeks(8)->startOfWeek(),
                 'next_due_on' => Carbon::now()->subWeeks(8)->startOfWeek(),
                 'grace_days' => 2, 'late_fee_pct' => 5, 'late_fee_flat' => 0, 'is_active' => true]
            );
            ContributionSchedule::updateOrCreate(
                ['group_id' => $g1->id, 'name' => 'Social Fund'],
                ['type' => 'social_fund', 'frequency' => 'weekly', 'amount' => 1000,
                 'start_date' => Carbon::now()->subWeeks(8)->startOfWeek(),
                 'next_due_on' => Carbon::now()->subWeeks(8)->startOfWeek(),
                 'grace_days' => 2, 'late_fee_pct' => 0, 'late_fee_flat' => 500, 'is_active' => true]
            );
            ContributionSchedule::updateOrCreate(
                ['group_id' => $g2->id, 'name' => 'Monthly Savings'],
                ['type' => 'savings', 'frequency' => 'monthly', 'amount' => 20000,
                 'start_date' => Carbon::now()->subMonths(3)->startOfMonth(),
                 'next_due_on' => Carbon::now()->subMonths(3)->startOfMonth(),
                 'grace_days' => 3, 'late_fee_pct' => 3, 'late_fee_flat' => 0, 'is_active' => true]
            );

            // Generate contributions up to today
            app(ContributionGeneratorService::class)->run();

            // --- Demo loans ---
            $svc       = app(\App\Services\LoanService::class);
            $approver  = $groupAdmin;

            $loanA = \App\Models\Loan::updateOrCreate(
                ['reference' => 'L-00001'],
                [
                    'group_id'          => $g1->id,
                    'member_id'         => $g1Members->first()->id,
                    'principal'         => 50000,
                    'interest_rate_pct' => 5,
                    'term_months'       => 3,
                    'purpose'           => 'School fees for the new term',
                    'status'            => 'requested',
                    'requested_on'      => now()->subDays(20)->toDateString(),
                ]
            );
            $svc->approve($loanA, $approver->id);
            $svc->disburse($loanA, now()->subDays(15)->toDateString());
            $svc->recordRepayment($loanA, [
                'amount'  => 20000,
                'paid_on' => now()->subDays(7)->toDateString(),
                'method'  => 'mobile_money',
            ], $approver->id);

            \App\Models\Loan::updateOrCreate(
                ['reference' => 'L-00002'],
                [
                    'group_id'          => $g1->id,
                    'member_id'         => $g1Members->skip(2)->first()->id,
                    'principal'         => 100000,
                    'interest_rate_pct' => 5,
                    'term_months'       => 6,
                    'purpose'           => 'Restock the kiosk',
                    'status'            => 'requested',
                    'requested_on'      => now()->subDays(2)->toDateString(),
                ]
            );
        });
    }
}
