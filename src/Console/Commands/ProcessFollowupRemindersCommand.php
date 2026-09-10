<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Console\Commands;

use Illuminate\Console\Command;
use Karnoweb\Crm\Services\FollowupService;

final class ProcessFollowupRemindersCommand extends Command
{
    protected $signature = 'crm:process-followup-reminders';

    protected $description = 'Publish FollowupDue for due, unnotified followups.';

    public function handle(FollowupService $followups): int
    {
        $published = $followups->processDue();
        $this->info("Published {$published} followup reminders.");

        return self::SUCCESS;
    }
}
