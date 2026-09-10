<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Enums\AttributionPolicy;
use Karnoweb\Crm\Enums\AttributionStatus;
use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Attribution;
use Karnoweb\Crm\Tests\TestCase;

final class AttributionServiceTest extends TestCase
{
    public function test_active_deal_attributes_to_deal_assignee_not_lead_owner(): void
    {
        config(['crm.attribution.default_policy' => AttributionPolicy::ActiveOpportunity->value]);

        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Sales'], ['Prospect']);
        $lead = Crm::leads()->create([
            'name' => 'Buyer',
            'assigned_to' => 99,
            'branch_id' => 1,
        ]);
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
            'assigned_to' => 42,
            'branch_id' => 1,
        ]);

        $row = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'deal_id' => $deal->id,
            'order_id' => 10,
            'invoice_id' => 20,
            'amount' => 1000,
            'currency' => 'IRT',
            'branch_id' => 1,
            'idempotency_key' => 'attr:order:10',
        ]);

        $this->assertSame(AttributionStatus::Attributed, $row->status);
        $this->assertSame(42, (int) $row->credited_to);
        $this->assertNotSame(99, (int) $row->credited_to);
        $this->assertSame($deal->id, (int) $row->deal_id);
    }

    public function test_expired_window_yields_unattributed(): void
    {
        config([
            'crm.attribution.default_policy' => AttributionPolicy::LastTouch->value,
            'crm.attribution.window_days' => 7,
        ]);

        $lead = Crm::leads()->create(['name' => 'W', 'branch_id' => 1]);
        Carbon::setTestNow('2026-01-01 10:00:00');
        Crm::attributions()->openWindow($lead, 5, null, Carbon::now(), ['source' => 'call']);

        Carbon::setTestNow('2026-01-20 10:00:00');
        $row = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'amount' => 100,
            'idempotency_key' => 'attr:expired',
            'occurred_at' => Carbon::now(),
        ]);

        $this->assertSame(AttributionStatus::Unattributed, $row->status);
        $this->assertNull($row->credited_to);
        Carbon::setTestNow();
    }

    public function test_valid_window_last_touch_attributes(): void
    {
        config([
            'crm.attribution.default_policy' => AttributionPolicy::LastTouch->value,
            'crm.attribution.window_days' => 30,
        ]);

        $lead = Crm::leads()->create(['name' => 'W', 'branch_id' => 1]);
        Crm::attributions()->openWindow($lead, 1, null, Carbon::now()->subDays(2), ['source' => 'first']);
        Crm::attributions()->openWindow($lead, 7, null, Carbon::now()->subDay(), ['source' => 'last']);

        $row = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'amount' => 50,
            'idempotency_key' => 'attr:last',
        ]);

        $this->assertSame(7, (int) $row->credited_to);
        $this->assertSame('last', $row->source);
    }

    public function test_attribution_is_idempotent(): void
    {
        $lead = Crm::leads()->create(['name' => 'I', 'branch_id' => 1, 'assigned_to' => 3]);
        $a = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'amount' => 10,
            'idempotency_key' => 'same-key',
        ]);
        $b = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'amount' => 10,
            'idempotency_key' => 'same-key',
        ]);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Attribution::query()->count());
    }

    public function test_refund_reverses_without_deleting_history(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'S'], ['A']);
        $lead = Crm::leads()->create(['name' => 'R', 'branch_id' => 1]);
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
            'assigned_to' => 8,
            'branch_id' => 1,
        ]);

        $row = Crm::attributions()->attributePurchase([
            'lead_id' => $lead->id,
            'deal_id' => $deal->id,
            'invoice_id' => 55,
            'amount' => 200,
            'idempotency_key' => 'attr:inv:55',
        ]);

        $count = Crm::attributions()->reverseForInvoice(55, 200);
        $this->assertSame(1, $count);
        $this->assertSame(AttributionStatus::Reversed, $row->fresh()->status);
        $this->assertNotNull($row->fresh()->reversed_at);
        $this->assertSame(2, Attribution::query()->count());
        $this->assertSame(0.0, Crm::attributions()->attributedRevenue());
        $this->assertSame(0, Crm::attributions()->reverseForInvoice(55, 200));
    }

    public function test_deal_win_opens_attribution_window(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'S'], ['A']);
        $lead = Crm::leads()->create(['name' => 'Win', 'branch_id' => 1]);
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
            'assigned_to' => 11,
            'branch_id' => 1,
        ]);

        Crm::deals()->win($deal);

        $this->assertDatabaseCount('crm_attribution_windows', 1);
        $this->assertSame(DealStatus::Won, $deal->fresh()->status);
    }
}
