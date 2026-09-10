<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * Migration helpers for configurable host-key columns. No real FKs are created.
 */
final class CrmSchema
{
    public static function userKey(Blueprint $table, string $column = 'user_id', bool $nullable = true): ColumnDefinition
    {
        return KeyType::apply($table, $column, KeyType::user(), $nullable);
    }

    public static function branchKey(Blueprint $table, string $column = 'branch_id', bool $nullable = true): ColumnDefinition
    {
        return KeyType::apply($table, $column, KeyType::branch(), $nullable);
    }

    /**
     * assigned_to always uses user_key_type.
     */
    public static function assignedTo(Blueprint $table, string $column = 'assigned_to', bool $nullable = true): ColumnDefinition
    {
        return KeyType::apply($table, $column, KeyType::assignedTo(), $nullable);
    }
}
