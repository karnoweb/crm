<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Exceptions\InvalidKeyTypeException;
use Karnoweb\Crm\Support\KeyType;
use Karnoweb\Crm\Tests\TestCase;

final class KeyTypeTest extends TestCase
{
    public function test_branch_inherits_user_key_type_when_null(): void
    {
        config()->set('crm.keys.user_key_type', 'uuid');
        config()->set('crm.keys.branch_key_type', null);

        $this->assertSame(KeyType::UUID, KeyType::branch());
    }

    public function test_branch_inherits_user_key_type_when_empty_string(): void
    {
        config()->set('crm.keys.user_key_type', 'ulid');
        config()->set('crm.keys.branch_key_type', '');

        $this->assertSame(KeyType::ULID, KeyType::branch());
    }

    public function test_assigned_to_always_uses_user_key_type(): void
    {
        config()->set('crm.keys.user_key_type', 'uuid');
        config()->set('crm.keys.branch_key_type', 'int');

        $this->assertSame(KeyType::UUID, KeyType::assignedTo());
        $this->assertSame(KeyType::INT, KeyType::branch());
    }

    public function test_unknown_key_type_is_rejected(): void
    {
        $this->expectException(InvalidKeyTypeException::class);

        KeyType::normalize('objectid');
    }
}
