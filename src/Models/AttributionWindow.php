<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionWindow extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function isOpen(?\Illuminate\Support\Carbon $at = null): bool
    {
        $at ??= \Illuminate\Support\Carbon::now();

        return $this->is_active
            && $this->opens_at !== null
            && $this->closes_at !== null
            && $this->opens_at->lte($at)
            && $this->closes_at->gte($at);
    }
}
