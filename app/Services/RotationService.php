<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\Group;
use App\Models\Member;
use App\Models\Rotation;
use App\Models\RotationMember;
use App\Models\RotationPayout;
use App\Models\RotationTurn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RotationService
 * ---------------
 * Encapsulates the rotation / merry-go-round payout business logic:
 *   - creating a rotation with its ordered recipient list
 *   - generating the next scheduled turn on the right cadence
 *   - computing how much a turn should disburse, given the rule
 *   - executing a turn (creates payouts + cashbook expense, advances state)
 *   - skipping or cancelling
 *
 * Cash-on-hand is read from TreasuryService so the "available fund" is always
 * the same number shown on the treasury page.
 */
class RotationService
{
    public function __construct(protected TreasuryService $treasury) {}

    /** Create the rotation + recipient pivot rows, and the very first turn. */
    public function create(array $data, array $memberIds, int $userId): Rotation
    {
        return DB::transaction(function () use ($data, $memberIds, $userId) {
            $startsOn = Carbon::parse($data['starts_on'])->startOfDay();

            $rotation = Rotation::create(array_merge($data, [
                'created_by'   => $userId,
                'next_turn_on' => $startsOn->toDateString(),
                'status'       => 'active',
            ]));

            foreach (array_values($memberIds) as $i => $memberId) {
                RotationMember::create([
                    'rotation_id' => $rotation->id,
                    'member_id'   => (int) $memberId,
                    'position'    => $i + 1,
                ]);
            }

            // Seed the first turn.
            $this->createNextTurn($rotation, $startsOn);

            return $rotation->fresh(['members.member', 'turns']);
        });
    }

    /**
     * Replace the recipient list on an existing rotation. Only allowed while
     * no turns have been executed, otherwise positions wouldn't make sense.
     */
    public function syncMembers(Rotation $rotation, array $memberIds): void
    {
        if ($rotation->turns()->where('status', 'paid')->exists()) {
            abort(422, 'Members cannot be reordered after the first turn has been paid.');
        }

        DB::transaction(function () use ($rotation, $memberIds) {
            $rotation->members()->delete();
            foreach (array_values($memberIds) as $i => $memberId) {
                RotationMember::create([
                    'rotation_id' => $rotation->id,
                    'member_id'   => (int) $memberId,
                    'position'    => $i + 1,
                ]);
            }
        });
    }

    /**
     * Compute the disbursement amount that *would* be paid if this turn ran
     * right now, based on the rule and current cash on hand.
     */
    public function plannedAmount(Rotation $rotation): float
    {
        $cash = (float) $this->treasury->groupSummary((int) $rotation->group_id, null)['cash_on_hand'];

        return match ($rotation->disbursement_method) {
            'full'       => max(0, round($cash, 2)),
            'percentage' => max(0, round($cash * ((float) $rotation->disbursement_pct / 100), 2)),
            'fixed'      => max(0, round((float) $rotation->disbursement_amount, 2)),
            default      => 0,
        };
    }

    /**
     * The next R recipients in line for a rotation.
     * Order: members with the lowest received_count first, then by position.
     */
    public function nextRecipients(Rotation $rotation, ?int $count = null): \Illuminate\Support\Collection
    {
        $count = $count ?? (int) $rotation->recipients_per_turn;

        return $rotation->members()
            ->with('member:id,full_name,member_no')
            ->orderBy('received_count')
            ->orderBy('position')
            ->limit($count)
            ->get();
    }

