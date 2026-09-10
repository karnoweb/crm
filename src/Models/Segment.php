<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Segment extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'last_evaluated_at' => 'immutable_datetime',
            'last_match_ids' => 'array',
        ];
    }

    /**
     * @return HasMany<SegmentRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SegmentRule::class);
    }
}
