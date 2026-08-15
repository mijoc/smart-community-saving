<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Console\Command;

class AccrueLoanInterest extends Command
{
    protected $signature = 'vsla:accrue-loan-interest
                            {--group= : Restrict to one group ID}
                            {--period= : Month to accrue for, YYYY-MM format (default: current month)}';

    protected $description = 'Add monthly compound interest to every active compound-model loan.';

    public function handle(LoanService $svc): int
    {
        $groupId = $this->option('group') ? (int) $this->option('group') : null;

        $periodOption = $this->option('period');
        $period = $periodOption
            ? \Carbon\Carbon::createFromFormat('Y-m', $periodOption)->startOfMonth()
            : now()->startOfMonth();

        $query = Loan::query()
            ->where('interest_model', 'compound')
            ->whereIn('status', ['disbursed', 'repaying']);

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $loans     = $query->get();
        $processed = 0;
        $skipped   = 0;

        foreach ($loans as $loan) {
            $accrual = $svc->accrueMonthlyInterest($loan, $period->copy());
            if ($accrual) {
                $processed++;
                $this->line(sprintf(
                    '  Loan %s: %.2f × %.3f%% = +%.2f → outstanding %.2f',
                    $loan->reference,
                    $accrual->balance_before,
                    $accrual->rate_pct,
                    $accrual->interest_amount,
                    $accrual->balance_after
                ));
            } else {
                $skipped++;
            }
        }

        $this->info(sprintf(
            'Done. Accrued: %d  |  Already done (skipped): %d  |  Period: %s',
            $processed, $skipped, $period->format('Y-m')
        ));

        return self::SUCCESS;
    }
}
