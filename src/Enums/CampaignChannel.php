<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum CampaignChannel: string
{
    case Sms = 'sms';
    case Email = 'email';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
