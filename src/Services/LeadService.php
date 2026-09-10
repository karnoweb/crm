<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Events\LeadCreated;
use Karnoweb\Crm\Exceptions\CustomerTerminalException;
use Karnoweb\Crm\Exceptions\InvalidLeadStatusTransitionException;
use Karnoweb\Crm\Exceptions\LeadNotFoundException;
use Karnoweb\Crm\Models\Interest;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\QueryExceptionClassifier;

final class LeadService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data): Lead {
            $status = $this->resolveStatus($data['status'] ?? LeadStatus::New);

            $lead = Lead::query()->create([
                'user_id' => $data['user_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'source' => $data['source'] ?? null,
                'status' => $status,
                'captured_at' => $data['captured_at'] ?? Carbon::now(),
                'last_status_change_at' => null,
                'attributes' => $data['attributes'] ?? null,
            ]);

            CrmEventDispatcher::dispatch(new LeadCreated($lead->getKey()));

            return $lead;
        });
    }

    public function firstOrCreateForUser(int|string $userId): Lead
    {
        try {
            return DB::transaction(function () use ($userId): Lead {
                $lead = Lead::query()->create([
                    'user_id' => $userId,
                    'status' => LeadStatus::Customer,
                    'source' => 'system',
                    'captured_at' => Carbon::now(),
                    'last_status_change_at' => null,
                ]);

                CrmEventDispatcher::dispatch(new LeadCreated($lead->getKey()));

                return $lead;
            });
        } catch (QueryException $e) {
            if (! QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id')) {
                throw $e;
            }

            return Lead::query()->where('user_id', $userId)->firstOrFail();
        }
    }

    /**
     * Update contact and assignment fields. Status changes use {@see changeStatus()}.
     *
     * @param array<string, mixed> $data
     */
    public function update(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data): Lead {
            $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            $attributes = [];
            foreach (['name', 'phone', 'email', 'source', 'branch_id', 'assigned_to'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if ($attributes !== []) {
                $lead->forceFill($attributes)->save();
            }

            return $lead->refresh();
        });
    }

    public function archive(Lead $lead): Lead
    {
        return DB::transaction(function () use ($lead): Lead {
            $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($lead->archived_at !== null) {
                return $lead;
            }

            $lead->forceFill(['archived_at' => Carbon::now()])->save();

            return $lead;
        });
    }

    /**
     * @return Collection<int, Interest>
     */
    public function topInterests(int $leadId, ?string $subjectGroup = null, int $limit = 5): Collection
    {
        $lead = Lead::query()->whereKey($leadId)->first();

        if ($lead === null) {
            throw new LeadNotFoundException("Lead [{$leadId}] was not found.");
        }

        return $lead->rankedInterests($subjectGroup, $limit);
    }

    /**
     * Non-customer statuses may move between themselves. customer has no outbound transition.
     */
    public function changeStatus(Lead $lead, LeadStatus $status): Lead
    {
        return DB::transaction(function () use ($lead, $status): Lead {
            $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($lead->status === LeadStatus::Customer) {
                throw new CustomerTerminalException('Lead status customer is terminal in v1.');
            }

            if ($status === LeadStatus::Customer) {
                throw new InvalidLeadStatusTransitionException('Lead status customer must be set via LeadConversionService::convert().');
            }

            if ($lead->status === $status) {
                return $lead;
            }

            $lead->forceFill([
                'status' => $status,
                'last_status_change_at' => Carbon::now(),
            ])->save();

            return $lead;
        });
    }

    private function resolveStatus(mixed $status): LeadStatus
    {
        if ($status instanceof LeadStatus) {
            return $status;
        }

        return LeadStatus::from((string) $status);
    }
}
