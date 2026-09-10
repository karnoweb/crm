<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

/**
 * Published after commit when an open Deal moves between open stages.
 */
final class DealStageChanged
{
    public function __construct(
        public readonly int|string $dealId,
        public readonly int|string $fromStageId,
        public readonly int|string $toStageId,
    ) {}
}
