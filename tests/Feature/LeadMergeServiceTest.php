<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Tests\TestCase;

final class LeadMergeServiceTest extends TestCase
{
    public function test_soft_duplicates_by_phone_and_email(): void
    {
        $a = Crm::leads()->create(['name' => 'A', 'phone' => '09120001111', 'email' => 'a@ex.com']);
        $b = Crm::leads()->create(['name' => 'B', 'phone' => '09120001111']);
        $c = Crm::leads()->create(['name' => 'C', 'email' => 'a@ex.com']);
        Crm::leads()->create(['name' => 'D', 'phone' => '09129999999']);

        $dupes = Crm::merges()->findSoftDuplicates($a);

        $this->assertCount(2, $dupes);
        $ids = array_map(fn ($l) => $l->id, $dupes);
        $this->assertContains($b->id, $ids);
        $this->assertContains($c->id, $ids);
    }

    public function test_merge_moves_deals_interactions_and_archives_secondary(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'M'], ['P']);
        $primary = Crm::leads()->create(['name' => 'Primary', 'branch_id' => 1]);
        $secondary = Crm::leads()->create(['name' => 'Secondary', 'phone' => '09121112233', 'branch_id' => 1]);

        $deal = Crm::deals()->create([
            'lead_id' => $secondary->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
            'branch_id' => 1,
        ]);

        Crm::interactions()->record(
            leadId: $secondary->id,
            type: 'viewed',
            subjectGroup: 'page',
            subjectKey: 'home',
            source: 'web',
            idempotencyKey: 'merge:view:1',
        );

        Crm::merges()->merge($primary, $secondary);

        $this->assertSame($primary->id, (int) $deal->fresh()->lead_id);
        $this->assertSame(1, Interaction::query()->where('lead_id', $primary->id)->count());
        $this->assertNotNull($secondary->fresh()->archived_at);
        $this->assertSame(LeadStatus::Lost, $secondary->fresh()->status);
        $this->assertSame($primary->id, (int) $secondary->fresh()->hostAttributes()->get('merged_into_lead_id'));
    }

    public function test_cannot_merge_leads_with_different_users(): void
    {
        $primary = Crm::leads()->create(['name' => 'P', 'user_id' => 1]);
        $secondary = Crm::leads()->create(['name' => 'S', 'user_id' => 2]);

        $this->expectException(\Karnoweb\Crm\Exceptions\CrmException::class);
        Crm::merges()->merge($primary, $secondary);
    }
}
