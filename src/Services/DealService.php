<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Events\DealLost;
use Karnoweb\Crm\Events\DealStageChanged;
use Karnoweb\Crm\Events\DealWon;
use Karnoweb\Crm\Exceptions\InvalidStageTransitionException;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\PipelineStage;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\StageTransitionValidator;

final class DealService
{
    public function __construct(
        private readonly StageTransitionValidator $validator,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Deal
    {
        return DB::transaction(function () use ($data): Deal {
            $stage = PipelineStage::query()->whereKey($data['pipeline_stage_id'])->firstOrFail();

            if ($stage->type !== PipelineStageType::Open) {
                throw new InvalidStageTransitionException('A new Deal must start on an open stage.');
            }

            return Deal::query()->create([
                'lead_id' => $data['lead_id'],
                'pipeline_id' => $stage->pipeline_id,
                'pipeline_stage_id' => $stage->getKey(),
                'stage_entered_at' => Carbon::now(),
                'value' => $data['value'] ?? null,
                'probability' => $data['probability'] ?? null,
                'status' => DealStatus::Open,
                'branch_id' => $data['branch_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'expected_close_at' => $data['expected_close_at'] ?? null,
            ]);
        });
    }

    public function moveStage(Deal $deal, PipelineStage $stage): Deal
    {
        return DB::transaction(function () use ($deal, $stage): Deal {
            $deal = Deal::query()->whereKey($deal->getKey())->lockForUpdate()->firstOrFail();
            $stage = PipelineStage::query()->whereKey($stage->getKey())->firstOrFail();

            $this->validator->assertCanMove($deal, $stage);

            if ((int) $deal->pipeline_stage_id === (int) $stage->getKey()) {
                return $deal;
            }

            $from = $deal->pipeline_stage_id;
            $deal->forceFill([
                'pipeline_stage_id' => $stage->getKey(),
                'stage_entered_at' => Carbon::now(),
            ])->save();

            CrmEventDispatcher::dispatch(new DealStageChanged($deal->getKey(), $from, $stage->getKey()));

            return $deal->refresh();
        });
    }

    public function win(Deal $deal): Deal
    {
        return DB::transaction(function () use ($deal): Deal {
            $deal = Deal::query()->whereKey($deal->getKey())->lockForUpdate()->firstOrFail();

            if ($deal->status === DealStatus::Won) {
                return $deal;
            }

            $wonStage = PipelineStage::query()
                ->where('pipeline_id', $deal->pipeline_id)
                ->where('type', PipelineStageType::Won)
                ->firstOrFail();

            $this->validator->assertWin($deal, $wonStage);

            $deal->forceFill([
                'status' => DealStatus::Won,
                'pipeline_stage_id' => $wonStage->getKey(),
                'stage_entered_at' => Carbon::now(),
            ])->save();

            CrmEventDispatcher::dispatch(new DealWon($deal->getKey(), $deal->lead_id));

            if ($deal->assigned_to !== null && $deal->lead !== null) {
                app(AttributionService::class)->openWindow(
                    $deal->lead,
                    $deal->assigned_to,
                    $deal,
                    Carbon::now(),
                    ['source' => 'deal_won', 'metadata' => ['deal_id' => $deal->getKey()]],
                );
            } elseif ($deal->assigned_to !== null) {
                $lead = Lead::query()->find($deal->lead_id);
                if ($lead !== null) {
                    app(AttributionService::class)->openWindow(
                        $lead,
                        $deal->assigned_to,
                        $deal,
                        Carbon::now(),
                        ['source' => 'deal_won', 'metadata' => ['deal_id' => $deal->getKey()]],
                    );
                }
            }

            return $deal->refresh();
        });
    }

    public function lose(Deal $deal, string $reason): Deal
    {
        return DB::transaction(function () use ($deal, $reason): Deal {
            $deal = Deal::query()->whereKey($deal->getKey())->lockForUpdate()->firstOrFail();

            if ($deal->status === DealStatus::Lost) {
                return $deal;
            }

            $lostStage = PipelineStage::query()
                ->where('pipeline_id', $deal->pipeline_id)
                ->where('type', PipelineStageType::Lost)
                ->firstOrFail();

            $this->validator->assertLose($deal, $lostStage);

            $deal->forceFill([
                'status' => DealStatus::Lost,
                'pipeline_stage_id' => $lostStage->getKey(),
                'stage_entered_at' => Carbon::now(),
                'lost_reason' => $reason,
            ])->save();

            CrmEventDispatcher::dispatch(new DealLost($deal->getKey(), $deal->lead_id, $reason));

            return $deal->refresh();
        });
    }
}
