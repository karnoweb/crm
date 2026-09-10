<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Convenience facade. Dependency-injected services remain the primary API.
 *
 * @method static mixed                                             config(string $key, mixed $default = null)
 * @method static \Karnoweb\Crm\Services\LeadService                leads()
 * @method static \Karnoweb\Crm\Services\LeadConversionService      conversions()
 * @method static \Karnoweb\Crm\Services\InteractionRecorderService interactions()
 * @method static \Karnoweb\Crm\Services\LeadMetricsService         metrics()
 * @method static \Karnoweb\Crm\Services\AffinityScoringService     scoring()
 * @method static \Karnoweb\Crm\Services\PipelineService            pipelines()
 * @method static \Karnoweb\Crm\Services\DealService                deals()
 * @method static \Karnoweb\Crm\Services\ActivityService            activities()
 * @method static \Karnoweb\Crm\Services\FollowupService            followups()
 * @method static \Karnoweb\Crm\Services\TaskService                tasks()
 * @method static \Karnoweb\Crm\Services\SegmentEvaluationService   segments()
 * @method static \Karnoweb\Crm\Services\CampaignService            campaigns()
 * @method static \Karnoweb\Crm\Services\CrmReportService           reports()
 * @method static \Karnoweb\Crm\Services\AttributionService         attributions()
 * @method static \Karnoweb\Crm\Services\LeadMergeService           merges()
 *
 * @mixin \Karnoweb\Crm\Crm
 *
 * @see \Karnoweb\Crm\Crm
 */
class Crm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'crm';
    }
}
