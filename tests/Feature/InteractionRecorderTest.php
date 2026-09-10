<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Events\AffinityThresholdReached;
use Karnoweb\Crm\Events\InteractionRecorded;
use Karnoweb\Crm\Exceptions\InteractionImmutableException;
use Karnoweb\Crm\Exceptions\InvalidWeightException;
use Karnoweb\Crm\Exceptions\LeadNotFoundException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Models\Interest;
use Karnoweb\Crm\Tests\TestCase;
use RuntimeException;

final class InteractionRecorderTest extends TestCase
{
    public function test_record_inserts_interaction_and_builds_projections(): void
    {
        Event::fake([InteractionRecorded::class, AffinityThresholdReached::class]);
        $lead = $this->makeLead();

        $interaction = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 2.0,
            source: 'commerce',
            sourceId: 'order:152',
            idempotencyKey: 'commerce:order:152:purchased',
            subjectLabel: 'SKU 1',
        );

        $lead->refresh();
        $interest = Interest::query()->where('lead_id', $lead->id)->first();

        $this->assertSame(1, Interaction::query()->count());
        $this->assertSame('purchased', $interaction->type);
        $this->assertSame(2.0, $interaction->weight);
        $this->assertEqualsWithDelta(10.0, (float) $interest?->score, 0.0001);
        $this->assertSame(1, $interest?->interactions_count);
        $this->assertSame(10, $lead->score);
        $this->assertSame(1, $lead->total_interactions);
        $this->assertNotNull($lead->first_seen_at);
        $this->assertNotNull($lead->last_interaction_at);
        Event::assertDispatchedTimes(InteractionRecorded::class, 1);
    }

    public function test_idempotent_replay_returns_existing_row_without_rescoring_or_event(): void
    {
        Event::fake([InteractionRecorded::class]);
        $lead = $this->makeLead();

        $first = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'clicked',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 1.0,
            source: 'web',
            idempotencyKey: 'stable-key',
        );

        $lead->refresh();
        $score = $lead->score;

        $second = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'clicked',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 99.0,
            source: 'web',
            idempotencyKey: 'stable-key',
        );

        $lead->refresh();

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Interaction::query()->count());
        $this->assertSame(1.0, $second->weight);
        $this->assertSame($score, $lead->score);
        Event::assertDispatchedTimes(InteractionRecorded::class, 1);
    }

    public function test_unknown_type_is_persisted_with_zero_score_impact(): void
    {
        $lead = $this->makeLead();

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'webinar_attended',
            subjectGroup: 'course',
            subjectKey: 'c1',
            weight: 8.0,
            source: 'lms',
            idempotencyKey: 'lms:c1:webinar',
        );

        $lead->refresh();
        $interest = Interest::query()->where('lead_id', $lead->id)->first();

        $this->assertSame(1, Interaction::query()->count());
        $this->assertSame(0.0, $interest?->score);
        $this->assertSame(0, $lead->score);
        $this->assertSame(1, $lead->total_interactions);
    }

    public function test_nan_and_infinite_weights_are_rejected_before_insert(): void
    {
        $lead = $this->makeLead();

        try {
            Crm::interactions()->record(
                leadId: $lead->id,
                type: 'viewed',
                subjectGroup: 'product',
                subjectKey: 'sku-1',
                weight: NAN,
                source: 'web',
                idempotencyKey: 'nan',
            );
            $this->fail('NaN must be rejected');
        } catch (InvalidWeightException) {
            $this->assertSame(0, Interaction::query()->count());
        }

        $this->expectException(InvalidWeightException::class);
        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: INF,
            source: 'web',
            idempotencyKey: 'inf',
        );
    }

    public function test_missing_lead_fails_without_insert(): void
    {
        $this->expectException(LeadNotFoundException::class);

        Crm::interactions()->record(
            leadId: 999,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            source: 'web',
            idempotencyKey: 'missing-lead',
        );
    }

    public function test_interaction_is_immutable(): void
    {
        $lead = $this->makeLead();
        $interaction = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            source: 'web',
            idempotencyKey: 'imm',
        );

        $this->expectException(InteractionImmutableException::class);
        $interaction->update(['weight' => 8]);
    }

    public function test_metadata_is_stored_and_not_used_for_scoring(): void
    {
        $lead = $this->makeLead();

        $interaction = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 1.0,
            source: 'web',
            idempotencyKey: 'meta',
            metadata: ['preferred_language' => 'fa', 'score_override' => 999],
        );

        $this->assertSame('fa', $interaction->metadata['preferred_language']);
        $this->assertSame(1, $lead->fresh()->score);
    }

    public function test_old_interaction_decays_relative_to_a_fresh_one(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');
        $lead = $this->makeLead();

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 1.0,
            source: 'web',
            idempotencyKey: 'old',
            occurredAt: Carbon::parse('2026-06-27 12:00:00'),
        );

        $oldScore = Interest::query()->where('lead_id', $lead->id)->value('score');

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 1.0,
            source: 'web',
            idempotencyKey: 'new',
        );

        $combined = Interest::query()->where('lead_id', $lead->id)->value('score');

        $this->assertLessThan(1.0, (float) $oldScore);
        $this->assertGreaterThan((float) $oldScore, (float) $combined);
        Carbon::setTestNow();
    }

    public function test_affinity_threshold_event_fires_once_on_crossing(): void
    {
        Event::fake([AffinityThresholdReached::class]);
        config()->set('crm.scoring.thresholds.affinity_alert', 20);
        $lead = $this->makeLead();

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 2.0,
            source: 'commerce',
            idempotencyKey: 'p1',
        );

        Event::assertNotDispatched(AffinityThresholdReached::class);

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 3.0,
            source: 'commerce',
            idempotencyKey: 'p2',
        );

        Event::assertDispatchedTimes(AffinityThresholdReached::class, 1);

        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 1.0,
            source: 'commerce',
            idempotencyKey: 'p3',
        );

        Event::assertDispatchedTimes(AffinityThresholdReached::class, 1);
    }

    public function test_interaction_recorded_is_not_published_on_rollback(): void
    {
        Event::fake([InteractionRecorded::class]);
        $lead = $this->makeLead();

        try {
            DB::transaction(function () use ($lead): void {
                Crm::interactions()->record(
                    leadId: $lead->id,
                    type: 'clicked',
                    subjectGroup: 'product',
                    subjectKey: 'sku-1',
                    source: 'web',
                    idempotencyKey: 'rollback',
                );

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Interaction::query()->count());
        Event::assertNotDispatched(InteractionRecorded::class);
    }

    public function test_unique_index_exists_on_lead_id_and_idempotency_key(): void
    {
        $indexes = Schema::getIndexes('crm_interactions');
        $found = false;

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) === true && $index['columns'] === ['lead_id', 'idempotency_key']) {
                $found = true;
            }
        }

        $this->assertTrue($found);
    }

    public function test_recalculate_rebuilds_projections_from_interactions(): void
    {
        $lead = $this->makeLead();
        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 2.0,
            source: 'commerce',
            idempotencyKey: 'rebuild',
        );

        Interest::query()->where('lead_id', $lead->id)->update(['score' => 0]);
        $lead->forceFill(['score' => 0, 'total_interactions' => 0])->save();

        Crm::scoring()->recalculateLead($lead->fresh());
        $lead->refresh();

        $this->assertSame(10, $lead->score);
        $this->assertSame(1, $lead->total_interactions);
        $this->assertEqualsWithDelta(10.0, (float) Interest::query()->where('lead_id', $lead->id)->value('score'), 0.0001);
    }
}
