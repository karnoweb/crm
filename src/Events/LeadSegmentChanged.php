<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when a Lead enters or leaves a Segment membership projection.
 */
final class LeadSegmentChanged
{
    public function __construct(
        public readonly int|string $leadId,
        public readonly int|string $segmentId,
        public readonly bool $included,
    ) {}
}
