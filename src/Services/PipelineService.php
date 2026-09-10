<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Exceptions\InvalidPipelineException;
use Karnoweb\Crm\Models\Pipeline;
use Karnoweb\Crm\Models\PipelineStage;

final class PipelineService
{
    /**
     * @param array<string, mixed> $data
     * @param list<string>         $openStageNames
     */
    public function createWithDefaultStages(array $data, array $openStageNames): Pipeline
    {
        if ($openStageNames === []) {
            throw new InvalidPipelineException('Pipeline creation requires at least one open stage.');
        }

        return DB::transaction(function () use ($data, $openStageNames): Pipeline {
            $pipeline = Pipeline::query()->create([
                'name' => $data['name'] ?? 'Sales',
            ]);

            $order = 0;
            foreach ($openStageNames as $name) {
                PipelineStage::query()->create([
                    'pipeline_id' => $pipeline->getKey(),
                    'name' => $name,
                    'type' => PipelineStageType::Open,
                    'order' => $order++,
                ]);
            }

            PipelineStage::query()->create([
                'pipeline_id' => $pipeline->getKey(),
                'name' => $data['won_stage_name'] ?? 'Won',
                'type' => PipelineStageType::Won,
                'order' => $order++,
            ]);

            PipelineStage::query()->create([
                'pipeline_id' => $pipeline->getKey(),
                'name' => $data['lost_stage_name'] ?? 'Lost',
                'type' => PipelineStageType::Lost,
                'order' => $order++,
            ]);

            return $pipeline->load('stages');
        });
    }

    public function addStage(Pipeline $pipeline, string $name, PipelineStageType $type): PipelineStage
    {
        return DB::transaction(function () use ($pipeline, $name, $type): PipelineStage {
            $pipeline = Pipeline::query()->whereKey($pipeline->getKey())->lockForUpdate()->firstOrFail();

            if ($type->isTerminal()) {
                $exists = PipelineStage::query()
                    ->where('pipeline_id', $pipeline->getKey())
                    ->where('type', $type)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw new InvalidPipelineException("Pipeline already has a {$type->value} stage.");
                }
            }

            $order = (int) PipelineStage::query()->where('pipeline_id', $pipeline->getKey())->max('order');

            return PipelineStage::query()->create([
                'pipeline_id' => $pipeline->getKey(),
                'name' => $name,
                'type' => $type,
                'order' => $order + 1,
            ]);
        });
    }
}
