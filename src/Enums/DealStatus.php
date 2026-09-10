<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function isTerminal(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
