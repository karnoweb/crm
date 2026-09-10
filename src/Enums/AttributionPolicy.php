<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

enum AttributionPolicy: string
{
    case FirstTouch = 'first_touch';
    case LastTouch = 'last_touch';
    case ActiveOpportunity = 'active_opportunity';
    case Manual = 'manual';
}
