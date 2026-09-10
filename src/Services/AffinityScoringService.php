<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Support\Carbon;
use Karnoweb\Crm\Events\AffinityThresholdReached;
use Karnoweb\Crm\Models\Interaction;
use Karnoweb\Crm\Models\Interest;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\AffinityDecayCalculator;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\ScoringPolicy;

/**
 * Rebuilds Interest and Lead projection fields from Interaction rows.
 *
 * Interest.score = Σ (effective_weight × decay(age_in_days))
 * effective_weight = interaction.weight × scoring.weights[type]
 */
final class AffinityScoringService
{
    public function __construct(
        private readonly ScoringPolicy $policy,
        private readonly AffinityDecayCalculator $decay,
    ) {}

    public function apply(Interaction $interaction): void
    {
        $lead = Lead::query()->whereKey($interaction->lead_id)->lockForUpdate()->firstOrFail();

        $previousScore = $this->interestScore($lead, $interaction->subject_group, $interaction->subject_key);
        $interest = $this->rebuildInterest($lead, $interaction->subject_group, $interaction->subject_key, $interaction->subject_label);
        $this->rebuildLeadProjections($lead);

        $threshold = $this->policy->affinityThreshold();

        if ($previousScore < $threshold && $interest->score >= $threshold) {
            CrmEventDispatcher::dispatch(new AffinityThresholdReached(
                $lead->getKey(),
                $interest->subject_group,
                $interest->subject_key,
                (float) $interest->score,
                $threshold,
            ));
        }
    }

    public function recalculateLead(Lead $lead): void
    {
        $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

        $subjects = Interaction::query()
            ->where('lead_id', $lead->getKey())
            ->select('subject_group', 'subject_key')
            ->distinct()
            ->get();

        foreach ($subjects as $subject) {
            $this->rebuildInterest($lead, $subject->subject_group, $subject->subject_key);
        }

        $known = $subjects->map(fn ($row) => $row->subject_group . "\0" . $row->subject_key)->all();

        Interest::query()
            ->where('lead_id', $lead->getKey())
            ->get()
            ->each(function (Interest $interest) use ($known): void {
                if (! in_array($interest->subject_group . "\0" . $interest->subject_key, $known, true)) {
                    $interest->delete();
                }
            });

        $this->rebuildLeadProjections($lead->fresh() ?? $lead);
    }

    public function scoreForInteraction(Interaction $interaction, ?Carbon $asOf = null): float
    {
        $asOf ??= Carbon::now();
        $ageInDays = $this->ageInDays($interaction->occurred_at, $asOf);

        return $this->policy->effectiveWeight($interaction->type, (float) $interaction->weight)
            * $this->decay->decay($ageInDays, $this->policy->halfLifeDays());
    }

    private function rebuildInterest(Lead $lead, string $subjectGroup, string $subjectKey, ?string $subjectLabel = null): Interest
    {
        $asOf = Carbon::now();

        $interactions = Interaction::query()
            ->where('lead_id', $lead->getKey())
            ->where('subject_group', $subjectGroup)
            ->where('subject_key', $subjectKey)
            ->orderBy('occurred_at')
            ->get();

        $score = 0.0;
        foreach ($interactions as $interaction) {
            $score += $this->scoreForInteraction($interaction, $asOf);
        }

        $label = $subjectLabel
            ?? $interactions->last()?->subject_label;

        return Interest::query()->updateOrCreate(
            [
                'lead_id' => $lead->getKey(),
                'subject_group' => $subjectGroup,
                'subject_key' => $subjectKey,
            ],
            [
                'subject_label' => $label,
                'score' => $score,
                'interactions_count' => $interactions->count(),
                'first_interacted_at' => $interactions->first()?->occurred_at,
                'last_interacted_at' => $interactions->last()?->occurred_at,
            ],
        );
    }

    private function rebuildLeadProjections(Lead $lead): void
    {
        $aggregates = Interaction::query()
            ->where('lead_id', $lead->getKey())
            ->selectRaw('COUNT(*) as total_interactions')
            ->selectRaw('MIN(occurred_at) as first_seen_at')
            ->selectRaw('MAX(occurred_at) as last_interaction_at')
            ->first();

        $score = (int) round((float) Interest::query()->where('lead_id', $lead->getKey())->sum('score'));
        $total = (int) ($aggregates?->total_interactions ?? 0);
        $lastAt = $aggregates?->last_interaction_at;

        $recency = null;
        if ($lastAt !== null) {
            $recency = (int) round($this->ageInDays(Carbon::parse($lastAt), Carbon::now()));
        }

        $lead->forceFill([
            'score' => $score,
            'total_interactions' => $total,
            'first_seen_at' => $aggregates?->first_seen_at,
            'last_interaction_at' => $lastAt,
            'rfm_frequency_score' => $total > 0 ? $total : null,
            'rfm_recency_score' => $recency,
        ])->save();
    }

    private function interestScore(Lead $lead, string $subjectGroup, string $subjectKey): float
    {
        $existing = Interest::query()
            ->where('lead_id', $lead->getKey())
            ->where('subject_group', $subjectGroup)
            ->where('subject_key', $subjectKey)
            ->first();

        return (float) ($existing?->score ?? 0.0);
    }

    private function ageInDays(mixed $occurredAt, Carbon $asOf): float
    {
        $occurred = $occurredAt instanceof Carbon ? $occurredAt : Carbon::parse((string) $occurredAt);

        return max(0.0, floor($occurred->diffInSeconds($asOf) / 86400));
    }
}
