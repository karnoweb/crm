<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Crm\Enums\AttributionPolicy;
use Karnoweb\Crm\Enums\AttributionStatus;

class Attribution extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttributionStatus::class,
            'policy' => AttributionPolicy::class,
            'amount' => 'float',
            'attributed_at' => 'immutable_datetime',
            'window_starts_at' => 'immutable_datetime',
            'window_ends_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
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

    public function isAttributed(): bool
    {
        return $this->status === AttributionStatus::Attributed;
    }
}
