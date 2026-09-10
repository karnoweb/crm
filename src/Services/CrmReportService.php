<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Enums\TaskStatus;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Followup;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\PipelineStage;
use Karnoweb\Crm\Support\AffinityDecayCalculator;
use Karnoweb\Crm\Support\ScoringPolicy;

/**
 * Read-only reporting. Critical affinity metrics are aggregated from Interaction.
 */
final class CrmReportService
{
    public function __construct(
        private readonly ScoringPolicy $policy,
        private readonly AffinityDecayCalculator $decay,
        private readonly AttributionService $attributions,
    ) {}

    /**
     * @return array{
     *     captured: int,
     *     by_status: array<string, int>,
     *     converted: int,
     *     interactions: int
     * }
     */
    public function conversionFunnel(?Carbon $from = null, ?Carbon $to = null, ?int $branchId = null): array
    {
        $leads = Lead::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('captured_at', '<=', $to));

        $byStatus = [];
        foreach (LeadStatus::cases() as $status) {
            $byStatus[$status->value] = (clone $leads)->where('status', $status)->count();
        }

        $converted = Lead::query()
            ->whereNotNull('converted_at')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('converted_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('converted_at', '<=', $to))
            ->count();

        $interactions = Interaction::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->when($branchId !== null, function ($q) use ($branchId): void {
                $q->whereIn('lead_id', Lead::query()->where('branch_id', $branchId)->select('id'));
            })
            ->count();

        return [
            'captured' => (clone $leads)->count(),
            'by_status' => $byStatus,
            'converted' => $converted,
            'interactions' => $interactions,
        ];
    }

