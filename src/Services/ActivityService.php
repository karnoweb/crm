<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Karnoweb\Crm\Models\Activity;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\Note;
use Karnoweb\Crm\Support\CrmMorphMap;

final class ActivityService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function log(Lead|Deal $subject, string $type, array $payload = []): Activity
    {
        return Activity::query()->create([
            'activityable_type' => CrmMorphMap::aliasFor($subject),
            'activityable_id' => $subject->getKey(),
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function addNote(Lead|Deal $subject, string $body, ?string $title = null): Note
    {
        return Note::query()->create([
            'notable_type' => CrmMorphMap::aliasFor($subject),
            'notable_id' => $subject->getKey(),
            'body' => $body,
            'title' => $title,
        ]);
    }
}
