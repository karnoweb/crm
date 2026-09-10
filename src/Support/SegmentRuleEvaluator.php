<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Database\Eloquent\Builder;
use Karnoweb\Crm\Enums\SegmentRuleField;
use Karnoweb\Crm\Enums\SegmentRuleOperator;
use Karnoweb\Crm\Exceptions\CrmException;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\SegmentRule;

/**
 * Closed allowlist evaluator. Never uses $lead->{$rule->field}.
 */
final class SegmentRuleEvaluator
{
    /**
     * @param Builder<Lead> $query
     *
     * @return Builder<Lead>
     */
    public function apply(Builder $query, SegmentRule $rule): Builder
    {
        $operator = $rule->operator;
        $value = $this->normalizedValue($rule, $operator);

        return match ($rule->field) {
            SegmentRuleField::LeadStatus => $this->whereColumn($query, 'status', $operator, $value),
            SegmentRuleField::LeadScore => $this->whereColumn($query, 'score', $operator, $value),
            SegmentRuleField::LeadSource => $this->whereColumn($query, 'source', $operator, $value),
            SegmentRuleField::LastInteractionAt => $this->whereColumn($query, 'last_interaction_at', $operator, $value),
            SegmentRuleField::InteractionsCount => $this->whereColumn($query, 'total_interactions', $operator, $value),
            SegmentRuleField::InterestScoreForGroup => $query->whereHas(
                'interests',
                function (Builder $interest) use ($rule, $operator, $value): void {
                    $group = $rule->meta['subject_group'] ?? null;
                    if (! is_string($group) || $group === '') {
                        throw new CrmException('InterestScoreForGroup requires meta.subject_group.');
                    }
                    $interest->where('subject_group', $group);
                    $this->whereColumn($interest, 'score', $operator, $value);
                }
            ),
            SegmentRuleField::DealStatus => $query->whereHas(
                'deals',
                fn (Builder $deal) => $this->whereColumn($deal, 'status', $operator, $value)
            ),
            SegmentRuleField::DealValue => $query->whereHas(
                'deals',
                fn (Builder $deal) => $this->whereColumn($deal, 'value', $operator, $value)
            ),
        };
    }

    /**
     * @param  Builder<*>  $query
     *
     * @return Builder<*>
     */
    private function whereColumn(Builder $query, string $column, SegmentRuleOperator $operator, mixed $value): Builder
    {
        return match ($operator) {
            SegmentRuleOperator::In => $query->whereIn($column, is_array($value) ? $value : [$value]),
            SegmentRuleOperator::Equals => $query->where($column, '=', $value),
            SegmentRuleOperator::GreaterThan => $query->where($column, '>', $value),
            SegmentRuleOperator::LessThan => $query->where($column, '<', $value),
            SegmentRuleOperator::GreaterThanOrEqual => $query->where($column, '>=', $value),
            SegmentRuleOperator::LessThanOrEqual => $query->where($column, '<=', $value),
        };
    }

    private function normalizedValue(SegmentRule $rule, SegmentRuleOperator $operator): mixed
    {
        $value = $rule->scalarValue();

        if ($operator === SegmentRuleOperator::In) {
            return is_array($value) ? array_values($value) : [$value];
        }

        if (is_array($value)) {
            return array_values($value)[0] ?? null;
        }

        return $value;
    }
}
