<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Crm\Enums\LeadStatus;
use Spatie\SchemalessAttributes\SchemalessAttributes;

/**
 * Lead is the permanent CRM party/contact identity. The word "Lead" names the
 * acquisition stage, not a temporary record type. When status becomes customer,
 * this same row remains the identity.
 *
 * `attributes` (JSON column) is opaque host-defined contact data. It is never
 * used for scoring, segmentation, or any internal CRM decision.
 *
 * Eloquent's `$model->attributes` bag is reserved by the framework. Read/write
 * the schemaless column through {@see hostAttributes()}.
 *
 * @property array<string, mixed>|null $attributes
 * @property int|string|null           $user_id
 * @property int|string|null           $branch_id
 * @property LeadStatus                $status
 */
class Lead extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'score' => 'integer',
            'rfm_recency_score' => 'integer',
            'rfm_frequency_score' => 'integer',
            'rfm_monetary_score' => 'integer',
            'rfm_monetary_at' => 'immutable_datetime',
            'total_interactions' => 'integer',
            'last_status_change_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'first_seen_at' => 'immutable_datetime',
            'last_interaction_at' => 'immutable_datetime',
            'attributes' => 'array',
        ];
    }

    /**
     * Opaque host-defined contact data. Never queried by CRM internals.
     */
    public function hostAttributes(): SchemalessAttributes
    {
        return SchemalessAttributes::createForModel($this, 'attributes');
    }

    /**
     * @return HasMany<Interaction, $this>
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * @return HasMany<Interest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(Interest::class);
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return Collection<int, Interest>
     */
    public function rankedInterests(?string $subjectGroup = null, int $limit = 5): Collection
    {
        return $this->interests()
            ->when($subjectGroup !== null, fn ($query) => $query->where('subject_group', $subjectGroup))
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }
}
