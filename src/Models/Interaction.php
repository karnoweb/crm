<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Crm\Exceptions\InteractionImmutableException;

/**
 * Immutable source of truth. Interest and aggregated Lead fields are projections
 * rebuilt from this table.
 *
 * `metadata` is opaque event-specific data. CRM never reads keys inside it
 * for scoring, segmentation, or any internal decision.
 *
 * @property array<string, mixed>|null $metadata
 * @property string                    $idempotency_key
 */
class Interaction extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InteractionImmutableException('Interactions are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new InteractionImmutableException('Interactions are immutable and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
