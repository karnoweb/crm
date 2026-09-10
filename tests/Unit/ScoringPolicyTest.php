<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Unit;

use Karnoweb\Crm\Support\AffinityDecayCalculator;
use Karnoweb\Crm\Support\ScoringPolicy;
use Karnoweb\Crm\Tests\TestCase;

final class ScoringPolicyTest extends TestCase
{
    public function test_effective_weight_multiplies_raw_weight_by_policy(): void
    {
        $policy = new ScoringPolicy;

        $this->assertSame(10.0, $policy->effectiveWeight('purchased', 2.0));
        $this->assertSame(1.0, $policy->effectiveWeight('viewed', 1.0));
    }

    public function test_unknown_type_uses_configured_default_weight(): void
    {
        config()->set('crm.scoring.default_weight', 0);
        $policy = new ScoringPolicy;

        $this->assertSame(0.0, $policy->weightFor('webinar_attended'));
        $this->assertSame(0.0, $policy->effectiveWeight('webinar_attended', 9.0));
    }

    public function test_decay_is_half_after_one_half_life(): void
    {
        $decay = new AffinityDecayCalculator;

        $this->assertEqualsWithDelta(1.0, $decay->decay(0, 30), 0.0001);
        $this->assertEqualsWithDelta(0.5, $decay->decay(30, 30), 0.0001);
        $this->assertEqualsWithDelta(0.25, $decay->decay(60, 30), 0.0001);
        $this->assertEqualsWithDelta(1.0, $decay->decay(-10, 30), 0.0001);
    }
}
