<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
