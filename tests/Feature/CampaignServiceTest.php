<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\CampaignRecipientStatus;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Enums\SegmentRuleField;
use Karnoweb\Crm\Enums\SegmentRuleOperator;
use Karnoweb\Crm\Events\CampaignDispatchRequested;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Campaign;
use Karnoweb\Crm\Models\CampaignRecipient;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Models\SegmentRule;
use Karnoweb\Crm\Tests\TestCase;

final class CampaignServiceTest extends TestCase
{
    public function test_enqueue_recipients_is_idempotent(): void
    {
        Event::fake([CampaignDispatchRequested::class]);
        [$campaign, $lead] = $this->campaignFixture();

        Crm::campaigns()->enqueueRecipients($campaign, [$lead->id, $lead->id]);

        $this->assertSame(1, CampaignRecipient::query()->count());
        Event::assertNotDispatched(CampaignDispatchRequested::class);
    }

    public function test_evaluate_does_not_create_recipients_and_enqueue_does_not_dispatch(): void
    {
        Event::fake([CampaignDispatchRequested::class]);
        [$campaign, $lead] = $this->campaignFixture();

        $ids = Crm::segments()->evaluate($campaign->segment->load('rules'));
        $this->assertContains($lead->id, $ids->all());
        $this->assertSame(0, CampaignRecipient::query()->count());

        Crm::campaigns()->enqueueRecipientsFromSegment($campaign, $campaign->segment->load('rules'));
        Crm::campaigns()->enqueueRecipientsFromSegment($campaign, $campaign->segment->load('rules'));

        $this->assertSame(1, CampaignRecipient::query()->count());
        $this->assertSame(CampaignRecipientStatus::Pending, CampaignRecipient::query()->first()->status);
        Event::assertNotDispatched(CampaignDispatchRequested::class);
    }

    public function test_dispatch_pending_is_the_only_event_producer_and_is_idempotent(): void
    {
        Event::fake([CampaignDispatchRequested::class]);
        [$campaign] = $this->campaignFixture();
        Crm::campaigns()->enqueueRecipientsFromSegment($campaign, $campaign->segment->load('rules'));

        Crm::campaigns()->dispatchPending($campaign);
        Crm::campaigns()->dispatchPending($campaign);

        $recipient = CampaignRecipient::query()->first();
        $this->assertSame(CampaignRecipientStatus::DispatchRequested, $recipient->status);
        Event::assertDispatchedTimes(CampaignDispatchRequested::class, 1);
        Event::assertDispatched(CampaignDispatchRequested::class, function (CampaignDispatchRequested $event) use ($campaign, $recipient): bool {
            return $event->campaignId === $campaign->id
                && $event->recipientId === $recipient->id
                && $event->channel === 'sms'
                && ! array_key_exists('phone', $event->payload);
        });
    }

    public function test_rank_based_out_of_order_and_duplicate_marks(): void
    {
        [$campaign] = $this->campaignFixture();
        Crm::campaigns()->enqueueRecipientsFromSegment($campaign, $campaign->segment->load('rules'));
        Crm::campaigns()->dispatchPending($campaign);
        $recipient = CampaignRecipient::query()->first();

        Crm::campaigns()->markClicked($recipient);
        $this->assertSame(CampaignRecipientStatus::Clicked, $recipient->fresh()->status);

        Crm::campaigns()->markOpened($recipient->fresh());
        Crm::campaigns()->markDelivered($recipient->fresh());
        Crm::campaigns()->markSent($recipient->fresh());
        $this->assertSame(CampaignRecipientStatus::Clicked, $recipient->fresh()->status);

        $second = CampaignRecipient::query()->first();
        $second->forceFill(['status' => CampaignRecipientStatus::Sent])->save();
        Crm::campaigns()->markOpened($second->fresh());
        $this->assertSame(CampaignRecipientStatus::Opened, $second->fresh()->status);
        Crm::campaigns()->markOpened($second->fresh());
        $this->assertSame(CampaignRecipientStatus::Opened, $second->fresh()->status);
    }

    public function test_failed_is_terminal_before_delivery_and_ignored_after(): void
    {
        [$campaign] = $this->campaignFixture();
        Crm::campaigns()->enqueueRecipientsFromSegment($campaign, $campaign->segment->load('rules'));
        $pending = CampaignRecipient::query()->first();
        Crm::campaigns()->markFailed($pending);
        $this->assertSame(CampaignRecipientStatus::Failed, $pending->fresh()->status);
        Crm::campaigns()->markSent($pending->fresh());
        $this->assertSame(CampaignRecipientStatus::Failed, $pending->fresh()->status);

        $pending->forceFill(['status' => CampaignRecipientStatus::Delivered])->save();
        Crm::campaigns()->markFailed($pending->fresh());
        $this->assertSame(CampaignRecipientStatus::Delivered, $pending->fresh()->status);
    }

    /**
     * @return array{0: Campaign, 1: Lead}
     */
    private function campaignFixture(): array
    {
        $lead = Crm::leads()->create(['name' => 'C', 'status' => LeadStatus::Qualified]);
        $segment = Segment::query()->create(['name' => 'All qualified']);
        SegmentRule::query()->create([
            'segment_id' => $segment->id,
            'field' => SegmentRuleField::LeadStatus,
            'operator' => SegmentRuleOperator::Equals,
            'value' => 'qualified',
        ]);
        $campaign = Crm::campaigns()->create([
            'name' => 'Spring',
            'channel' => 'sms',
            'segment_id' => $segment->id,
            'payload' => ['template_id' => 12],
        ]);

        return [$campaign->load('segment'), $lead];
    }
}
