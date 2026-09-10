<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Collection;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Support\SegmentRuleEvaluator;

/**
 * Deterministic, read-only evaluation. Returns lead IDs and creates nothing.
 */
final class SegmentEvaluationService
{
    public function __construct(
        private readonly SegmentRuleEvaluator $evaluator,
    ) {}

    /**
     * @return Collection<int, int|string>
     */
    public function evaluate(Segment $segment): Collection
    {
        $query = Lead::query()->whereNull('archived_at');

        foreach ($segment->rules as $rule) {
            $this->evaluator->apply($query, $rule);
        }

        return $query->orderBy('id')->pluck('id');
    }
}
