<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when an Interest score crosses the configured affinity threshold.
 */
final class AffinityThresholdReached
{
    public function __construct(
        public readonly int|string $leadId,
        public readonly string $subjectGroup,
        public readonly string $subjectKey,
        public readonly float $score,
        public readonly float $threshold,
    ) {}
}