    /**
     * Execute a scheduled turn:
     *   1. Pick the next R recipients.
     *   2. Compute the disbursement total (overrideable).
     *   3. Split it equally among recipients.
     *   4. Write a CashbookEntry expense for each payout (so it shows up in
     *      treasury cash flow, dashboard "Expenses (mo)", etc.).
     *   5. Create RotationPayout rows linked to the cashbook entries.
     *   6. Bump received_count on each recipient + last_received_on.
     *   7. Mark the turn paid, then either generate the next turn or mark
     *      the whole rotation completed.
     */
    public function executeTurn(
        RotationTurn $turn,
        int $userId,
        ?float $totalOverride = null,
        ?string $paidOn = null,
        string $method = 'cash',
        ?string $notes = null,
    ): RotationTurn {
        if ($turn->status !== 'scheduled') {
            abort(422, 'Only scheduled turns can be executed.');
        }

        $rotation = $turn->rotation;
        if ($rotation->status !== 'active') {
            abort(422, 'This rotation is no longer active.');
        }

        $recipients = $this->nextRecipients($rotation);
        if ($recipients->isEmpty()) {
            abort(422, 'No eligible recipients remain for this rotation.');
        }

        $total = $totalOverride !== null
            ? round((float) $totalOverride, 2)
            : $this->plannedAmount($rotation);

        if ($total <= 0) {
            abort(422, 'Disbursement amount must be greater than zero.');
        }

        $perMember = round($total / $recipients->count(), 2);
        $paidOn    = $paidOn ?: now()->toDateString();

        return DB::transaction(function () use ($turn, $rotation, $recipients, $total, $perMember, $paidOn, $method, $notes, $userId) {
            $refPrefix = 'RX-'.now()->format('Ymd').'-';
            // Find the highest existing RX reference for today (across active
            // AND soft-deleted rows, since the UNIQUE index covers both) so
            // the new references don't collide.
            $lastRef = CashbookEntry::withTrashed()
                ->where('reference', 'like', $refPrefix.'%')
                ->orderByDesc('reference')
                ->value('reference');
            $nextSeq = $lastRef ? ((int) substr($lastRef, -5)) + 1 : 1;

            foreach ($recipients as $rm) {
                // Rotation payouts leave the group's cash on hand, so they
                // become a cashbook expense entry. That keeps the treasury
                // numbers consistent — no double accounting.
                $cb = CashbookEntry::create([
                    'reference'   => $refPrefix.str_pad((string) $nextSeq++, 5, '0', STR_PAD_LEFT),
                    'group_id'    => $rotation->group_id,
                    'type'        => 'expense',
                    'category'    => 'rotation_payout',
                    'amount'      => $perMember,
                    'method'      => $method,
                    'counterparty'=> $rm->member?->full_name,
                    'occurred_on' => $paidOn,
                    'notes'       => "Rotation '{$rotation->name}' · turn #{$turn->sequence_no}",
                    'recorded_by' => $userId,
                ]);

                RotationPayout::create([
                    'rotation_turn_id'  => $turn->id,
                    'rotation_id'       => $rotation->id,
                    'group_id'          => $rotation->group_id,
                    'member_id'         => $rm->member_id,
                    'amount'            => $perMember,
                    'paid_on'           => $paidOn,
                    'method'            => $method,
                    'reference'         => $cb->reference,
                    'cashbook_entry_id' => $cb->id,
                    'recorded_by'       => $userId,
                    'notes'             => $notes,
                ]);

                $rm->update([
                    'received_count'   => $rm->received_count + 1,
                    'last_received_on' => $paidOn,
                ]);
            }

            $turn->update([
                'status'             => 'paid',
                'disbursement_total' => $total,
                'executed_on'        => $paidOn,
                'executed_by'        => $userId,
                'notes'              => $notes,
            ]);

            // Have all members received at least one payout?
            $remaining = $rotation->members()->where('received_count', 0)->count();

            if ($remaining === 0) {
                // One full cycle complete — close the rotation.
                $rotation->update([
                    'status'       => 'completed',
                    'next_turn_on' => null,
                ]);
            } else {
                $this->createNextTurn($rotation, Carbon::parse($turn->scheduled_on));
            }

            return $turn->fresh(['payouts.member', 'rotation']);
        });
    }

    public function skipTurn(RotationTurn $turn, int $userId, ?string $reason = null): RotationTurn
    {
        if ($turn->status !== 'scheduled') {
            abort(422, 'Only scheduled turns can be skipped.');
        }

        return DB::transaction(function () use ($turn, $userId, $reason) {
            $turn->update([
                'status'      => 'skipped',
                'executed_on' => now()->toDateString(),
                'executed_by' => $userId,
                'notes'       => $reason,
            ]);

            $rotation = $turn->rotation;
            if ($rotation->status === 'active') {
                $this->createNextTurn($rotation, Carbon::parse($turn->scheduled_on));
            }

            return $turn->fresh();
        });
    }

    public function cancel(Rotation $rotation, ?string $reason = null): void
    {
        DB::transaction(function () use ($rotation, $reason) {
            $rotation->turns()->where('status', 'scheduled')->update(['status' => 'skipped', 'notes' => $reason]);
            $rotation->update(['status' => 'cancelled', 'next_turn_on' => null]);
        });
    }

    /** Add the next scheduled turn after $afterDate, advancing by frequency. */
    protected function createNextTurn(Rotation $rotation, Carbon $afterDate): RotationTurn
    {
        $nextDate = $this->advance(clone $afterDate, $rotation->frequency);
        $nextSeq  = (int) $rotation->turns()->max('sequence_no') + 1;

        // The very first turn lands on the start date itself.
        $isFirst = $rotation->turns()->count() === 0;
        $scheduled = $isFirst ? $afterDate->toDateString() : $nextDate->toDateString();

        $rotation->update(['next_turn_on' => $scheduled]);

        return RotationTurn::create([
            'rotation_id'  => $rotation->id,
            'sequence_no'  => $nextSeq,
            'scheduled_on' => $scheduled,
            'status'       => 'scheduled',
        ]);
    }

    protected function advance(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'   => $date->addDay(),
            'weekly'  => $date->addWeek(),
            'monthly' => $date->addMonthNoOverflow(),
            default   => $date->addWeek(),
        };
    }
}
