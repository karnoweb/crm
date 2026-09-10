<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when a Followup becomes due.
 */
final class FollowupDue
{
    public function __construct(
        public readonly int|string $followupId,
        public readonly string $subjectType,
        public readonly int|string $subjectId,
        public readonly string $dueAt,
    ) {}
}
