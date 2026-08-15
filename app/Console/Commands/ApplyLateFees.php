<?php

namespace App\Console\Commands;

use App\Services\ArrearsService;
use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature = 'vsla:apply-late-fees {--group= : Restrict to one group ID}';
    protected $description = 'Mark overdue contributions, apply the configured late fee, create/update Arrear rows, and apply late fees to overdue flat loans.';

    public function handle(ArrearsService $svc): int
    {
        $groupId = $this->option('group') ? (int) $this->option('group') : null;

        $r = $svc->run($groupId);
        $this->info(sprintf(
            'Contributions — Evaluated: %d  |  Newly overdue: %d  |  Late fees applied: %d  |  Arrears opened: %d  |  Arrears updated: %d',
            $r['evaluated'], $r['newly_overdue'], $r['fees_applied'], $r['arrears_opened'], $r['arrears_updated']
        ));

        $l = $svc->runLoanLateFees($groupId);
        $this->info(sprintf(
            'Loans        — Evaluated: %d  |  Late fees applied: %d',
            $l['evaluated'], $l['fees_applied']
        ));

        return self::SUCCESS;
    }
}
