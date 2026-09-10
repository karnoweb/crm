<?php

declare(strict_types=1);

namespace Karnoweb\Crm;

use Karnoweb\Crm\Services\ActivityService;
use Karnoweb\Crm\Services\AffinityScoringService;
use Karnoweb\Crm\Services\AttributionService;
use Karnoweb\Crm\Services\CampaignService;
use Karnoweb\Crm\Services\CrmReportService;
use Karnoweb\Crm\Services\DealService;
use Karnoweb\Crm\Services\FollowupService;
use Karnoweb\Crm\Services\InteractionRecorderService;
use Karnoweb\Crm\Services\LeadConversionService;
use Karnoweb\Crm\Services\LeadMergeService;
use Karnoweb\Crm\Services\LeadMetricsService;
use Karnoweb\Crm\Services\LeadService;
use Karnoweb\Crm\Services\PipelineService;
use Karnoweb\Crm\Services\SegmentEvaluationService;
use Karnoweb\Crm\Services\TaskService;

/**
 * Thin manager: service delegation only. Dependency-injected services are the primary API.
 */
final class Crm
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly LeadConversionService $conversions,
        private readonly InteractionRecorderService $interactions,
        private readonly LeadMetricsService $metrics,
        private readonly AffinityScoringService $scoring,
        private readonly PipelineService $pipelines,
        private readonly DealService $deals,
        private readonly ActivityService $activities,
        private readonly FollowupService $followups,
        private readonly TaskService $tasks,
        private readonly SegmentEvaluationService $segments,
        private readonly CampaignService $campaigns,
        private readonly CrmReportService $reports,
        private readonly AttributionService $attributions,
        private readonly LeadMergeService $merges,
    ) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return config('crm.' . $key, $default);
    }

    public function leads(): LeadService
    {
        return $this->leads;
    }

    public function conversions(): LeadConversionService
    {
        return $this->conversions;
    }

    public function interactions(): InteractionRecorderService
    {
        return $this->interactions;
    }

    public function metrics(): LeadMetricsService
    {
        return $this->metrics;
    }

    public function scoring(): AffinityScoringService
    {
        return $this->scoring;
    }

    public function pipelines(): PipelineService
    {
        return $this->pipelines;
    }

    public function deals(): DealService
    {
        return $this->deals;
    }

    public function activities(): ActivityService
    {
        return $this->activities;
    }

    public function followups(): FollowupService
    {
        return $this->followups;
    }

    public function tasks(): TaskService
    {
        return $this->tasks;
    }

    public function segments(): SegmentEvaluationService
    {
        return $this->segments;
    }

    public function campaigns(): CampaignService
    {
        return $this->campaigns;
    }

    public function reports(): CrmReportService
    {
        return $this->reports;
    }

    public function attributions(): AttributionService
    {
        return $this->attributions;
    }

    public function merges(): LeadMergeService
    {
        return $this->merges;
    }
}
