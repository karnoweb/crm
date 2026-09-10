<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\AttributionPolicy;
use Karnoweb\Crm\Enums\AttributionStatus;
use Karnoweb\Crm\Enums\DealStatus;
use Karnoweb\Crm\Models\Attribution;
use Karnoweb\Crm\Models\AttributionWindow;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\AttributionWindowPolicy;

/**
 * Sales credit attribution. Owner (assigned_to) is never used as automatic credit.
 */
final class AttributionService
{
    public function __construct(
        private readonly AttributionWindowPolicy $windowPolicy,
    ) {}

    /**
     * Open a time-bound credit window after a credited sales action.
     *
     * @param array{source?: string, metadata?: array<string, mixed>|null} $attributes
     */
    public function openWindow(
        Lead $lead,
        int|string|null $creditedTo,
        ?Deal $deal = null,
        ?Carbon $opensAt = null,
        array $attributes = [],
    ): AttributionWindow {
        $opensAt ??= Carbon::now();

        return AttributionWindow::query()->create([
            'lead_id' => $lead->getKey(),
            'deal_id' => $deal?->getKey(),
            'credited_to' => $creditedTo,
            'source' => $attributes['source'] ?? 'sales_action',
            'opens_at' => $opensAt,
            'closes_at' => $this->windowPolicy->closesAt($opensAt),
            'is_active' => true,
            'branch_id' => $lead->branch_id,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    /**
     * Attribute a settled purchase. Idempotent on idempotency_key.
     *
     * @param  array{
     *     lead_id: int,
     *     order_id?: int|null,
     *     invoice_id?: int|null,
     *     deal_id?: int|null,
     *     amount: float,
     *     currency?: string|null,
     *     branch_id?: int|null,
     *     occurred_at?: Carbon|null,
     *     policy?: AttributionPolicy|null,
     *     idempotency_key: string,
     * }  $payload
     */
    public function attributePurchase(array $payload): Attribution
    {
        $key = (string) $payload['idempotency_key'];

        $existing = Attribution::query()->where('idempotency_key', $key)->first();
        if ($existing instanceof Attribution) {
            return $existing;
        }

        return DB::transaction(function () use ($payload, $key): Attribution {
            $locked = Attribution::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($locked instanceof Attribution) {
                return $locked;
            }

            $lead = Lead::query()->whereKey($payload['lead_id'])->firstOrFail();
            $occurredAt = $payload['occurred_at'] ?? Carbon::now();
            $policy = $payload['policy'] ?? $this->windowPolicy->defaultPolicy();

            $resolution = $this->resolveCredit(
                $lead,
                $policy,
                isset($payload['deal_id']) ? (int) $payload['deal_id'] : null,
                $occurredAt,
            );

            return Attribution::query()->create([
                'lead_id' => $lead->getKey(),
                'deal_id' => $resolution['deal_id'],
                'credited_to' => $resolution['credited_to'],
                'source' => $resolution['source'],
                'policy' => $policy,
                'status' => $resolution['credited_to'] !== null
                    ? AttributionStatus::Attributed
                    : AttributionStatus::Unattributed,
                'order_id' => $payload['order_id'] ?? null,
                'invoice_id' => $payload['invoice_id'] ?? null,
                'amount' => (float) $payload['amount'],
                'currency' => $payload['currency'] ?? null,
                'attributed_at' => $occurredAt,
                'window_starts_at' => $resolution['window_starts_at'],
                'window_ends_at' => $resolution['window_ends_at'],
                'idempotency_key' => $key,
                'branch_id' => $payload['branch_id'] ?? $lead->branch_id,
                'metadata' => [
                    'owner_at_attribution' => $lead->assigned_to,
                    'resolution' => $resolution['reason'],
                ],
            ]);
        });
    }

    /**
     * Reverse attributions for an invoice/order refund. Append-only: originals marked reversed + reversal rows.
     */
    public function reverseForInvoice(int $invoiceId, ?float $amount = null, ?Carbon $occurredAt = null): int
    {
        $occurredAt ??= Carbon::now();
        $reversed = 0;

        DB::transaction(function () use ($invoiceId, $amount, $occurredAt, &$reversed): void {
            $rows = Attribution::query()
                ->where('invoice_id', $invoiceId)
                ->where('status', AttributionStatus::Attributed)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $reversalAmount = $amount ?? (float) $row->amount;
                $reversalKey = "reversal:attribution:{$row->getKey()}:invoice:{$invoiceId}";

                if (Attribution::query()->where('idempotency_key', $reversalKey)->exists()) {
                    continue;
                }

                $row->forceFill([
                    'status' => AttributionStatus::Reversed,
                    'reversed_at' => $occurredAt,
                ])->save();

                Attribution::query()->create([
                    'lead_id' => $row->lead_id,
                    'deal_id' => $row->deal_id,
                    'credited_to' => $row->credited_to,
                    'source' => 'refund_reversal',
                    'policy' => $row->policy,
                    'status' => AttributionStatus::Reversed,
                    'order_id' => $row->order_id,
                    'invoice_id' => $row->invoice_id,
                    'amount' => $reversalAmount,
                    'currency' => $row->currency,
                    'attributed_at' => $occurredAt,
                    'window_starts_at' => $row->window_starts_at,
                    'window_ends_at' => $row->window_ends_at,
                    'idempotency_key' => $reversalKey,
                    'reversal_of_id' => $row->getKey(),
                    'reversed_at' => $occurredAt,
                    'branch_id' => $row->branch_id,
                    'metadata' => ['reason' => 'refund'],
                ]);

                $reversed++;
            }
        });

        return $reversed;
    }

    /**
     * Net attributed revenue for active (non-reversed) attribution rows.
     */
    public function attributedRevenue(?Carbon $from = null, ?Carbon $to = null, ?int $branchId = null): float
    {
        return (float) Attribution::query()
            ->where('status', AttributionStatus::Attributed)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from, fn ($q) => $q->where('attributed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('attributed_at', '<=', $to))
            ->sum('amount');
    }

    /**
     * @return array{
     *     credited_to: int|string|null,
     *     deal_id: int|null,
     *     source: string|null,
     *     window_starts_at: Carbon|null,
     *     window_ends_at: Carbon|null,
     *     reason: string
     * }
     */
    private function resolveCredit(
        Lead $lead,
        AttributionPolicy $policy,
        ?int $preferredDealId,
        Carbon $at,
    ): array {
        $empty = [
            'credited_to' => null,
            'deal_id' => null,
            'source' => null,
            'window_starts_at' => null,
            'window_ends_at' => null,
            'reason' => 'unattributed',
        ];

        if ($policy === AttributionPolicy::Manual) {
            return $empty;
        }

        if ($policy === AttributionPolicy::ActiveOpportunity || $preferredDealId !== null) {
            $deal = $this->resolveDealCandidate($lead, $preferredDealId, $at);
            if ($deal instanceof Deal && $deal->assigned_to !== null) {
                return [
                    'credited_to' => $deal->assigned_to,
                    'deal_id' => (int) $deal->getKey(),
                    'source' => 'active_opportunity',
                    'window_starts_at' => $deal->stage_entered_at?->toMutable() ?? $at,
                    'window_ends_at' => $this->windowPolicy->closesAt($at),
                    'reason' => 'active_deal',
                ];
            }

            if ($policy === AttributionPolicy::ActiveOpportunity) {
                // Fall through to open windows when no active/won deal in window.
            }
        }

        $windows = AttributionWindow::query()
            ->where('lead_id', $lead->getKey())
            ->where('is_active', true)
            ->where('opens_at', '<=', $at)
            ->where('closes_at', '>=', $at)
            ->orderBy('opens_at')
            ->get();

        if ($windows->isEmpty()) {
            return $empty;
        }

        $chosen = match ($policy) {
            AttributionPolicy::FirstTouch => $windows->first(),
            AttributionPolicy::LastTouch,
            AttributionPolicy::ActiveOpportunity => $windows->last(),
            default => $windows->last(),
        };

        if ($chosen === null || $chosen->credited_to === null) {
            return $empty;
        }

        return [
            'credited_to' => $chosen->credited_to,
            'deal_id' => $chosen->deal_id !== null ? (int) $chosen->deal_id : null,
            'source' => $chosen->source,
            'window_starts_at' => $chosen->opens_at?->toMutable(),
            'window_ends_at' => $chosen->closes_at?->toMutable(),
            'reason' => 'window_' . $policy->value,
        ];
    }

    private function resolveDealCandidate(Lead $lead, ?int $preferredDealId, Carbon $at): ?Deal
    {
        if ($preferredDealId !== null) {
            $deal = Deal::query()
                ->whereKey($preferredDealId)
                ->where('lead_id', $lead->getKey())
                ->first();

            if ($deal instanceof Deal) {
                return $deal;
            }
        }

        $open = Deal::query()
            ->where('lead_id', $lead->getKey())
            ->where('status', DealStatus::Open)
            ->orderByDesc('updated_at')
            ->first();

        if ($open instanceof Deal) {
            return $open;
        }

        $windowStart = $at->copy()->subDays($this->windowPolicy->windowDays());

        return Deal::query()
            ->where('lead_id', $lead->getKey())
            ->where('status', DealStatus::Won)
            ->where('updated_at', '>=', $windowStart)
            ->orderByDesc('updated_at')
            ->first();
    }
}
