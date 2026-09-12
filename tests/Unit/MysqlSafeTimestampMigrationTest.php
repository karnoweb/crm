<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MysqlSafeTimestampMigrationTest extends TestCase
{
    public function test_timestamp_columns_use_a_mysql_safe_modifier(): void
    {
        $migrationsDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $files = glob($migrationsDir.DIRECTORY_SEPARATOR.'*.php') ?: [];

        $this->assertNotEmpty($files, 'Expected CRM package migrations to exist.');

        $unsafe = [];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);

            if (! preg_match_all(
                '/\$table->timestamp\s*\(([^;]*);/s',
                $contents,
                $matches,
                PREG_SET_ORDER
            )) {
                continue;
            }

            foreach ($matches as $match) {
                $statement = '$table->timestamp('.$match[1].';';

                if ($this->hasMysqlSafeModifier($statement)) {
                    continue;
                }

                $unsafe[] = basename($file).': '.trim((string) preg_replace('/\s+/', ' ', $statement));
            }
        }

        $this->assertSame(
            [],
            $unsafe,
            'TIMESTAMP NOT NULL without DEFAULT is rejected by MySQL when sql_mode includes NO_ZERO_DATE. Chain nullable(), useCurrent(), useCurrentOnUpdate(), or default(...) — or use dateTime() for required business times.'
        );
    }

    private function hasMysqlSafeModifier(string $statement): bool
    {
        return (bool) preg_match(
            '/->(?:nullable|useCurrent|useCurrentOnUpdate|default)\s*\(/',
            $statement
        );
    }
}
