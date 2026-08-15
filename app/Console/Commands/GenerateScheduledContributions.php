<?php

namespace App\Console\Commands;

use App\Services\ContributionGeneratorService;
use Illuminate\Console\Command;

class GenerateScheduledContributions extends Command
{
    protected $signature = 'vsla:generate-contributions {--group= : Restrict to one group ID}';
    protected $description = 'Generate the next due Contribution rows for every active schedule whose next-due date is on or before today.';

    public function handle(ContributionGeneratorService $svc): int
    {
        $groupId = $this->option('group') ? (int) $this->option('group') : null;

        $result = $svc->run($groupId);

        $this->info(sprintf(
            'Schedules processed: %d  |  Contributions created: %d  |  Periods advanced: %d',
            $result['schedules'], $result['created'], $result['advanced']
        ));

        return self::SUCCESS;
    }
}
