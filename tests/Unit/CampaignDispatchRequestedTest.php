<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Events\CampaignDispatchRequested;
use Karnoweb\Crm\Exceptions\ForbiddenCampaignPayloadException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CampaignDispatchRequestedTest extends TestCase
{
    public function test_content_metadata_payload_is_allowed(): void
    {
        $event = new CampaignDispatchRequested(1, 2, 3, 'sms', ['template_id' => 12]);

        $this->assertSame(['template_id' => 12], $event->payload);
    }

    #[DataProvider('forbiddenPayloadKeys')]
    public function test_delivery_provider_fields_are_rejected(string $key): void
    {
        $this->expectException(ForbiddenCampaignPayloadException::class);

        new CampaignDispatchRequested(1, 2, 3, 'email', [$key => 'secret']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenPayloadKeys(): array
    {
        return [
            'sms_provider' => ['sms_provider'],
            'sender' => ['sender'],
            'smtp_host' => ['smtp_host'],
            'phone' => ['phone'],
            'email' => ['email'],
        ];
    }
}
