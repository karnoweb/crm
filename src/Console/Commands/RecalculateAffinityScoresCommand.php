<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Console\Commands;

use Illuminate\Console\Command;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Services\AffinityScoringService;

final class RecalculateAffinityScoresCommand extends Command
{
    protected $signature = 'crm:recalculate-affinity';

    protected $description = 'Rebuild Interest and Lead projection fields from Interaction rows.';

    public function handle(AffinityScoringService $scoring): int
    {
        $count = 0;

        Lead::query()->orderBy('id')->each(function (Lead $lead) use ($scoring, &$count): void {
            $scoring->recalculateLead($lead);
            $count++;
        });

        $this->info("Recalculated affinity for {$count} leads.");

        return self::SUCCESS;
    }
}
