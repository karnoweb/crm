<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Customer = 'customer';
    case Lost = 'lost';

    /**
     * customer is terminal in v1 — no outbound transition is performed by CRM services.
     */
    public function isTerminal(): bool
    {
        return $this === self::Customer;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