    /**
     * @return array{open: int, won: int, lost: int, won_value: float, lost_value: float, win_rate: float}
     */
    public function winLossSummary(?int $pipelineId = null, ?int $branchId = null): array
    {
        $query = Deal::query()
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId));

        $won = (clone $query)->where('status', DealStatus::Won)->count();
        $lost = (clone $query)->where('status', DealStatus::Lost)->count();
        $closed = $won + $lost;

        return [
            'open' => (clone $query)->where('status', DealStatus::Open)->count(),
            'won' => $won,
            'lost' => $lost,
            'won_value' => (float) (clone $query)->where('status', DealStatus::Won)->sum('value'),
            'lost_value' => (float) (clone $query)->where('status', DealStatus::Lost)->sum('value'),
            'win_rate' => $closed > 0 ? round(($won / $closed) * 100, 2) : 0.0,
        ];
    }

    /**
     * @return Collection<int, object{source: string|null, count: int}>
     */
    public function leadsBySource(?Carbon $from = null, ?Carbon $to = null, ?int $branchId = null): Collection
    {
        return Lead::query()
            ->selectRaw('source, count(*) as count')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('captured_at', '<=', $to))
            ->groupBy('source')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => (object) [
                'source' => $row->source,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * @return array{leads: int, deals: int, conversion_rate: float}
     */
    public function leadToDealConversion(?Carbon $from = null, ?Carbon $to = null, ?int $branchId = null): array
    {
        $leads = Lead::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('captured_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('captured_at', '<=', $to))
            ->count();

        $deals = Deal::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        return [
            'leads' => $leads,
            'deals' => $deals,
            'conversion_rate' => $leads > 0 ? round(($deals / $leads) * 100, 2) : 0.0,
        ];
    }

    /**
     * Snapshot of open deals per stage (stage conversion funnel snapshot).
     *
     * @return Collection<int, object{stage_id: int, stage_name: string, count: int, avg_age_days: float}>
     */
    public function stageFunnel(?int $pipelineId = null, ?int $branchId = null): Collection
    {
        $stages = PipelineStage::query()
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->orderBy('order')
            ->get();

        return $stages->map(function (PipelineStage $stage) use ($branchId) {
            $deals = Deal::query()
                ->where('pipeline_stage_id', $stage->getKey())
                ->where('status', DealStatus::Open)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->get(['id', 'stage_entered_at']);

            $ages = $deals->map(function (Deal $deal): float {
                if ($deal->stage_entered_at === null) {
                    return 0.0;
                }

                return (float) $deal->stage_entered_at->diffInDays(Carbon::now());
            });

            return (object) [
                'stage_id' => (int) $stage->getKey(),
                'stage_name' => $stage->name,
                'count' => $deals->count(),
                'avg_age_days' => $ages->isEmpty() ? 0.0 : round($ages->avg(), 2),
            ];
        })->values();
    }

    /**
     * Stage aging uses stage_entered_at exclusively (never updated_at).
     *
     * @return Collection<int, object{deal_id: int, stage_id: int, age_days: int}>
     */
    public function stageAging(?int $pipelineId = null, ?int $branchId = null, int $limit = 50): Collection
    {
        return Deal::query()
            ->where('status', DealStatus::Open)
            ->whereNotNull('stage_entered_at')
            ->when($pipelineId, fn ($q) => $q->where('pipeline_id', $pipelineId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('stage_entered_at')
            ->limit($limit)
            ->get(['id', 'pipeline_stage_id', 'stage_entered_at'])
            ->map(fn (Deal $deal) => (object) [
                'deal_id' => (int) $deal->id,
                'stage_id' => (int) $deal->pipeline_stage_id,
                'age_days' => (int) $deal->stage_entered_at->diffInDays(Carbon::now()),
            ]);
    }

    /**
     * @return Collection<int, object{owner_id: int|string, open_deals: int, open_tasks: int, overdue_tasks: int}>
     */
    public function ownerWorkload(?int $branchId = null): Collection
    {
        $owners = Deal::query()
            ->where('status', DealStatus::Open)
            ->whereNotNull('assigned_to')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('assigned_to, count(*) as open_deals')
            ->groupBy('assigned_to')
            ->pluck('open_deals', 'assigned_to');

        $now = Carbon::now();
        $result = collect();

        foreach ($owners as $ownerId => $openDeals) {
            $openTasks = Followup::query()
                ->where('status', TaskStatus::Open)
                ->where('assigned_to', $ownerId)
                ->count();

            $overdue = Followup::query()
                ->where('status', TaskStatus::Open)
                ->where('assigned_to', $ownerId)
                ->where('due_at', '<=', $now)
                ->count();

            $result->push((object) [
                'owner_id' => $ownerId,
                'open_deals' => (int) $openDeals,
                'open_tasks' => $openTasks,
                'overdue_tasks' => $overdue,
            ]);
        }

        return $result->values();
    }

    public function attributedRevenue(?Carbon $from = null, ?Carbon $to = null, ?int $branchId = null): float
    {
        return $this->attributions->attributedRevenue($from, $to, $branchId);
    }

    /**
     * @return Collection<int, object{subject_group: string, subject_key: string, score: float, interactions_count: int}>
     */
    public function topInterests(string $subjectGroup, int $limit = 10): Collection
    {
        $asOf = Carbon::now();
        $halfLife = $this->policy->halfLifeDays();
        $scores = [];

        Interaction::query()
            ->where('subject_group', $subjectGroup)
            ->orderBy('id')
            ->each(function (Interaction $interaction) use (&$scores, $asOf, $halfLife): void {
                $key = $interaction->subject_key;
                $age = max(0.0, floor($interaction->occurred_at->diffInSeconds($asOf) / 86400));
                $score = $this->policy->effectiveWeight($interaction->type, (float) $interaction->weight)
                    * $this->decay->decay($age, $halfLife);

                $scores[$key] ??= ['subject_group' => $interaction->subject_group, 'subject_key' => $key, 'score' => 0.0, 'interactions_count' => 0];
                $scores[$key]['score'] += $score;
                $scores[$key]['interactions_count']++;
            });

        return collect($scores)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn (array $row) => (object) $row);
    }
}
