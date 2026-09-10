<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

/**
 * Known scoring types. Interaction.type itself is an open string — unknown values persist
 * and score with config('crm.scoring.default_weight').
 */
enum InteractionType: string
{
    case Viewed = 'viewed';
    case Clicked = 'clicked';
    case Requested = 'requested';
    case Purchased = 'purchased';
    case Enrolled = 'enrolled';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
