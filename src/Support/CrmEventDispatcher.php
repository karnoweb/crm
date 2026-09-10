<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Support\Facades\DB;

/**
 * Publishes every Output API event after the database transaction commits.
 *
 * There is no escape hatch: rollback must never execute host listeners.
 */
final class CrmEventDispatcher
{
    public static function dispatch(object $event): void
    {
        DB::afterCommit(static fn () => event($event));
    }
}
