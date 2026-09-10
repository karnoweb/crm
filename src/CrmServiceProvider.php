<?php

declare(strict_types=1);

namespace Karnoweb\Crm;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Crm\Console\Commands\ProcessFollowupRemindersCommand;
use Karnoweb\Crm\Console\Commands\RecalculateAffinityScoresCommand;
use Karnoweb\Crm\Console\Commands\RefreshDynamicSegmentsCommand;
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
use Karnoweb\Crm\Support\AffinityDecayCalculator;
use Karnoweb\Crm\Support\AttributionWindowPolicy;
use Karnoweb\Crm\Support\CampaignRecipientStatusRank;
use Karnoweb\Crm\Support\CrmMorphMap;
use Karnoweb\Crm\Support\InteractionValidator;
use Karnoweb\Crm\Support\ScoringPolicy;
use Karnoweb\Crm\Support\SegmentRuleEvaluator;
use Karnoweb\Crm\Support\StageTransitionValidator;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/crm.php', 'crm');

        $this->app->singleton(ScoringPolicy::class);
        $this->app->singleton(AffinityDecayCalculator::class);
        $this->app->singleton(AttributionWindowPolicy::class);
        $this->app->singleton(InteractionValidator::class);
        $this->app->singleton(AffinityScoringService::class);
        $this->app->singleton(InteractionRecorderService::class);
        $this->app->singleton(LeadMetricsService::class);
        $this->app->singleton(LeadService::class);
        $this->app->singleton(LeadConversionService::class);
        $this->app->singleton(StageTransitionValidator::class);
        $this->app->singleton(PipelineService::class);
        $this->app->singleton(DealService::class);
        $this->app->singleton(ActivityService::class);
        $this->app->singleton(TaskService::class);
        $this->app->singleton(FollowupService::class);
        $this->app->singleton(SegmentRuleEvaluator::class);
        $this->app->singleton(SegmentEvaluationService::class);
        $this->app->singleton(CampaignRecipientStatusRank::class);
        $this->app->singleton(CampaignService::class);
        $this->app->singleton(AttributionService::class);
        $this->app->singleton(LeadMergeService::class);
        $this->app->singleton(CrmReportService::class);

        $this->app->singleton('crm', fn ($app) => new Crm(
            $app->make(LeadService::class),
            $app->make(LeadConversionService::class),
            $app->make(InteractionRecorderService::class),
            $app->make(LeadMetricsService::class),
            $app->make(AffinityScoringService::class),
            $app->make(PipelineService::class),
            $app->make(DealService::class),
            $app->make(ActivityService::class),
            $app->make(FollowupService::class),
            $app->make(TaskService::class),
            $app->make(SegmentEvaluationService::class),
            $app->make(CampaignService::class),
            $app->make(CrmReportService::class),
            $app->make(AttributionService::class),
            $app->make(LeadMergeService::class),
        ));
        $this->app->singleton(Crm::class, fn ($app) => $app->make('crm'));
    }

    public function boot(): void
    {
        CrmMorphMap::register();

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'crm');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecalculateAffinityScoresCommand::class,
                ProcessFollowupRemindersCommand::class,
                RefreshDynamicSegmentsCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/crm.php' => config_path('crm.php'),
            ], 'crm-config');

            $this->publishes([
                __DIR__ . '/../lang' => lang_path('vendor/crm'),
            ], 'crm-lang');
        }
    }
}
