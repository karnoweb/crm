<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\CampaignChannel;
use Karnoweb\Crm\Enums\CampaignRecipientStatus;
use Karnoweb\Crm\Events\CampaignDispatchRequested;
use Karnoweb\Crm\Models\Campaign;
use Karnoweb\Crm\Models\CampaignRecipient;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Support\CampaignRecipientStatusRank;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\QueryExceptionClassifier;

final class CampaignService
{
    public function __construct(
        private readonly SegmentEvaluationService $segments,
        private readonly CampaignRecipientStatusRank $ranks,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        return Campaign::query()->create([
            'name' => $data['name'],
            'channel' => $data['channel'] instanceof CampaignChannel
                ? $data['channel']
                : CampaignChannel::from((string) $data['channel']),
            'segment_id' => $data['segment_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'payload' => $data['payload'] ?? [],
        ]);
    }

    /**
     * Creates pending recipients only. Does not publish CampaignDispatchRequested.
     *
     * @param iterable<int|string> $leadIds
     */
    public function enqueueRecipients(Campaign $campaign, iterable $leadIds): void
    {
        DB::transaction(function () use ($campaign, $leadIds): void {
            foreach ($leadIds as $leadId) {
                try {
                    CampaignRecipient::query()->create([
                        'campaign_id' => $campaign->getKey(),
                        'lead_id' => (int) $leadId,
                        'status' => CampaignRecipientStatus::Pending,
                    ]);
                } catch (QueryException $e) {
                    if (! QueryExceptionClassifier::isUniqueViolationOn($e, 'campaign_id', 'lead_id')) {
                        throw $e;
                    }
                }
            }
        });
    }

    /**
     * Creates pending recipients only. Does not publish CampaignDispatchRequested.
     */
    public function enqueueRecipientsFromSegment(Campaign $campaign, Segment $segment): void
    {
        $this->enqueueRecipients($campaign, $this->segments->evaluate($segment));
    }

    /**
     * Sole producer of CampaignDispatchRequested.
     */
    public function dispatchPending(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $recipients = CampaignRecipient::query()
                ->where('campaign_id', $campaign->getKey())
                ->where('status', CampaignRecipientStatus::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->forceFill([
                    'status' => CampaignRecipientStatus::DispatchRequested,
                ])->save();

                CrmEventDispatcher::dispatch(new CampaignDispatchRequested(
                    $campaign->getKey(),
                    $recipient->getKey(),
                    $recipient->lead_id,
                    $campaign->channel->value,
                    $campaign->payload ?? [],
                ));
            }
        });
    }

    public function markSent(CampaignRecipient $recipient): void
    {
        $this->advance($recipient, CampaignRecipientStatus::Sent);
    }

    public function markDelivered(CampaignRecipient $recipient): void
    {
        $this->advance($recipient, CampaignRecipientStatus::Delivered);
    }

    public function markOpened(CampaignRecipient $recipient): void
    {
        $this->advance($recipient, CampaignRecipientStatus::Opened);
    }

    public function markClicked(CampaignRecipient $recipient): void
    {
        $this->advance($recipient, CampaignRecipientStatus::Clicked);
    }

    public function markFailed(CampaignRecipient $recipient): void
    {
        $this->advance($recipient, CampaignRecipientStatus::Failed);
    }

    private function advance(CampaignRecipient $recipient, CampaignRecipientStatus $next): void
    {
        DB::transaction(function () use ($recipient, $next): void {
            $recipient = CampaignRecipient::query()->whereKey($recipient->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->ranks->shouldAdvance($recipient->status, $next)) {
                return;
            }

            $recipient->forceFill(['status' => $next])->save();
        });
    }
}
