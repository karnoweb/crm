<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Karnoweb\Crm\Enums\TaskStatus;

/**
 * Operational CRM task persisted as crm_followups.
 * Product language: Task. Reminder uses notified_at without implying completion.
 */
class Followup extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Lead|Deal, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOpen(): bool
    {
        return $this->status === TaskStatus::Open;
    }

    public function isOverdue(?\Illuminate\Support\Carbon $now = null): bool
    {
        $now ??= \Illuminate\Support\Carbon::now();

        return $this->isOpen()
            && $this->due_at !== null
            && $this->due_at->lte($now);
    }

    /**
     * @param Builder<Followup> $query
     *
     * @return Builder<Followup>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::Open);
    }
}
