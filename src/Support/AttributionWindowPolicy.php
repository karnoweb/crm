<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Enums\AttributionPolicy;

/**
 * Configurable attribution window — duration is never hard-coded in domain logic.
 */
final class AttributionWindowPolicy
{
    public function windowDays(): int
    {
        return max(1, (int) config('crm.attribution.window_days', 30));
    }

    public function defaultPolicy(): AttributionPolicy
    {
        $value = (string) config('crm.attribution.default_policy', AttributionPolicy::ActiveOpportunity->value);

        return AttributionPolicy::tryFrom($value) ?? AttributionPolicy::ActiveOpportunity;
    }

    public function closesAt(?Carbon $opensAt = null): Carbon
    {
        $opensAt ??= Carbon::now();

        return $opensAt->copy()->addDays($this->windowDays());
    }
}
