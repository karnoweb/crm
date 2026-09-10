<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Exception;
use Illuminate\Database\QueryException;
use Karnoweb\Crm\Support\QueryExceptionClassifier;
use PHPUnit\Framework\TestCase;

final class QueryExceptionClassifierTest extends TestCase
{
    public function test_mysql_duplicate_on_user_id_is_detected(): void
    {
        $e = $this->queryException(
            '23000',
            1062,
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '9' for key 'crm_leads_user_id_unique'"
        );

        $this->assertTrue(QueryExceptionClassifier::isUniqueViolation($e));
        $this->assertTrue(QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id'));
        $this->assertFalse(QueryExceptionClassifier::isUniqueViolationOn($e, 'idempotency_key'));
    }

    public function test_postgres_unique_violation_on_composite_key_requires_both_columns(): void
    {
        $e = $this->queryException(
            '23505',
            7,
            'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "crm_interactions_lead_id_idempotency_key_unique"'
        );

        $this->assertTrue(QueryExceptionClassifier::isUniqueViolationOn($e, 'lead_id', 'idempotency_key'));
        $this->assertFalse(QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id'));
    }

    public function test_sqlite_unique_violation_uses_driver_code_nineteen(): void
    {
        $e = $this->queryException(
            '23000',
            19,
            'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: crm_leads.user_id'
        );

        $this->assertTrue(QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id'));
    }

    public function test_unrelated_query_exception_is_not_swallowed(): void
    {
        $e = $this->queryException('HY000', 1, 'SQLSTATE[HY000]: General error: no such table: crm_leads');

        $this->assertFalse(QueryExceptionClassifier::isUniqueViolation($e));
        $this->assertFalse(QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id'));
    }

    public function test_message_containing_unique_without_driver_code_is_not_enough(): void
    {
        $e = $this->queryException('HY000', 0, 'something unique went wrong for user_id');

        $this->assertFalse(QueryExceptionClassifier::isUniqueViolation($e));
        $this->assertFalse(QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id'));
    }

    private function queryException(string $sqlState, int $driverCode, string $message): QueryException
    {
        $exception = new QueryException('testing', 'insert into crm_leads', [], new Exception($message));
        $exception->errorInfo = [$sqlState, $driverCode, $message];

        return $exception;
    }
}
