<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Enums\CampaignRecipientStatus;
use Karnoweb\Crm\Support\CampaignRecipientStatusRank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CampaignRecipientStatusRankTest extends TestCase
{
    #[DataProvider('advances')]
    public function test_higher_rank_wins(CampaignRecipientStatus $from, CampaignRecipientStatus $to, bool $expected): void
    {
        $this->assertSame($expected, (new CampaignRecipientStatusRank)->shouldAdvance($from, $to));
    }

    /**
     * @return array<string, array{0: CampaignRecipientStatus, 1: CampaignRecipientStatus, 2: bool}>
     */
    public static function advances(): array
    {
        return [
            'pending to requested' => [CampaignRecipientStatus::Pending, CampaignRecipientStatus::DispatchRequested, true],
            'requested to sent' => [CampaignRecipientStatus::DispatchRequested, CampaignRecipientStatus::Sent, true],
            'sent to delivered' => [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Delivered, true],
            'sent to opened' => [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Opened, true],
            'sent to clicked' => [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Clicked, true],
            'delivered to opened' => [CampaignRecipientStatus::Delivered, CampaignRecipientStatus::Opened, true],
            'delivered to clicked' => [CampaignRecipientStatus::Delivered, CampaignRecipientStatus::Clicked, true],
            'opened to clicked' => [CampaignRecipientStatus::Opened, CampaignRecipientStatus::Clicked, true],
            'clicked to opened' => [CampaignRecipientStatus::Clicked, CampaignRecipientStatus::Opened, false],
            'failed stays' => [CampaignRecipientStatus::Failed, CampaignRecipientStatus::Sent, false],
            'delivered cannot fail' => [CampaignRecipientStatus::Delivered, CampaignRecipientStatus::Failed, false],
            'sent can fail' => [CampaignRecipientStatus::Sent, CampaignRecipientStatus::Failed, true],
        ];
    }
}
