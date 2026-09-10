<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Exceptions\InvalidStageTransitionException;
use Karnoweb\Crm\Exceptions\TerminalDealException;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\PipelineStage;
use Karnoweb\Crm\Support\StageTransitionValidator;
use PHPUnit\Framework\TestCase;

final class StageTransitionValidatorTest extends TestCase
{
    public function test_move_rejects_terminal_deals_and_non_open_stages(): void
    {
        $validator = new StageTransitionValidator;
        $deal = new Deal(['status' => DealStatus::Won, 'pipeline_id' => 1]);
        $open = new PipelineStage(['pipeline_id' => 1, 'type' => PipelineStageType::Open]);

        $this->expectException(TerminalDealException::class);
        $validator->assertCanMove($deal, $open);
    }

    public function test_move_rejects_a_stage_from_another_pipeline(): void
    {
        $validator = new StageTransitionValidator;
        $deal = new Deal(['status' => DealStatus::Open, 'pipeline_id' => 1]);
        $stage = new PipelineStage(['pipeline_id' => 2, 'type' => PipelineStageType::Open]);

        $this->expectException(InvalidStageTransitionException::class);
        $validator->assertCanMove($deal, $stage);
    }
}
