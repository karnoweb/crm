<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Support\QueryExceptionClassifier;
use Karnoweb\Crm\Tests\TestCase;

/**
 * SQLite :memory: cannot share a schema across OS processes on this Windows runner.
 * This test forces the real unique-violation catch path with a committed peer insert
 * that races the recorder's insert-first contract.
 */
final class InteractionRecorderConcurrencyTest extends TestCase
{
    public function test_insert_first_unique_violation_replays_existing_row(): void
    {
        $lead = $this->makeLead();

        Interaction::query()->create([
            'lead_id' => $lead->id,
            'type' => 'clicked',
            'subject_group' => 'product',
            'subject_key' => 'sku-1',
            'weight' => 1,
            'source' => 'web',
            'idempotency_key' => 'race-key',
            'occurred_at' => now(),
        ]);

        $before = Interaction::query()->count();

        $replayed = Crm::interactions()->record(
            leadId: $lead->id,
            type: 'clicked',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 50,
            source: 'web',
            idempotencyKey: 'race-key',
        );

        $this->assertSame($before, Interaction::query()->count());
        $this->assertSame(1.0, $replayed->weight);
        $this->assertSame('race-key', $replayed->idempotency_key);
    }

    public function test_direct_duplicate_insert_is_a_unique_violation_on_the_composite_key(): void
    {
        $lead = $this->makeLead();

        $payload = [
            'lead_id' => $lead->id,
            'type' => 'clicked',
            'subject_group' => 'product',
            'subject_key' => 'sku-1',
            'weight' => 1,
            'source' => 'web',
            'idempotency_key' => 'db-unique',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_interactions')->insert($payload);

        try {
            DB::table('crm_interactions')->insert($payload);
            $this->fail('Duplicate (lead_id, idempotency_key) must fail at the database.');
        } catch (QueryException $e) {
            $this->assertTrue(QueryExceptionClassifier::isUniqueViolationOn($e, 'lead_id', 'idempotency_key'));
        }
    }
}
