<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Support\CrmSchema;
use Karnoweb\Crm\Support\KeyType;
use Karnoweb\Crm\Tests\TestCase;

final class KeyTypeMigrationTest extends TestCase
{
    public function test_int_user_keys_create_integer_columns_and_assigned_to_follows_user(): void
    {
        config()->set('crm.keys.user_key_type', 'int');
        config()->set('crm.keys.branch_key_type', null);

        $this->assertSame(KeyType::INT, KeyType::user());
        $this->assertSame(KeyType::INT, KeyType::branch());
        $this->assertSame(KeyType::INT, KeyType::assignedTo());

        Schema::create('crm_key_probe', function (Blueprint $table): void {
            $table->id();
            CrmSchema::userKey($table);
            CrmSchema::branchKey($table);
            CrmSchema::assignedTo($table);
        });

        $this->assertTrue(Schema::hasColumns('crm_key_probe', ['user_id', 'branch_id', 'assigned_to']));
        $this->assertSame('integer', Schema::getColumnType('crm_key_probe', 'user_id'));
        $this->assertSame('integer', Schema::getColumnType('crm_key_probe', 'assigned_to'));
        $this->assertSame('integer', Schema::getColumnType('crm_key_probe', 'branch_id'));
    }

    public function test_uuid_user_keys_and_explicit_ulid_branch_keys(): void
    {
        config()->set('crm.keys.user_key_type', 'uuid');
        config()->set('crm.keys.branch_key_type', 'ulid');

        $this->assertSame(KeyType::UUID, KeyType::user());
        $this->assertSame(KeyType::ULID, KeyType::branch());
        $this->assertSame(KeyType::UUID, KeyType::assignedTo());

        Schema::create('crm_key_probe_mixed', function (Blueprint $table): void {
            $table->id();
            CrmSchema::userKey($table);
            CrmSchema::branchKey($table);
            CrmSchema::assignedTo($table);
        });

        $this->assertContains(Schema::getColumnType('crm_key_probe_mixed', 'user_id'), ['string', 'guid', 'uuid', 'varchar']);
        $this->assertContains(Schema::getColumnType('crm_key_probe_mixed', 'assigned_to'), ['string', 'guid', 'uuid', 'varchar']);
        $this->assertContains(Schema::getColumnType('crm_key_probe_mixed', 'branch_id'), ['string', 'guid', 'ulid', 'varchar']);
    }
}
