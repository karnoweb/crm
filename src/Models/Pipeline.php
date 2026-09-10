<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Crm\Enums\PipelineStageType;

class Pipeline extends BaseModel
{
    /**
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('order');
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function stageOfType(PipelineStageType $type): ?PipelineStage
    {
        return $this->stages()->where('type', $type)->first();
    }
}
