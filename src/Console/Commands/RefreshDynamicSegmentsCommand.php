<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Events\LeadSegmentChanged;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Services\SegmentEvaluationService;
use Karnoweb\Crm\Support\CrmEventDispatcher;

final class RefreshDynamicSegmentsCommand extends Command
{
    protected $signature = 'crm:refresh-dynamic-segments';

    protected $description = 'Re-evaluate dynamic segments and publish LeadSegmentChanged for membership diffs.';

    public function handle(SegmentEvaluationService $evaluation): int
    {
        $segments = Segment::query()->where('is_dynamic', true)->with('rules')->orderBy('id')->get();

        foreach ($segments as $segment) {
            $ids = $evaluation->evaluate($segment)->map(fn ($id) => (int) $id)->values();

            DB::transaction(function () use ($segment, $ids): void {
                $previous = collect($segment->last_match_ids ?? [])->map(fn ($id) => (int) $id);
                $added = $ids->diff($previous);
                $removed = $previous->diff($ids);

                foreach ($added as $leadId) {
                    CrmEventDispatcher::dispatch(new LeadSegmentChanged($leadId, $segment->getKey(), true));
                }

                foreach ($removed as $leadId) {
                    CrmEventDispatcher::dispatch(new LeadSegmentChanged($leadId, $segment->getKey(), false));
                }

                $segment->forceFill([
                    'last_match_ids' => $ids->all(),
                    'last_evaluated_at' => Carbon::now(),
                ])->save();
            });

            $this->line("Segment {$segment->id} matched {$ids->count()} leads.");
        }

        $this->info("Refreshed {$segments->count()} dynamic segments.");

        return self::SUCCESS;
    }
}
