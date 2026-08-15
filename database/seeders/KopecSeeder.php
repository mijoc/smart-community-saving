<?php

namespace Database\Seeders;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\Member;
use App\Models\PassbookEntry;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * KOPEC seeder
 * ------------
 * Creates the KOPEC group with its 8 members and seeds one share of
 * 50,000 (savings) per member per month from July 2024 through the
 * current month. Idempotent — safe to re-run; deterministic payment
 * references prevent duplication.
 */
class KopecSeeder extends Seeder
{
    public const SHARE_VALUE = 50000;
    public const CYCLE_START = '2024-07-01';

    public function run(): void
    {
        DB::transaction(function () {
            $group = $this->ensureGroup();
            $members = $this->ensureMembers($group);
            $this->seedMonthlyShares($group, $members);
            $this->rebuildPassbookBalances($group->id);
        });
    }

    protected function ensureGroup(): Group
    {
        return Group::firstOrCreate(
            ['code' => 'KOPEC'],
            [
                'name'            => 'KOPEC',
                'description'     => 'KOPEC savings & loan cooperative',
                'currency'        => 'RWF',
                'formed_on'       => self::CYCLE_START,
                'cycle_starts_on' => self::CYCLE_START,
                'cycle_ends_on'   => Carbon::parse(self::CYCLE_START)->addYear()->subDay()->toDateString(),
                'status'          => 'active',
                'country'         => 'Rwanda',
            ],
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Member>
     */
    protected function ensureMembers(Group $group): \Illuminate\Support\Collection
    {
        $roster = [
            ['no' => 'KP001', 'first' => 'Innocent',    'last' => 'Munyantore',     'gender' => 'male'],
            ['no' => 'KP002', 'first' => 'Athanase',    'last' => 'HABYARIMANA',    'gender' => 'male'],
            ['no' => 'KP003', 'first' => 'Jean Claude', 'last' => 'Ikitegetse',     'gender' => 'male'],
            ['no' => 'KP004', 'first' => 'Joel',        'last' => 'Uwamungu',       'gender' => 'male'],
            ['no' => 'KP005', 'first' => 'Aline',       'last' => 'UWAMWEZI',       'gender' => 'female'],
            ['no' => 'KP006', 'first' => 'Emmanuel',    'last' => '',               'gender' => 'male'],
            ['no' => 'KP007', 'first' => 'Jean Paul',   'last' => '',               'gender' => 'male'],
            ['no' => 'KP008', 'first' => 'Salvator',    'last' => '',               'gender' => 'male'],
        ];

        $members = collect();
        foreach ($roster as $r) {
            $fullName = trim($r['first'].' '.$r['last']);
            // last_name is NOT NULL — fall back to first_name for mononyms.
            $last = $r['last'] !== '' ? $r['last'] : $r['first'];
            $member = Member::firstOrCreate(
                ['member_no' => $r['no']],
                [
                    'first_name' => $r['first'],
                    'last_name'  => $last,
                    'full_name'  => $fullName,
                    'gender'     => $r['gender'],
                    'status'     => 'active',
                    'joined_on'  => self::CYCLE_START,
                ],
            );

            // The Member model rewrites full_name to "first last" on every
            // save; for mononyms we patch full_name back via raw DB write.
            if ($r['last'] === '' && $member->full_name !== $r['first']) {
                DB::table('members')->where('id', $member->id)->update([
                    'full_name' => $r['first'],
                ]);
                $member->refresh();
            }

            // Attach to KOPEC with one share, idempotent.
            $group->members()->syncWithoutDetaching([
                $member->id => [
                    'position'    => 'member',
                    'joined_at'   => self::CYCLE_START,
                    'share_count' => 1,
                    'is_active'   => true,
                ],
            ]);

            $members->push($member);
        }

        return $members;
    }

    /**
     * One savings payment of SHARE_VALUE per member per month, from
     * July 2024 through the current month. Reference is deterministic
     * so re-running the seeder doesn't create duplicates.
     */
    protected function seedMonthlyShares(Group $group, \Illuminate\Support\Collection $members): void
    {
        $cursor = Carbon::parse(self::CYCLE_START)->startOfMonth();
        $end    = now()->startOfMonth();

        while ($cursor->lte($end)) {
            $paidOn = $cursor->copy()->day(min(15, $cursor->daysInMonth))->toDateString();

            $periodStart = $cursor->copy()->startOfMonth()->toDateString();
            $periodEnd   = $cursor->copy()->endOfMonth()->toDateString();
            $dueOn       = $cursor->copy()->endOfMonth()->toDateString();

            foreach ($members as $member) {
                $reference = sprintf('KP-SAV-%s-%s', $member->member_no, $cursor->format('Ym'));

                // Each monthly share has a matching "paid" contribution row
                // so the Contributions page reflects the payment history.
                $contribution = Contribution::firstOrCreate(
                    [
                        'group_id'                 => $group->id,
                        'member_id'                => $member->id,
                        'contribution_schedule_id' => null,
                        'period_start'             => $periodStart,
                        'type'                     => 'savings',
                    ],
                    [
                        'period_end'      => $periodEnd,
                        'due_on'          => $dueOn,
                        'paid_on'         => $paidOn,
                        'expected_amount' => self::SHARE_VALUE,
                        'paid_amount'     => self::SHARE_VALUE,
                        'late_fee_amount' => 0,
                        'status'          => 'paid',
                        'notes'           => 'Monthly share — '.$cursor->format('F Y'),
                    ],
                );

                $payment = Payment::firstOrCreate(
                    ['reference' => $reference],
                    [
                        'group_id'        => $group->id,
                        'member_id'       => $member->id,
                        'contribution_id' => $contribution->id,
                        'amount'          => self::SHARE_VALUE,
                        'method'          => 'cash',
                        'paid_on'         => $paidOn,
                        'notes'           => 'Monthly share — '.$cursor->format('F Y'),
                    ],
                );

                // Back-fill the link if the payment was created in an
                // earlier seeder run (when contributions weren't seeded yet).
                if (! $payment->contribution_id) {
                    $payment->contribution_id = $contribution->id;
                    $payment->save();
                }

                // Mirror into the passbook (savings credit). Skip if a
                // matching entry already exists for that payment.
                PassbookEntry::firstOrCreate(
                    [
                        'source_type' => Payment::class,
                        'source_id'   => $payment->id,
                    ],
                    [
                        'group_id'    => $group->id,
                        'member_id'   => $member->id,
                        'entry_date'  => $paidOn,
                        'description' => 'Monthly share — '.$cursor->format('F Y'),
                        'category'    => 'savings',
                        'debit'       => 0,
                        'credit'      => self::SHARE_VALUE,
                        'balance'     => 0, // recalculated below
                    ],
                );
            }

            $cursor->addMonth();
        }
    }

    /**
     * Rebuild the running per-member balance column on passbook_entries
     * for KOPEC, ordered by date then id.
     */
    protected function rebuildPassbookBalances(int $groupId): void
    {
        $memberIds = PassbookEntry::where('group_id', $groupId)
            ->distinct()
            ->pluck('member_id');

        foreach ($memberIds as $mid) {
            $running = 0.0;
            $entries = PassbookEntry::where('group_id', $groupId)
                ->where('member_id', $mid)
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get();

            foreach ($entries as $e) {
                $running += (float) $e->credit - (float) $e->debit;
                $e->balance = $running;
                $e->save();
            }
        }
    }
}
