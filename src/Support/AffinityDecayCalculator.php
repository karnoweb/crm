<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

/**
 * Exponential decay: 0.5 ^ (age_in_days / half_life_days).
 */
final class AffinityDecayCalculator
{
    public function decay(float $ageInDays, int $halfLifeDays): float
    {
        $age = max(0.0, $ageInDays);
        $halfLife = max(1, $halfLifeDays);

        return 0.5 ** ($age / $halfLife);
    }
}
