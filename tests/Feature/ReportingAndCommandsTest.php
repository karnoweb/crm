<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Events\FollowupDue;
use Karnoweb\Crm\Events\LeadSegmentChanged;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Interest;
use Karnoweb\Crm\Models\Segment;
use Karnoweb\Crm\Tests\TestCase;

final class ReportingAndCommandsTest extends TestCase
{
    public function test_reports_survive_projection_rebuild_and_include_won_lost(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');
        $lead = Crm::leads()->create(['name' => 'R']);
        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'purchased',
            subjectGroup: 'product',
            subjectKey: 'sku-1',
            weight: 2,
            source: 'commerce',
            idempotencyKey: 'rep-1',
        );
        Crm::conversions()->convert($lead, 900);

        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'S'], ['New']);
        $open = $pipeline->stages->firstWhere('type', PipelineStageType::Open);
        $deal = Crm::deals()->create(['lead_id' => $lead->id, 'pipeline_stage_id' => $open->id, 'value' => 50]);
        Crm::deals()->win($deal);

        $before = Crm::reports()->topInterests('product', 10);
        Interest::query()->delete();
        $lead->forceFill(['score' => 0])->save();
        $after = Crm::reports()->topInterests('product', 10);

        $this->assertEqualsWithDelta((float) $before->first()->score, (float) $after->first()->score, 0.0001);
        $this->assertSame(1, Crm::reports()->conversionFunnel()['converted']);
        $this->assertSame(1, Crm::reports()->conversionFunnel()['interactions']);
        $this->assertSame(1, Crm::reports()->winLossSummary($pipeline->id)['won']);
        Carbon::setTestNow();
    }

    public function test_commands_are_idempotent(): void
    {
        Event::fake([FollowupDue::class, LeadSegmentChanged::class]);
        $lead = Crm::leads()->create(['name' => 'Cmd']);
        Crm::interactions()->record(
            leadId: $lead->id,
            type: 'viewed',
            subjectGroup: 'product',
            subjectKey: 'sku-9',
            source: 'web',
            idempotencyKey: 'cmd-1',
        );
        $segment = Segment::query()->create(['name' => 'Dyn', 'is_dynamic' => true]);
        Crm::followups()->schedule($lead, Carbon::now()->subMinute());

        $this->artisan('crm:recalculate-affinity')->assertSuccessful();
        $this->artisan('crm:recalculate-affinity')->assertSuccessful();
        $this->artisan('crm:refresh-dynamic-segments')->assertSuccessful();
        $this->artisan('crm:refresh-dynamic-segments')->assertSuccessful();
        $this->assertNotNull($segment->fresh()->last_evaluated_at);
        Event::assertDispatchedTimes(LeadSegmentChanged::class, 1);

        Artisan::call('crm:process-followup-reminders');
        Artisan::call('crm:process-followup-reminders');
        Event::assertDispatchedTimes(FollowupDue::class, 1);
    }
}
