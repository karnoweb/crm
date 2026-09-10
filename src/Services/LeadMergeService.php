<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Exceptions\CrmException;
use Karnoweb\Crm\Models\Activity;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Followup;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Models\Note;
use Karnoweb\Crm\Support\CrmMorphMap;

/**
 * Soft duplicate detection + auditable Lead merge.
 */
final class LeadMergeService
{
    /**
     * @return list<Lead>
     */
    public function findSoftDuplicates(Lead $lead): array
    {
        $query = Lead::query()->whereKeyNot($lead->getKey());

        $query->where(function ($builder) use ($lead): void {
            $matched = false;
            if (filled($lead->phone)) {
                $builder->orWhere('phone', $lead->phone);
                $matched = true;
            }
            if (filled($lead->email)) {
                $builder->orWhere('email', $lead->email);
                $matched = true;
            }
            if (! $matched) {
                $builder->whereRaw('1 = 0');
            }
        });

        return $query->limit(25)->get()->all();
    }

    /**
     * Merge $secondary into $primary. Preserves history; does not delete secondary hard.
     */
    public function merge(Lead $primary, Lead $secondary): Lead
    {
        if ($primary->is($secondary)) {
            throw new CrmException('Cannot merge a Lead into itself.');
        }

        return DB::transaction(function () use ($primary, $secondary): Lead {
            $primary = Lead::query()->whereKey($primary->getKey())->lockForUpdate()->firstOrFail();
            $secondary = Lead::query()->whereKey($secondary->getKey())->lockForUpdate()->firstOrFail();

            if ($primary->user_id !== null && $secondary->user_id !== null && (string) $primary->user_id !== (string) $secondary->user_id) {
                throw new CrmException('Cannot merge Leads linked to different Users.');
            }

            if ($primary->user_id === null && $secondary->user_id !== null) {
                $primary->forceFill(['user_id' => $secondary->user_id])->save();
                $secondary->forceFill(['user_id' => null])->save();
            }

            Deal::query()->where('lead_id', $secondary->getKey())->update(['lead_id' => $primary->getKey()]);

            Interaction::query()->where('lead_id', $secondary->getKey())->update(['lead_id' => $primary->getKey()]);

            $this->reassignMorph(Activity::query(), 'activityable_type', 'activityable_id', $secondary, $primary);
            $this->reassignMorph(Note::query(), 'notable_type', 'notable_id', $secondary, $primary);
            $this->reassignMorph(Followup::query(), 'subject_type', 'subject_id', $secondary, $primary);

            $primary->forceFill([
                'phone' => $primary->phone ?: $secondary->phone,
                'email' => $primary->email ?: $secondary->email,
                'name' => $primary->name ?: $secondary->name,
                'source' => $primary->source ?: $secondary->source,
                'assigned_to' => $primary->assigned_to ?? $secondary->assigned_to,
            ])->save();

            if ($secondary->status !== LeadStatus::Customer) {
                $secondary->forceFill([
                    'status' => LeadStatus::Lost,
                    'archived_at' => Carbon::now(),
                ])->save();
            } else {
                $secondary->forceFill([
                    'archived_at' => Carbon::now(),
                ])->save();
            }

            $secondary->hostAttributes()->set('merged_into_lead_id', $primary->getKey());
            $secondary->hostAttributes()->set('merged_at', Carbon::now()->toIso8601String());
            $secondary->save();

            app(ActivityService::class)->log($primary, 'lead_merged', [
                'merged_lead_id' => $secondary->getKey(),
            ]);

            return $primary->refresh();
        });
    }

    private function reassignMorph($query, string $typeColumn, string $idColumn, Lead $from, Lead $to): void
    {
        $query->where($typeColumn, CrmMorphMap::LEAD)
            ->where($idColumn, $from->getKey())
            ->update([$idColumn => $to->getKey()]);
    }
}
