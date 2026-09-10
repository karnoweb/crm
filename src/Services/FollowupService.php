<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Followup;
use Karnoweb\Crm\Models\Lead;

/**
 * Backwards-compatible reminder/schedule API.
 * Prefer {@see TaskService} for new product code.
 */
final class FollowupService
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    public function schedule(Lead|Deal $subject, Carbon $dueAt, int|string|null $assignedTo = null): Followup
    {
        return $this->tasks->create($subject, $dueAt, $assignedTo);
    }

    public function processDue(?Carbon $now = null): int
    {
        return $this->tasks->processDue($now);
    }
}
