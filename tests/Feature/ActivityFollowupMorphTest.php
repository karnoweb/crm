<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Karnoweb\Crm\Events\FollowupDue;
use Karnoweb\Crm\Exceptions\UnsupportedMorphTargetException;
use Karnoweb\Crm\Facades\Crm;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\CrmMorphMap;
use Karnoweb\Crm\Tests\Fixtures\User;
use Karnoweb\Crm\Tests\TestCase;

final class ActivityFollowupMorphTest extends TestCase
{
    public function test_morph_map_merges_with_existing_host_aliases(): void
    {
        Relation::morphMap(['host_user' => User::class]);
        CrmMorphMap::register();

        $map = Relation::morphMap();

        $this->assertSame(User::class, $map['host_user']);
        $this->assertSame(Lead::class, $map['crm_lead']);
        $this->assertSame(Deal::class, $map['crm_deal']);
    }

    public function test_activity_and_note_use_crm_prefixed_aliases(): void
    {
        $lead = Crm::leads()->create(['name' => 'A']);
        $activity = Crm::activities()->log($lead, 'call', ['note' => 'hello']);
        $note = Crm::activities()->addNote($lead, 'Body', 'Title');

        $this->assertSame('crm_lead', $activity->activityable_type);
        $this->assertTrue($activity->activityable->is($lead));
        $this->assertSame('crm_lead', $note->notable_type);
    }

    public function test_host_models_are_rejected_as_morph_targets(): void
    {
        $this->expectException(UnsupportedMorphTargetException::class);
        CrmMorphMap::aliasFor(new User);
    }

    public function test_followup_due_fires_once_after_due_time(): void
    {
        Event::fake([FollowupDue::class]);
        Carbon::setTestNow('2026-08-26 10:00:00');
        $lead = Crm::leads()->create(['name' => 'F']);

        Crm::followups()->schedule($lead, Carbon::parse('2026-08-26 11:00:00'), 9);
        $this->assertSame(0, Crm::followups()->processDue());
        Event::assertNotDispatched(FollowupDue::class);

        Carbon::setTestNow('2026-08-26 11:00:00');
        $this->assertSame(1, Crm::followups()->processDue());
        $this->assertSame(0, Crm::followups()->processDue());
        Event::assertDispatchedTimes(FollowupDue::class, 1);
        Carbon::setTestNow();
    }
}
