<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

/**
 * Rank-based delivery state. Higher rank wins; equal/lower is a no-op.
 *
 * rank: pending(0) < dispatch_requested(1) < sent(2) < delivered(3) < opened(4) < clicked(5)
 * failed is terminal and reachable only from ranks 0..2.
 */
enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case DispatchRequested = 'dispatch_requested';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::DispatchRequested => 1,
            self::Sent => 2,
            self::Delivered => 3,
            self::Opened => 4,
            self::Clicked => 5,
            self::Failed => -1,
        };
    }

    public function isFailedTerminal(): bool
    {
        return $this === self::Failed;
    }

    /**
     * failed may only be reached from ranks before delivery confirmation (0..2).
     */
    public function canFail(): bool
    {
        return $this->rank() >= 0 && $this->rank() <= 2;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
