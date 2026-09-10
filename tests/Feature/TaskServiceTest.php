<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Enums\TaskStatus;
use Karnoweb\Crm\Events\FollowupDue;
use Karnoweb\Crm\Exceptions\InvalidTaskStateException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Tests\TestCase;

final class TaskServiceTest extends TestCase
{
    public function test_create_defaults_to_open(): void
    {
        $lead = Crm::leads()->create(['name' => 'Task Lead']);
        $task = Crm::tasks()->create($lead, Carbon::now()->addDay(), 3, [
            'title' => 'Call back',
            'type' => 'call',
        ]);

        $this->assertSame(TaskStatus::Open, $task->status);
        $this->assertNull($task->completed_at);
        $this->assertNull($task->notified_at);
        $this->assertSame('Call back', $task->title);
    }

    public function test_complete_sets_done_outcome_and_completed_at(): void
    {
        Carbon::setTestNow('2026-08-29 12:00:00');
        $lead = Crm::leads()->create(['name' => 'Task Lead']);
        $task = Crm::tasks()->create($lead, Carbon::now()->addHour());

        $done = Crm::tasks()->complete($task, 'Interested');

        $this->assertSame(TaskStatus::Done, $done->status);
        $this->assertSame('Interested', $done->outcome);
        $this->assertNotNull($done->completed_at);
        Carbon::setTestNow();
    }

    public function test_cancel_sets_cancelled(): void
    {
        $lead = Crm::leads()->create(['name' => 'Task Lead']);
        $task = Crm::tasks()->create($lead, Carbon::now()->addHour());

        $cancelled = Crm::tasks()->cancel($task);

        $this->assertSame(TaskStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->completed_at);
    }

    public function test_cannot_complete_cancelled_task(): void
    {
        $lead = Crm::leads()->create(['name' => 'Task Lead']);
        $task = Crm::tasks()->cancel(Crm::tasks()->create($lead, Carbon::now()->addHour()));

        $this->expectException(InvalidTaskStateException::class);
        Crm::tasks()->complete($task, 'nope');
    }

    public function test_notified_task_remains_open(): void
    {
        Event::fake([FollowupDue::class]);
        Carbon::setTestNow('2026-08-29 10:00:00');
        $lead = Crm::leads()->create(['name' => 'Task Lead']);
        $task = Crm::tasks()->create($lead, Carbon::parse('2026-08-29 09:00:00'));

        Crm::tasks()->processDue();
        $task->refresh();

        $this->assertSame(TaskStatus::Open, $task->status);
        $this->assertNotNull($task->notified_at);
        Event::assertDispatchedTimes(FollowupDue::class, 1);
        Carbon::setTestNow();
    }

    public function test_process_due_skips_done_and_cancelled(): void
    {
        Event::fake([FollowupDue::class]);
        Carbon::setTestNow('2026-08-29 12:00:00');
        $lead = Crm::leads()->create(['name' => 'Task Lead']);

        $done = Crm::tasks()->create($lead, Carbon::parse('2026-08-29 11:00:00'));
        Crm::tasks()->complete($done, 'done');

        $cancelled = Crm::tasks()->create($lead, Carbon::parse('2026-08-29 11:00:00'));
        Crm::tasks()->cancel($cancelled);

        $this->assertSame(0, Crm::tasks()->processDue());
        Event::assertNotDispatched(FollowupDue::class);
        Carbon::setTestNow();
    }

    public function test_next_action_is_earliest_open_task_on_deal(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Sales'], ['Prospect']);
        $lead = Crm::leads()->create(['name' => 'Deal Lead']);
        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $pipeline->stages()->where('type', 'open')->first()->id,
        ]);

        Crm::tasks()->create($deal, Carbon::now()->addDays(2), null, ['title' => 'Later']);
        $first = Crm::tasks()->create($deal, Carbon::now()->addDay(), null, ['title' => 'Soon']);
        Crm::tasks()->complete(
            Crm::tasks()->create($deal, Carbon::now()->addHours(1), null, ['title' => 'Done early']),
            'done',
        );

        $next = Crm::tasks()->nextActionForDeal($deal);

        $this->assertNotNull($next);
        $this->assertTrue($next->is($first));
        $this->assertSame('Soon', $next->title);
    }

    public function test_schedule_compatibility_creates_open_task(): void
    {
        $lead = Crm::leads()->create(['name' => 'Compat']);
        $task = Crm::followups()->schedule($lead, Carbon::now()->addDay(), 1);

        $this->assertSame(TaskStatus::Open, $task->status);
    }

    public function test_deal_create_sets_stage_entered_at(): void
    {
        $pipeline = Crm::pipelines()->createWithDefaultStages(['name' => 'Sales'], ['A', 'B']);
        $lead = Crm::leads()->create(['name' => 'Aging']);
        $open = $pipeline->stages()->where('type', 'open')->orderBy('order')->get();

        $deal = Crm::deals()->create([
            'lead_id' => $lead->id,
            'pipeline_stage_id' => $open->first()->id,
        ]);

        $this->assertNotNull($deal->stage_entered_at);

        Carbon::setTestNow($deal->stage_entered_at->addHour());
        $moved = Crm::deals()->moveStage($deal, $open->last());
        $this->assertTrue($moved->stage_entered_at->equalTo(Carbon::now()));
        Carbon::setTestNow();
    }
}
