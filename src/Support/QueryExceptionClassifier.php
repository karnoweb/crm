<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Database\QueryException;

/**
 * Classifies unique-constraint violations by driver code AND column name.
 *
 * MySQL 1062, PostgreSQL 23505, SQLite 19 / SQLSTATE 23000.
 * Unrelated QueryExceptions must never be treated as idempotent races.
 */
final class QueryExceptionClassifier
{
    public static function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === 19
            || $driverCode === 1062;
    }

    public static function isUniqueViolationOn(QueryException $e, string ...$columns): bool
    {
        if ($columns === [] || ! self::isUniqueViolation($e)) {
            return false;
        }

        $haystack = strtolower($e->getMessage());

        foreach ($columns as $column) {
            if (! str_contains($haystack, strtolower($column))) {
                return false;
            }
        }

        return true;
    }
}
