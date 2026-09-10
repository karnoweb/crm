<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\TaskStatus;
use Karnoweb\Crm\Events\FollowupDue;
use Karnoweb\Crm\Exceptions\InvalidTaskStateException;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Followup;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\CrmMorphMap;

/**
 * Operational Task API persisted on crm_followups.
 * Reminder notifications use notified_at and never imply completion.
 */
final class TaskService
{
    /**
     * @param array{title?: string|null, type?: string|null} $attributes
     */
    public function create(
        Lead|Deal $subject,
        Carbon $dueAt,
        int|string|null $assignedTo = null,
        array $attributes = [],
    ): Followup {
        return Followup::query()->create([
            'subject_type' => CrmMorphMap::aliasFor($subject),
            'subject_id' => $subject->getKey(),
            'title' => $attributes['title'] ?? null,
            'type' => $attributes['type'] ?? null,
            'status' => TaskStatus::Open,
            'due_at' => $dueAt,
            'assigned_to' => $assignedTo,
            'outcome' => null,
            'completed_at' => null,
            'notified_at' => null,
        ]);
    }

    public function complete(Followup $task, ?string $outcome = null): Followup
    {
        return DB::transaction(function () use ($task, $outcome): Followup {
            $task = Followup::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();

            if ($task->status === TaskStatus::Done) {
                return $task;
            }

            if ($task->status === TaskStatus::Cancelled) {
                throw new InvalidTaskStateException('A cancelled task cannot be completed.');
            }

            $task->forceFill([
                'status' => TaskStatus::Done,
                'outcome' => $outcome,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->openAttributionWindowForCompletedTask($task);

            return $task->refresh();
        });
    }

    private function openAttributionWindowForCompletedTask(Followup $task): void
    {
        $subject = $task->subject;
        if (! $subject instanceof Lead && ! $subject instanceof Deal) {
            return;
        }

        $lead = $subject instanceof Lead ? $subject : $subject->lead;
        if ($lead === null) {
            return;
        }

        $creditedTo = $task->assigned_to ?? ($subject instanceof Deal ? $subject->assigned_to : $lead->assigned_to);
        if ($creditedTo === null) {
            return;
        }

        app(AttributionService::class)->openWindow(
            $lead,
            $creditedTo,
            $subject instanceof Deal ? $subject : null,
            Carbon::now(),
            ['source' => 'task_completed', 'metadata' => ['task_id' => $task->getKey()]],
        );
    }

    public function cancel(Followup $task): Followup
    {
        return DB::transaction(function () use ($task): Followup {
            $task = Followup::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();

            if ($task->status === TaskStatus::Cancelled) {
                return $task;
            }

            if ($task->status === TaskStatus::Done) {
                throw new InvalidTaskStateException('A completed task cannot be cancelled.');
            }

            $task->forceFill([
                'status' => TaskStatus::Cancelled,
                'completed_at' => Carbon::now(),
            ])->save();

            return $task->refresh();
        });
    }

    public function processDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $published = 0;

        DB::transaction(function () use ($now, &$published): void {
            $due = Followup::query()
                ->where('status', TaskStatus::Open)
                ->where('due_at', '<=', $now)
                ->whereNull('notified_at')
                ->lockForUpdate()
                ->get();

            foreach ($due as $followup) {
                $followup->forceFill(['notified_at' => $now])->save();
                CrmEventDispatcher::dispatch(new FollowupDue(
                    $followup->getKey(),
                    $followup->subject_type,
                    $followup->subject_id,
                    $followup->due_at->toIso8601String(),
                ));
                $published++;
            }
        });

        return $published;
    }

    public function nextActionForDeal(Deal $deal): ?Followup
    {
        return Followup::query()
            ->where('subject_type', CrmMorphMap::DEAL)
            ->where('subject_id', $deal->getKey())
            ->where('status', TaskStatus::Open)
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->first();
    }
}
