<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Crm;
use Karnoweb\Crm\CrmServiceProvider;
use Karnoweb\Crm\Facades\Crm as CrmFacade;
use Karnoweb\Crm\Models\BaseModel;
use Karnoweb\Crm\Services\InteractionRecorderService;
use Karnoweb\Crm\Tests\Fixtures\User;
use Karnoweb\Crm\Tests\TestCase;

final class PackageBootstrapTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(CrmServiceProvider::class));
    }

    public function test_crm_singleton_and_facade_resolve(): void
    {
        $this->assertTrue($this->app->bound('crm'));
        $this->assertSame($this->app->make('crm'), $this->app->make('crm'));
        $this->assertInstanceOf(Crm::class, CrmFacade::getFacadeRoot());
        $this->assertSame('crm_', CrmFacade::config('tables.prefix'));
        $this->assertInstanceOf(InteractionRecorderService::class, CrmFacade::interactions());
        $this->assertSame(
            $this->app->make(InteractionRecorderService::class),
            CrmFacade::interactions()
        );
    }

    public function test_phase_one_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('crm_leads'));
        $this->assertTrue(Schema::hasTable('crm_interactions'));
        $this->assertTrue(Schema::hasTable('crm_interests'));
        $this->assertTrue(Schema::hasColumns('crm_leads', [
            'user_id',
            'branch_id',
            'assigned_to',
            'attributes',
            'last_status_change_at',
        ]));
    }

    public function test_config_is_merged_with_key_strategy_defaults(): void
    {
        $this->assertSame(User::class, config('crm.models.user'));
        $this->assertSame('int', config('crm.keys.user_key_type'));
        $this->assertNull(config('crm.keys.branch_key_type'));
        $this->assertSame(0, config('crm.scoring.default_weight'));
        $this->assertSame(5, config('crm.scoring.weights.purchased'));
    }

    public function test_translations_are_loaded(): void
    {
        $this->assertSame('Customer', __('crm::crm.lead_status.customer'));
    }

    public function test_base_model_prefixes_tables_and_does_not_double_prefix(): void
    {
        $model = new class extends BaseModel {
            protected $table = 'leads';
        };

        $this->assertSame('crm_leads', $model->getTable());
        $this->assertSame('crm_leads', $model->newInstance()->getTable());
    }

    public function test_base_model_does_not_double_prefix_an_already_prefixed_table(): void
    {
        $model = new class extends BaseModel {
            protected $table = 'crm_leads';
        };

        $this->assertSame('crm_leads', $model->getTable());
    }
}
