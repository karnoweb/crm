<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when a new Lead identity is persisted.
 */
final class LeadCreated
{
    public function __construct(
        public readonly int|string $leadId,
    ) {}
}
