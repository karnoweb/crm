<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when a new Interaction is inserted (not on idempotent replay).
 */
final class InteractionRecorded
{
    public function __construct(
        public readonly int|string $interactionId,
        public readonly int|string $leadId,
        public readonly string $type,
        public readonly string $subjectGroup,
        public readonly string $subjectKey,
    ) {}
}
