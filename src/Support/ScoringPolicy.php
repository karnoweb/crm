<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

/**
 * Locked scoring policy:
 * effective_weight = interaction.weight × scoring.weights[type]
 * Unknown types use scoring.default_weight (default 0), never an exception.
 */
final class ScoringPolicy
{
    public function weightFor(string $type): float
    {
        $weights = config('crm.scoring.weights', []);

        if (is_array($weights) && array_key_exists($type, $weights)) {
            return (float) $weights[$type];
        }

        return (float) config('crm.scoring.default_weight', 0);
    }

    public function effectiveWeight(string $type, float $rawWeight): float
    {
        return $rawWeight * $this->weightFor($type);
    }

    public function halfLifeDays(): int
    {
        return max(1, (int) config('crm.scoring.decay_half_life_days', 30));
    }

    public function affinityThreshold(): float
    {
        return (float) config('crm.scoring.thresholds.affinity_alert', 20);
    }
}
