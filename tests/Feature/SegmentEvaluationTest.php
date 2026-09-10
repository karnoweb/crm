<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Enums\SegmentRuleField;
use Karnoweb\Crm\Enums\SegmentRuleOperator;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\CampaignRecipient;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Models\SegmentRule;
use Karnoweb\Crm\Tests\TestCase;

final class SegmentEvaluationTest extends TestCase
{
    public function test_supported_fields_and_operators_are_deterministic_and_side_effect_free(): void
    {
        $qualified = Crm::leads()->create(['name' => 'Q', 'source' => 'web', 'status' => LeadStatus::Qualified]);
        $other = Crm::leads()->create(['name' => 'O', 'source' => 'ads', 'status' => LeadStatus::New]);

        Crm::interactions()->record(
            leadId: $qualified->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 2,
            source: 'commerce',
            idempotencyKey: 'seg-1',
        );

        $segment = Segment::query()->create(['name' => 'Hot', 'is_dynamic' => true]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::LeadStatus,
            'operator' => SegmentRuleOperator::In,
            'value' => ['qualified', 'customer'],
        ]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::LeadScore,
            'operator' => SegmentRuleOperator::GreaterThanOrEqual,
            'value' => 10,
        ]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::LeadSource,
            'operator' => SegmentRuleOperator::Equals,
            'value' => 'web',
        ]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::InteractionsCount,
            'operator' => SegmentRuleOperator::GreaterThan,
            'value' => 0,
        ]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::InterestScoreForGroup,
            'operator' => SegmentRuleOperator::GreaterThan,
            'value' => 1,
            'meta' => ['subject_group' => 'product'],
        ]);

        $ids = Crm::segments()->evaluate($segment->load('rules'));

        $this->assertEquals([$qualified->id], $ids->all());
        $this->assertSame(0, CampaignRecipient::query()->count());
        $this->assertNull($segment->fresh()->last_evaluated_at);
        $this->assertSame(LeadStatus::Qualified, $qualified->fresh()->status);
        $this->assertNotContains($other->id, $ids->all());
    }

    public function test_deal_rules_and_numeric_operators(): void
    {
        $lead = Crm::leads()->create(['name' => 'D']);
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'S'], ['New']);
        $open = $pipeline->stages->first();
        Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $open->id,
            'value' => 500,
        ]);

        $segment = Segment::query()->create(['name' => 'Deals']);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::DealStatus,
            'operator' => SegmentRuleOperator::Equals,
            'value' => 'open',
        ]);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::DealValue,
            'operator' => SegmentRuleOperator::LessThanOrEqual,
            'value' => 500,
        ]);

        $this->assertEquals([$lead->id], Crm::segments()->evaluate($segment->load('rules'))->all());
    }

    public function test_opaque_fields_cannot_be_used_as_rules(): void
    {
        $this->assertNull(SegmentRuleField::tryFrom('metadata.preferred_language'));
        $this->assertNull(SegmentRuleField::tryFrom('attributes.newsletter_opt_in'));
        $this->assertNull(SegmentRuleField::tryFrom('lead.attributes'));
        $this->assertNull(SegmentRuleOperator::tryFrom('like'));
    }
}
