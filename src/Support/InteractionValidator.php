<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Karnoweb\Crm\Exceptions\InvalidIdempotencyKeyException;
use Karnoweb\Crm\Exceptions\InvalidInteractionTypeException;
use Karnoweb\Crm\Exceptions\InvalidWeightException;

final class InteractionValidator
{
    public function validateWeight(mixed $weight): float
    {
        if (! is_numeric($weight)) {
            throw new InvalidWeightException('Interaction weight must be numeric.');
        }

        $value = (float) $weight;

        if (! is_finite($value)) {
            throw new InvalidWeightException('Interaction weight must be finite (not NaN or INF).');
        }

        if ($value < 0) {
            throw new InvalidWeightException('Interaction weight must be greater than or equal to 0.');
        }

        return $value;
    }

    public function validateIdempotencyKey(string $idempotencyKey): string
    {
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new InvalidIdempotencyKeyException('idempotency_key must be a non-empty string of at most 255 characters.');
        }

        return $idempotencyKey;
    }

    public function validateType(string $type): string
    {
        if (trim($type) === '') {
            throw new InvalidInteractionTypeException('Interaction type must be a non-empty string.');
        }

        return $type;
    }

    public function validateSource(string $source): string
    {
        if (trim($source) === '') {
            throw new InvalidInteractionTypeException('Interaction source must be a non-empty string.');
        }

        return $source;
    }

    public function validateSubject(string $subjectGroup, string $subjectKey): void
    {
        if (trim($subjectGroup) === '' || trim($subjectKey) === '') {
            throw new InvalidInteractionTypeException('subject_group and subject_key are required.');
        }
    }
}
