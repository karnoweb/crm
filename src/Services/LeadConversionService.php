<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Karnoweb\Crm\Enums\LeadStatus;
use Karnoweb\Crm\Events\LeadConverted;
use Karnoweb\Crm\Exceptions\CustomerTerminalException;
use Karnoweb\Crm\Exceptions\LeadAlreadyLinkedException;
use Karnoweb\Crm\Models\Lead;
use Karnoweb\Crm\Support\CrmEventDispatcher;
use Karnoweb\Crm\Support\QueryExceptionClassifier;

final class LeadConversionService
{
    /**
     * Conversion در CRM فقط به معنی تغییر lifecycle status (`→ customer`) و اتصال optional `user_id` است؛ ساخت هیچ domain entity دیگری (Customer، Order، Invoice) جزو مسئولیت این متد نیست.
     *
     * Idempotent: if the Lead already has the same user_id, the current row is
     * returned and LeadConverted is not published again.
     */
    public function convert(Lead $lead, int|string $userId): Lead
    {
        return DB::transaction(function () use ($lead, $userId): Lead {
            $lead = Lead::query()->whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ((string) $lead->user_id === (string) $userId && $lead->status === LeadStatus::Customer) {
                return $lead;
            }

            if ($lead->status === LeadStatus::Customer) {
                throw new CustomerTerminalException('Lead status customer is terminal in v1.');
            }

            $statusChanged = $lead->status !== LeadStatus::Customer;

            try {
                $lead->forceFill([
                    'user_id' => $userId,
                    'status' => LeadStatus::Customer,
                    'converted_at' => $lead->converted_at ?? Carbon::now(),
                    'last_status_change_at' => $statusChanged ? Carbon::now() : $lead->last_status_change_at,
                ])->save();
            } catch (QueryException $e) {
                if (QueryExceptionClassifier::isUniqueViolationOn($e, 'user_id')) {
                    throw new LeadAlreadyLinkedException("user_id [{$userId}] is already linked to another Lead.");
                }

                throw $e;
            }

            CrmEventDispatcher::dispatch(new LeadConverted($lead->getKey(), $userId));

            return $lead->refresh();
        });
    }
}
