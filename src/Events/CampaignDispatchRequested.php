<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Events;

use Karnoweb\Crm\Exceptions\ForbiddenCampaignPayloadException;

/**
 * Integration signal: this recipient is ready for the host to send.
 *
 * This is NOT an internal CRM delivery command. CRM does not send SMS or email.
 * Produced only by CampaignService::dispatchPending() after commit.
 *
 * Payload may contain campaign content metadata (e.g. template_id) only.
 * Forbidden: sms_provider, sender, smtp_host, raw phone, raw email, or any delivery-provider field.
 */
final class CampaignDispatchRequested
{
    /** @var list<string> */
    private const FORBIDDEN_PAYLOAD_KEYS = [
        'sms_provider',
        'sender',
        'smtp_host',
        'smtp',
        'phone',
        'phone_number',
        'email',
        'email_address',
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int|string $campaignId,
        public readonly int|string $recipientId,
        public readonly int|string $leadId,
        public readonly string $channel,
        public readonly array $payload = [],
    ) {
        $this->assertPayloadIsSafe($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertPayloadIsSafe(array $payload): void
    {
        foreach (array_keys($payload) as $key) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_PAYLOAD_KEYS, true)) {
                throw new ForbiddenCampaignPayloadException(
                    "CampaignDispatchRequested payload must not contain delivery/provider field [{$key}]."
                );
            }
        }
    }
}
