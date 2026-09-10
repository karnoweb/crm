<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Exceptions\InvalidMonetaryValueException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Tests\TestCase;

final class LeadMetricsServiceTest extends TestCase
{
    public function test_monetary_value_overwrites_and_does_not_sum_currencies(): void
    {
        $lead = $this->makeLead();

        Crm::metrics()->recordMonetaryValue($lead->id, 1000, 'IRR');
        Crm::metrics()->recordMonetaryValue($lead->id, 20, 'USD');

        $lead->refresh();

        $this->assertSame(20, $lead->rfm_monetary_score);
        $this->assertSame('USD', $lead->rfm_monetary_currency);
    }

    public function test_occurred_at_is_persisted_for_audit(): void
    {
        $lead = $this->makeLead();
        $occurredAt = Carbon::parse('2024-06-15 10:30:00');

        Crm::metrics()->recordMonetaryValue($lead->id, 500, 'IRR', $occurredAt);

        $lead->refresh();

        $this->assertSame(500, $lead->rfm_monetary_score);
        $this->assertSame('IRR', $lead->rfm_monetary_currency);
        $this->assertTrue($lead->rfm_monetary_at->equalTo($occurredAt));
    }

    public function test_non_finite_monetary_value_is_rejected(): void
    {
        $lead = $this->makeLead();

        $this->expectException(InvalidMonetaryValueException::class);
        Crm::metrics()->recordMonetaryValue($lead->id, NAN, 'IRR');
    }
}
