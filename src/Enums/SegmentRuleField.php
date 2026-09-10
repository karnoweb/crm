<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Enums;

/**
 * Closed allowlist of SegmentRule fields. Never map these to dynamic Eloquent properties.
 */
enum SegmentRuleField: string
{
    case LeadStatus = 'lead.status';
    case LeadScore = 'lead.score';
    case LeadSource = 'lead.source';
    case LastInteractionAt = 'lead.last_interaction_at';
    case InteractionsCount = 'lead.total_interactions';
    case InterestScoreForGroup = 'interest.score';
    case DealStatus = 'deal.status';
    case DealValue = 'deal.value';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
