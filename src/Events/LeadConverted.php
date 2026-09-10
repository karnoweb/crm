<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when a Lead first becomes a customer and links user_id.
 */
final class LeadConverted
{
    public function __construct(
        public readonly int|string $leadId,
        public readonly int|string $userId,
    ) {}
}
