<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Tests\TestCase;

final class MigrationFoundationTest extends TestCase
{
    public function test_fresh_install_creates_every_crm_table(): void
    {
        foreach ([
            'crm_leads',
            'crm_interactions',
            'crm_interests',
            'crm_pipelines',
            'crm_pipeline_stages',
            'crm_deals',
            'crm_activities',
            'crm_notes',
            'crm_followups',
            'crm_segments',
            'crm_segment_rules',
            'crm_campaigns',
            'crm_campaign_recipients',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table . ' must exist after migrate');
        }
    }
}
