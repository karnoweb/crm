<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Events\DealLost;
use Karnoweb\Crm\Events\DealStageChanged;
use Karnoweb\Crm\Events\DealWon;
use Karnoweb\Crm\Exceptions\InvalidPipelineException;
use Karnoweb\Crm\Exceptions\InvalidStageTransitionException;
use Karnoweb\Crm\Exceptions\TerminalDealException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\Pipeline;
use Karnoweb\Crm\Models\PipelineStage;
use Karnoweb\Crm\Tests\TestCase;
use RuntimeException;

final class PipelineDealTest extends TestCase
{
    public function test_pipeline_is_created_atomically_with_one_won_and_one_lost_stage(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(
            ['name' => 'Sales'],
            ['New', 'Qualified'],
        );

        $types = $pipeline->stages->pluck('type')->all();

        $this->assertCount(4, $pipeline->stages);
        $this->assertSame(2, $pipeline->stages->where('type', PipelineStageType::Open)->count());
        $this->assertSame(1, $pipeline->stages->where('type', PipelineStageType::Won)->count());
        $this->assertSame(1, $pipeline->stages->where('type', PipelineStageType::Lost)->count());
        $this->assertNotContains(null, $types);
    }

    public function test_pipeline_rollback_leaves_no_partial_rows(): void
    {
        try {
            DB::transaction(function (): void {
                Crm::pipelines()->createWithDefaultStages(['name' => 'Broken'], ['A']);

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Pipeline::query()->count());
        $this->assertSame(0, PipelineStage::query()->count());
    }

    public function test_second_won_or_lost_stage_is_rejected_under_lock(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Sales'], ['New']);

        $this->expectException(InvalidPipelineException::class);
        Crm::pipelines()->addStage($pipeline, 'Won 2', PipelineStageType::Won);
    }

    public function test_deal_lifecycle_and_idempotent_win_lose(): void
    {
        Event::fake([DealWon::class, DealLost::class, DealStageChanged::class]);
        [$lead, $pipeline, $openA, $openB] = $this->pipelineFixture();

        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $openA->id,
            'value' => 1000,
        ]);

        $moved = Crm::deals()->moveStage($deal, $openB);
        $this->assertSame($openB->id, $moved->pipeline_stage_id);
        Event::assertDispatchedTimes(DealStageChanged::class, 1);

        $won = Crm::deals()->win($moved);
        $again = Crm::deals()->win($won);

        $this->assertSame(DealStatus::Won, $again->status);
        $this->assertSame(PipelineStageType::Won, $again->stage->type);
        Event::assertDispatchedTimes(DealWon::class, 1);

        $this->expectException(TerminalDealException::class);
        Crm::deals()->moveStage($again, $openA);
    }

    public function test_lose_is_idempotent_and_terminal(): void
    {
        Event::fake([DealLost::class]);
        [$lead, , $openA] = $this->pipelineFixture();

        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $openA->id,
        ]);

        $lost = Crm::deals()->lose($deal, 'budget');
        $again = Crm::deals()->lose($lost, 'ignored');

        $this->assertSame(DealStatus::Lost, $again->status);
        $this->assertSame('budget', $again->lost_reason);
        Event::assertDispatchedTimes(DealLost::class, 1);
    }

    public function test_cannot_create_deal_on_terminal_stage(): void
    {
        [$lead, $pipeline] = $this->pipelineFixture();
        $won = $pipeline->stageOfType(PipelineStageType::Won);

        $this->expectException(InvalidStageTransitionException::class);
        Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $won?->id,
        ]);
    }

    public function test_win_event_is_not_published_on_rollback(): void
    {
        Event::fake([DealWon::class]);
        [$lead, , $openA] = $this->pipelineFixture();
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $openA->id,
        ]);

        try {
            DB::transaction(function () use ($deal): void {
                Crm::deals()->win($deal);

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(DealStatus::Open, $deal->fresh()->status);
        Event::assertNotDispatched(DealWon::class);
    }

    public function test_no_deal_observer_exists(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/src/Observers/DealObserver.php');
    }

    public function test_won_and_lost_cannot_cross_into_each_other(): void
    {
        [$lead, , $openA] = $this->pipelineFixture();
        $wonDeal = Crm::deals()->create(['lead_id' => $lead->id, 'pipeline_stage_id' => $openA->id]);
        Crm::deals()->win($wonDeal);

        try {
            Crm::deals()->lose($wonDeal->fresh(), 'too late');
            $this->fail('won Deal must not lose');
        } catch (TerminalDealException) {
            $this->assertSame(DealStatus::Won, $wonDeal->fresh()->status);
        }

        $lostDeal = Crm::deals()->create(['lead_id' => $lead->id, 'pipeline_stage_id' => $openA->id]);
        Crm::deals()->lose($lostDeal, 'no budget');

        $this->expectException(TerminalDealException::class);
        Crm::deals()->win($lostDeal->fresh());
    }

    /**
     * @return array{0: Lead, 1: Pipeline, 2: PipelineStage, 3: PipelineStage}
     */
    private function pipelineFixture(): array
    {
        $lead = Crm::leads()->create(['name' => 'Deal Lead']);
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Sales'], ['New', 'Proposal']);
        $opens = $pipeline->stages->where('type', PipelineStageType::Open)->values();

        return [$lead, $pipeline, $opens[0], $opens[1]];
    }
}
