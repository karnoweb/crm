<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum SegmentRuleOperator: string
{
    case Equals = '=';
    case GreaterThan = '>';
    case LessThan = '<';
    case GreaterThanOrEqual = '>=';
    case LessThanOrEqual = '<=';
    case In = 'in';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
