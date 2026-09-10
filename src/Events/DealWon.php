<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit the first time a Deal is won. Idempotent re-wins do not republish.
 */
final class DealWon
{
    public function __construct(
        public readonly int|string $dealId,
        public readonly int|string $leadId,
    ) {}
}
