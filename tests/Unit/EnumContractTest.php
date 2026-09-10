<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Enums\CampaignRecipientStatus;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Enums\SegmentRuleField;
use Karnoweb\Crm\Enums\SegmentRuleOperator;
use PHPUnit\Framework\TestCase;

final class EnumContractTest extends TestCase
{
    public function test_lead_customer_is_the_only_terminal_lead_status(): void
    {
        $this->assertTrue(LeadStatus::Customer->isTerminal());
        $this->assertFalse(LeadStatus::Lost->isTerminal());
        $this->assertSame(['new', 'contacted', 'qualified', 'customer', 'lost'], LeadStatus::values());
    }

    public function test_campaign_recipient_ranks_match_the_locked_model(): void
    {
        $this->assertSame(0, CampaignRecipientStatus::Pending->rank());
        $this->assertSame(1, CampaignRecipientStatus::DispatchRequested->rank());
        $this->assertSame(2, CampaignRecipientStatus::Sent->rank());
        $this->assertSame(3, CampaignRecipientStatus::Delivered->rank());
        $this->assertSame(4, CampaignRecipientStatus::Opened->rank());
        $this->assertSame(5, CampaignRecipientStatus::Clicked->rank());
        $this->assertTrue(CampaignRecipientStatus::Failed->isFailedTerminal());
        $this->assertTrue(CampaignRecipientStatus::Sent->canFail());
        $this->assertFalse(CampaignRecipientStatus::Delivered->canFail());
    }

    public function test_segment_rule_allowlist_is_closed_and_excludes_opaque_fields(): void
    {
        $values = SegmentRuleField::values();

        $this->assertContains('lead.status', $values);
        $this->assertContains('interest.score', $values);
        $this->assertNotContains('metadata', $values);
        $this->assertNotContains('attributes', $values);
        $this->assertFalse(SegmentRuleField::tryFrom('metadata.preferred_language') instanceof SegmentRuleField);
        $this->assertFalse(SegmentRuleField::tryFrom('attributes.newsletter_opt_in') instanceof SegmentRuleField);
        $this->assertSame(['=', '>', '<', '>=', '<=', 'in'], SegmentRuleOperator::values());
        $this->assertNull(SegmentRuleOperator::tryFrom('like'));
    }
}
