<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Facades\File;
use Karnoweb\Crm\CrmServiceProvider;
use Karnoweb\Crm\Tests\TestCase;

final class MigrationPublishTest extends TestCase
{
    public function test_migrations_are_publishable_under_stable_tag(): void
    {
        $publishes = CrmServiceProvider::pathsToPublish(
            CrmServiceProvider::class,
            'crm-migrations'
        );

        $this->assertSame([database_path('migrations')], array_values($publishes));
        $this->assertDirectoryExists(array_key_first($publishes));
    }

    public function test_vendor_publish_keeps_source_migration_filenames(): void
    {
        $sourceDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $sourceBasenames = $this->phpMigrationBasenames($sourceDir);

        $this->assertSame([
            '2026_01_01_000001_create_crm_leads_table.php',
            '2026_01_01_000002_create_crm_interactions_table.php',
            '2026_01_01_000003_create_crm_interests_table.php',
            '2026_01_01_000004_create_crm_pipelines_table.php',
            '2026_01_01_000005_create_crm_deals_table.php',
            '2026_01_01_000006_create_crm_activities_notes_followups_tables.php',
            '2026_01_01_000007_create_crm_segments_tables.php',
            '2026_01_01_000008_create_crm_campaigns_tables.php',
            '2026_08_29_100001_add_task_columns_to_crm_followups_table.php',
            '2026_08_29_100002_add_stage_entered_at_to_crm_deals_table.php',
            '2026_08_29_200001_create_crm_attribution_tables.php',
        ], $sourceBasenames);

        $this->artisan('vendor:publish', [
            '--provider' => CrmServiceProvider::class,
            '--tag' => 'crm-migrations',
            '--force' => true,
        ])->assertSuccessful();

        $publishedDir = database_path('migrations');

        try {
            $publishedBasenames = $this->phpMigrationBasenames($publishedDir);
            $publishedFromPackage = array_values(array_intersect($sourceBasenames, $publishedBasenames));

            $this->assertSame(
                $sourceBasenames,
                $publishedFromPackage,
                'vendor:publish --tag=crm-migrations must copy migrations with their original basenames.'
            );

            foreach ($sourceBasenames as $basename) {
                $this->assertFileExists($publishedDir.DIRECTORY_SEPARATOR.$basename);
            }
        } finally {
            foreach ($sourceBasenames as $basename) {
                File::delete($publishedDir.DIRECTORY_SEPARATOR.$basename);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function phpMigrationBasenames(string $directory): array
    {
        return collect(File::files($directory))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn (string $name) => str_ends_with($name, '.php'))
            ->sort()
            ->values()
            ->all();
    }
}
