<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Events\LeadCreated;
use Karnoweb\Crm\Exceptions\CustomerTerminalException;
use Karnoweb\Crm\Exceptions\InvalidLeadStatusTransitionException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Tests\TestCase;
use RuntimeException;

final class LeadServiceTest extends TestCase
{
    public function test_create_publishes_lead_created_after_commit_and_leaves_last_status_change_null(): void
    {
        Event::fake([LeadCreated::class]);

        $lead = Crm::leads()->create([
            'name' => 'Sara',
            'source' => 'web',
        ]);

        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNull($lead->last_status_change_at);
        $this->assertNull($lead->user_id);
        Event::assertDispatched(LeadCreated::class, fn (LeadCreated $event) => $event->leadId === $lead->id);
    }

    public function test_create_without_captured_at_persists_a_non_null_captured_at(): void
    {
        $lead = Crm::leads()->create([
            'name' => 'No Capture Time',
            'source' => 'web',
        ]);

        $lead->refresh();

        $this->assertNotNull($lead->captured_at);
        $this->assertNotNull($lead->getRawOriginal('captured_at'));
    }

    public function test_lead_created_is_not_published_on_rollback(): void
    {
        Event::fake([LeadCreated::class]);

        try {
            DB::transaction(function (): void {
                Crm::leads()->create(['name' => 'Rollback']);

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Lead::query()->count());
        Event::assertNotDispatched(LeadCreated::class);
    }

    public function test_first_or_create_for_user_is_idempotent_and_unique(): void
    {
        Event::fake([LeadCreated::class]);

        $first = Crm::leads()->firstOrCreateForUser(88);
        $second = Crm::leads()->firstOrCreateForUser(88);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Lead::query()->where('user_id', 88)->count());
        $this->assertSame(LeadStatus::Customer, $first->status);
        $this->assertSame('system', $first->source);
        Event::assertDispatchedTimes(LeadCreated::class, 1);
    }

    public function test_first_or_create_race_returns_the_existing_row(): void
    {
        Event::fake([LeadCreated::class]);
        $existing = Crm::leads()->firstOrCreateForUser(77);

        $replayed = Crm::leads()->firstOrCreateForUser(77);

        $this->assertTrue($existing->is($replayed));
        $this->assertSame(1, Lead::query()->count());
        Event::assertDispatchedTimes(LeadCreated::class, 1);
    }

    public function test_archive_is_idempotent_and_does_not_delete(): void
    {
        $lead = Crm::leads()->create(['name' => 'Keep']);

        $first = Crm::leads()->archive($lead);
        $second = Crm::leads()->archive($first);

        $this->assertNotNull($first->archived_at);
        $this->assertTrue($first->archived_at->equalTo($second->archived_at));
        $this->assertTrue(Lead::query()->whereKey($lead->id)->exists());
    }

    public function test_top_interests_orders_by_score(): void
    {
        $lead = Crm::leads()->create(['name' => 'Buyer']);

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'low',
            source: 'web',
            idempotencyKey: 'low',
        );
        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'high',
            source: 'commerce',
            idempotencyKey: 'high',
        );

        $top = Crm::leads()->topInterests($lead->id, 'product', 5);

        $this->assertSame('high', $top->first()?->subject_key);
    }

    public function test_customer_status_cannot_be_set_via_change_status(): void
    {
        $lead = Crm::leads()->create(['name' => 'Prospect']);

        $this->expectException(InvalidLeadStatusTransitionException::class);
        Crm::leads()->changeStatus($lead, LeadStatus::Customer);
    }

    public function test_customer_status_cannot_transition_out(): void
    {
        $lead = Crm::leads()->firstOrCreateForUser(12);

        $this->expectException(CustomerTerminalException::class);
        Crm::leads()->changeStatus($lead, LeadStatus::Lost);
    }

    public function test_last_status_change_at_is_set_only_on_real_change(): void
    {
        $lead = Crm::leads()->create(['name' => 'Movable']);
        $this->assertNull($lead->last_status_change_at);

        $updated = Crm::leads()->changeStatus($lead, LeadStatus::Contacted);
        $this->assertNotNull($updated->last_status_change_at);

        $same = Crm::leads()->changeStatus($updated, LeadStatus::Contacted);
        $this->assertTrue($updated->last_status_change_at->equalTo($same->last_status_change_at));
    }
}
