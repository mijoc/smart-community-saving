<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FlagDefaultedLoans extends Command
{
    protected $signature = 'vsla:flag-defaulted-loans
                            {--group=     : Restrict to one group ID}
                            {--days=      : Override the default-after-N-days threshold}
                            {--dry-run    : List loans that would be flagged without changing anything}';

    protected $description = 'Flag overdue loans as defaulted and notify group admins via the activity feed.';

    public function handle(): int
    {
        $defaultDays   = (int) ($this->option('days') ?: config('vsla.loan_default_days', 90));
        $groupId       = $this->option('group') ? (int) $this->option('group') : null;
        $dryRun        = $this->option('dry-run');
        $cutoff        = now()->subDays($defaultDays)->toDateString();

        if ($dryRun) {
            $this->warn("DRY RUN — no changes will be saved. Default threshold: {$defaultDays} days (cutoff: {$cutoff}).");
        } else {
            $this->info("Flagging defaulted loans (threshold: {$defaultDays} days, cutoff: {$cutoff}).");
        }

        $query = Loan::with(['member:id,full_name,member_no', 'group:id,name'])
            ->whereIn('status', ['disbursed', 'repaying']);

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $flagged = 0;

        foreach ($query->cursor() as $loan) {
            if (! $this->isDefaulted($loan, $cutoff)) {
                continue;
            }

            $this->line(sprintf(
                '  %s  %-8s  %-30s  outstanding: %s',
                $loan->reference,
                $loan->status,
                $loan->member?->full_name ?? '?',
                number_format((float) $loan->outstanding, 2)
            ));

            if (! $dryRun) {
                $loan->update([
                    'status' => 'defaulted',
                    'notes'  => trim(($loan->notes ? $loan->notes . ' | ' : '')
                        . 'Auto-flagged as defaulted on ' . now()->toDateString()
                        . " (no payment / overdue >{$defaultDays} days)"),
                ]);

                ActivityLogger::log(
                    groupId: $loan->group_id,
                    type: 'loan.defaulted',
                    description: "Loan {$loan->reference} ({$loan->member?->full_name}) auto-flagged as defaulted — overdue by more than {$defaultDays} days",
                    subject: $loan,
                    icon: 'alert-triangle',
                    color: 'red',
                    data: [
                        'reference'   => $loan->reference,
                        'member'      => $loan->member?->full_name,
                        'outstanding' => number_format((float) $loan->outstanding, 2),
                        'threshold'   => "{$defaultDays} days",
                    ],
                );
            }

            $flagged++;
        }

        $label = $dryRun ? 'Would flag' : 'Flagged';
        $this->info("{$label}: {$flagged} loan(s) as defaulted.");

        return self::SUCCESS;
    }

    /**
     * Determine whether a loan qualifies as defaulted.
     *
     * Flat loans   → due_on is set; flag when due_on < cutoff.
     * Compound     → no fixed due_on; flag when the most recent approved repayment
     *                (or, if none, the disbursement date) is older than the cutoff.
     */
    private function isDefaulted(Loan $loan, string $cutoff): bool
    {
        if (! $loan->isCompound()) {
            return $loan->due_on && $loan->due_on->toDateString() < $cutoff;
        }

        // Compound: look at the date of the last payment activity
        $lastActivity = $loan->approvedRepayments()
            ->orderByDesc('paid_on')
            ->value('paid_on');

        $referenceDate = $lastActivity
            ? Carbon::parse($lastActivity)->toDateString()
            : ($loan->disbursed_on ? $loan->disbursed_on->toDateString() : null);

        return $referenceDate && $referenceDate < $cutoff;
    }
}
