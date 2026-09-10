<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Tests\TestCase;

final class CrmReportWave4Test extends TestCase
{
    public function test_leads_by_source_and_lead_to_deal_conversion(): void
    {
        Crm::leads()->create(['name' => 'A', 'source' => 'web', 'branch_id' => 1]);
        Crm::leads()->create(['name' => 'B', 'source' => 'web', 'branch_id' => 1]);
        Crm::leads()->create(['name' => 'C', 'source' => 'ads', 'branch_id' => 1]);

        $bySource = Crm::reports()->leadsBySource(branchId: 1);
        $this->assertSame(2, (int) $bySource->firstWhere('source', 'web')->count);

        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'R'], ['P']);
        $lead = Crm::leads()->create(['name' => 'D', 'branch_id' => 1]);
        Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
            'branch_id' => 1,
        ]);

        $conv = Crm::reports()->leadToDealConversion(branchId: 1);
        $this->assertSame(4, $conv['leads']);
        $this->assertSame(1, $conv['deals']);
    }

    public function test_stage_aging_uses_stage_entered_at(): void
    {
        Carbon::setTestNow('2026-08-01 12:00:00');
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Age'], ['A', 'B']);
        $lead = Crm::leads()->create(['name' => 'Aged', 'branch_id' => 1]);
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->orderBy('order')->first()->id,
            'branch_id' => 1,
            'assigned_to' => 5,
        ]);

        Carbon::setTestNow('2026-08-10 12:00:00');
        $aging = Crm::reports()->stageAging(pipelineId: $pipeline->id, branchId: 1);
        $this->assertSame($deal->id, $aging->first()->deal_id);
        $this->assertSame(9, $aging->first()->age_days);

        Crm::tasks()->create($deal, Carbon::now()->subDay(), 5, ['title' => 'Overdue']);
        $workload = Crm::reports()->ownerWorkload(branchId: 1);
        $row = $workload->firstWhere('owner_id', 5);
        $this->assertSame(1, $row->open_deals);
        $this->assertSame(1, $row->open_tasks);
        $this->assertSame(1, $row->overdue_tasks);

        $summary = Crm::reports()->winLossSummary($pipeline->id, 1);
        $this->assertArrayHasKey('win_rate', $summary);
        Carbon::setTestNow();
    }

    public function test_empty_datasets_return_zeros(): void
    {
        $this->assertSame(0, Crm::reports()->leadsBySource()->count());
        $this->assertSame(0.0, Crm::reports()->leadToDealConversion()['conversion_rate']);
        $this->assertSame(0.0, Crm::reports()->attributedRevenue());
    }
}
