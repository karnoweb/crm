<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rebuildable projection of Interaction affinity for one subject.
 */
class Interest extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'interactions_count' => 'integer',
            'first_interacted_at' => 'immutable_datetime',
            'last_interacted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
