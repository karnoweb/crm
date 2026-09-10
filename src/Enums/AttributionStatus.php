<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum AttributionStatus: string
{
    case Attributed = 'attributed';
    case Unattributed = 'unattributed';
    case Reversed = 'reversed';
}
