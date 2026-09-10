<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Enums\PipelineStageType;
use Karnoweb\Crm\Exceptions\InvalidStageTransitionException;
use Karnoweb\Crm\Exceptions\TerminalDealException;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\PipelineStage;

final class StageTransitionValidator
{
    public function assertCanMove(Deal $deal, PipelineStage $stage): void
    {
        if ($deal->status->isTerminal()) {
            throw new TerminalDealException('moveStage() is forbidden on a won or lost Deal.');
        }

        if ((int) $stage->pipeline_id !== (int) $deal->pipeline_id) {
            throw new InvalidStageTransitionException('Stage does not belong to the Deal pipeline.');
        }

        if ($stage->type !== PipelineStageType::Open) {
            throw new InvalidStageTransitionException('moveStage() may only target open stages.');
        }
    }

    public function assertWin(Deal $deal, PipelineStage $wonStage): void
    {
        if ($deal->status === DealStatus::Lost) {
            throw new TerminalDealException('A lost Deal cannot be won.');
        }

        if ($wonStage->type !== PipelineStageType::Won) {
            throw new InvalidStageTransitionException('win() requires a stage of type won.');
        }

        if ((int) $wonStage->pipeline_id !== (int) $deal->pipeline_id) {
            throw new InvalidStageTransitionException('Won stage does not belong to the Deal pipeline.');
        }
    }

    public function assertLose(Deal $deal, PipelineStage $lostStage): void
    {
        if ($deal->status === DealStatus::Won) {
            throw new TerminalDealException('A won Deal cannot be lost.');
        }

        if ($lostStage->type !== PipelineStageType::Lost) {
            throw new InvalidStageTransitionException('lose() requires a stage of type lost.');
        }

        if ((int) $lostStage->pipeline_id !== (int) $deal->pipeline_id) {
            throw new InvalidStageTransitionException('Lost stage does not belong to the Deal pipeline.');
        }
    }
}
