<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Events\LeadConverted;
use Karnoweb\Crm\Exceptions\CustomerTerminalException;
use Karnoweb\Crm\Exceptions\LeadAlreadyLinkedException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Tests\TestCase;
use RuntimeException;

final class LeadConversionServiceTest extends TestCase
{
    public function test_convert_links_user_and_sets_customer_terminal_state(): void
    {
        Event::fake([LeadConverted::class]);
        $lead = Crm::leads()->create(['name' => 'Prospect']);

        $converted = Crm::conversions()->convert($lead, 501);

        $this->assertSame(LeadStatus::Customer, $converted->status);
        $this->assertSame(501, (int) $converted->user_id);
        $this->assertNotNull($converted->converted_at);
        $this->assertNotNull($converted->last_status_change_at);
        Event::assertDispatchedTimes(LeadConverted::class, 1);
    }

    public function test_convert_is_idempotent_for_the_same_user(): void
    {
        Event::fake([LeadConverted::class]);
        $lead = Crm::leads()->create(['name' => 'Prospect']);

        $first = Crm::conversions()->convert($lead, 502);
        $second = Crm::conversions()->convert($first, 502);

        $this->assertTrue($first->is($second));
        $this->assertTrue($first->converted_at->equalTo($second->converted_at));
        Event::assertDispatchedTimes(LeadConverted::class, 1);
    }

    public function test_convert_does_not_publish_on_rollback(): void
    {
        Event::fake([LeadConverted::class]);
        $lead = Crm::leads()->create(['name' => 'Prospect']);

        try {
            DB::transaction(function () use ($lead): void {
                Crm::conversions()->convert($lead, 503);

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $lead->refresh();
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNull($lead->user_id);
        Event::assertNotDispatched(LeadConverted::class);
    }

    public function test_convert_rejects_rebinding_a_customer_to_another_user(): void
    {
        $lead = Crm::leads()->create(['name' => 'Prospect']);
        $converted = Crm::conversions()->convert($lead, 504);

        $this->expectException(CustomerTerminalException::class);
        Crm::conversions()->convert($converted, 505);
    }

    public function test_convert_rejects_a_user_already_linked_to_another_lead(): void
    {
        Crm::leads()->firstOrCreateForUser(600);
        $other = Crm::leads()->create(['name' => 'Other']);

        $this->expectException(LeadAlreadyLinkedException::class);
        Crm::conversions()->convert($other, 600);
    }
}
