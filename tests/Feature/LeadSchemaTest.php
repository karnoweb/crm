<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Tests\TestCase;

final class LeadSchemaTest extends TestCase
{
    public function test_multiple_null_user_ids_are_allowed(): void
    {
        $first = $this->makeLead(['user_id' => null]);
        $second = $this->makeLead(['user_id' => null]);

        $this->assertNull($first->user_id);
        $this->assertNull($second->user_id);
        $this->assertSame(2, Lead::query()->whereNull('user_id')->count());
    }

    public function test_user_id_is_unique_when_present(): void
    {
        $this->makeLead(['user_id' => 41]);

        $this->expectException(QueryException::class);
        $this->makeLead(['user_id' => 41]);
    }

    public function test_new_lead_has_null_last_status_change_at(): void
    {
        $lead = $this->makeLead();

        $this->assertNull($lead->last_status_change_at);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertNotNull($lead->captured_at);
    }

    public function test_unique_index_exists_on_user_id(): void
    {
        $indexes = Schema::getIndexes('crm_leads');
        $columns = [];

        foreach ($indexes as $index) {
            if (($index['unique'] ?? false) === true) {
                $columns[] = $index['columns'];
            }
        }

        $this->assertTrue(
            collect($columns)->contains(fn (array $cols) => $cols === ['user_id']),
            'crm_leads must have a unique index on user_id'
        );
    }
}
