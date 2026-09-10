<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\CrmServiceProvider;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            CrmServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('crm.tables.prefix', 'crm_');
        $app['config']->set('crm.models.user', User::class);
        $app['config']->set('crm.keys.user_key_type', 'int');
        $app['config']->set('crm.keys.branch_key_type', null);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function makeLead(array $attributes = []): Lead
    {
        return Lead::query()->create(array_merge([
            'status' => LeadStatus::New,
            'source' => 'test',
            'captured_at' => now(),
            'score' => 0,
            'total_interactions' => 0,
        ], $attributes));
    }
}
