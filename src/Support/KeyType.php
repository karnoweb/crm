<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Karnoweb\Crm\Exceptions\InvalidKeyTypeException;

/**
 * Resolves configurable host-key column types for user_id, branch_id, and assigned_to.
 */
final class KeyType
{
    public const INT = 'int';

    public const UUID = 'uuid';

    public const ULID = 'ulid';

    /**
     * @return self::INT|self::UUID|self::ULID
     */
    public static function user(): string
    {
        return self::normalize((string) config('crm.keys.user_key_type', self::INT));
    }

    /**
     * Branch keys inherit user_key_type when branch_key_type is null/empty.
     *
     * @return self::INT|self::UUID|self::ULID
     */
    public static function branch(): string
    {
        $configured = config('crm.keys.branch_key_type');

        if ($configured === null || $configured === '') {
            return self::user();
        }

        return self::normalize((string) $configured);
    }

    /**
     * assigned_to always follows user_key_type (it points at a host User).
     *
     * @return self::INT|self::UUID|self::ULID
     */
    public static function assignedTo(): string
    {
        return self::user();
    }

    public static function apply(Blueprint $table, string $column, string $type, bool $nullable = true): ColumnDefinition
    {
        $definition = match (self::normalize($type)) {
            self::UUID => $table->uuid($column),
            self::ULID => $table->ulid($column),
            self::INT => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }

        return $definition;
    }

    /**
     * @return self::INT|self::UUID|self::ULID
     */
    public static function normalize(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            self::INT, 'integer', 'bigint' => self::INT,
            self::UUID => self::UUID,
            self::ULID => self::ULID,
            default => throw new InvalidKeyTypeException("Unsupported CRM key type [{$type}]. Allowed: int, uuid, ulid."),
        };
    }
}
