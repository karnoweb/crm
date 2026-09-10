<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Events\LeadCreated;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Tests\TestCase;
use RuntimeException;

final class AfterCommitEventTest extends TestCase
{
    public function test_event_is_published_after_successful_commit(): void
    {
        Event::fake([LeadCreated::class]);

        DB::transaction(function (): void {
            CrmEventDispatcher::dispatch(new LeadCreated(1));
            Event::assertNotDispatched(LeadCreated::class);
        });

        Event::assertDispatched(LeadCreated::class, function (LeadCreated $event): bool {
            return $event->leadId === 1;
        });
    }

    public function test_event_is_not_published_when_the_transaction_rolls_back(): void
    {
        Event::fake([LeadCreated::class]);

        try {
            DB::transaction(function (): void {
                CrmEventDispatcher::dispatch(new LeadCreated(2));

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(LeadCreated::class);
    }

    public function test_event_is_published_immediately_when_no_transaction_is_open(): void
    {
        Event::fake([LeadCreated::class]);

        CrmEventDispatcher::dispatch(new LeadCreated(3));

        Event::assertDispatchedTimes(LeadCreated::class, 1);
    }

    public function test_listener_registered_before_dispatch_runs_only_after_commit(): void
    {
        $ran = false;

        Event::listen(LeadCreated::class, function () use (&$ran): void {
            $ran = true;
        });

        DB::transaction(function () use (&$ran): void {
            CrmEventDispatcher::dispatch(new LeadCreated(4));
            $this->assertFalse($ran);
        });

        $this->assertTrue($ran);
    }
}
