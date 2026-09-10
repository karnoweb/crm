<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit the first time a Deal is lost. Idempotent re-losses do not republish.
 */
final class DealLost
{
    public function __construct(
        public readonly int|string $dealId,
        public readonly int|string $leadId,
        public readonly string $reason,
    ) {}
}
