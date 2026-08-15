<?php

namespace App\Console\Commands;

use App\Services\PassbookService;
use Illuminate\Console\Command;

class RebuildPassbooks extends Command
{
    protected $signature = 'vsla:rebuild-passbooks {--group= : Restrict to one group ID}';
    protected $description = 'Recompute running balances for passbook entries (idempotent reconciliation).';

    public function handle(PassbookService $svc): int
    {
        $groupId = $this->option('group') ? (int) $this->option('group') : null;

        $r = $svc->rebuild($groupId);

        $this->info(sprintf('Passbooks rebuilt for %d members across %d groups.', $r['members'], $r['groups']));
        return self::SUCCESS;
    }
}
