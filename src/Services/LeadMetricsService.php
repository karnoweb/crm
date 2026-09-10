<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Exceptions\InvalidMonetaryValueException;
use Karnoweb\Crm\Exceptions\LeadNotFoundException;
use Karnoweb\Crm\Models\Lead;

/**
 * External monetary projection. CRM never converts or sums across currencies.
 * The host must send a canonical value in one unit; the last value overwrites.
 */
final class LeadMetricsService
{
    public function recordMonetaryValue(
        int $leadId,
        float $value,
        string $currency,
        ?Carbon $occurredAt = null,
    ): void {
        if (! is_finite($value)) {
            throw new InvalidMonetaryValueException('Monetary value must be finite.');
        }

        if (trim($currency) === '') {
            throw new InvalidMonetaryValueException('Currency is required and is stored for audit only.');
        }

        $recordedAt = $occurredAt ?? Carbon::now();

        DB::transaction(function () use ($leadId, $value, $currency, $recordedAt): void {
            $lead = Lead::query()->whereKey($leadId)->lockForUpdate()->first();

            if ($lead === null) {
                throw new LeadNotFoundException("Lead [{$leadId}] was not found.");
            }

            $lead->forceFill([
                'rfm_monetary_score' => (int) round($value),
                'rfm_monetary_currency' => $currency,
                'rfm_monetary_at' => $recordedAt,
            ])->save();
        });
    }
}
