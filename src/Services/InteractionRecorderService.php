<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Events\InteractionRecorded;
use Karnoweb\Crm\Exceptions\LeadNotFoundException;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\InteractionValidator;
use Karnoweb\Crm\Support\QueryExceptionClassifier;

/**
 * Records generic host signals. Interaction is the source of truth.
 *
 * Concurrency / idempotency:
 * 1. INSERT is attempted first (not select-then-insert).
 * 2. Only a unique violation on (lead_id, idempotency_key) is treated as a replay.
 * 3. A replay returns the existing row, does not rescore, and does not publish InteractionRecorded.
 * 4. Every other exception propagates.
 */
final class InteractionRecorderService
{
    public function __construct(
        private readonly AffinityScoringService $scoring,
        private readonly InteractionValidator $validator,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        int $leadId,
        string $type,
        string $subjectGroup,
        string $subjectKey,
        float $weight = 1.0,
        string $source = '',
        ?string $sourceId = null,
        string $idempotencyKey = '',
        ?Carbon $occurredAt = null,
        array $metadata = [],
        ?string $subjectLabel = null,
    ): Interaction {
        $type = $this->validator->validateType($type);
        $this->validator->validateSubject($subjectGroup, $subjectKey);
        $weight = $this->validator->validateWeight($weight);
        $source = $this->validator->validateSource($source);
        $idempotencyKey = $this->validator->validateIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $leadId,
            $type,
            $subjectGroup,
            $subjectKey,
            $weight,
            $source,
            $idempotencyKey,
            $sourceId,
            $occurredAt,
            $metadata,
            $subjectLabel,
        ): Interaction {
            $lead = Lead::query()->whereKey($leadId)->lockForUpdate()->first();

            if ($lead === null) {
                throw new LeadNotFoundException("Lead [{$leadId}] was not found.");
            }

            try {
                $interaction = Interaction::query()->create([
                    'lead_id' => $lead->getKey(),
                    'type' => $type,
                    'subject_group' => $subjectGroup,
                    'subject_key' => $subjectKey,
                    'subject_label' => $subjectLabel,
                    'weight' => $weight,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => $metadata,
                    'occurred_at' => $occurredAt ?? Carbon::now(),
                ]);
            } catch (QueryException $e) {
                if (! QueryExceptionClassifier::isUniqueViolationOn($e, 'lead_id', 'idempotency_key')) {
                    throw $e;
                }

                return Interaction::query()
                    ->where('lead_id', $lead->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }

            $this->scoring->apply($interaction);

            CrmEventDispatcher::dispatch(new InteractionRecorded(
                $interaction->getKey(),
                $lead->getKey(),
                $interaction->type,
                $interaction->subject_group,
                $interaction->subject_key,
            ));

            return $interaction;
        });
    }
}
