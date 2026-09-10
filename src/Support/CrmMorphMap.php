<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Karnoweb\Crm\Exceptions\UnsupportedMorphTargetException;
use Karnoweb\Crm\Models\Deal;
use Karnoweb\Crm\Models\Lead;

/**
 * CRM-owned morph aliases. Registered with Relation::morphMap() (merge), never enforceMorphMap().
 */
final class CrmMorphMap
{
    public const LEAD = 'crm_lead';

    public const DEAL = 'crm_deal';

    /**
     * @return array<string, class-string>
     */
    public static function aliases(): array
    {
        return [
            self::LEAD => Lead::class,
            self::DEAL => Deal::class,
        ];
    }

    public static function register(): void
    {
        Relation::morphMap(self::aliases());
    }

    public static function aliasFor(object $subject): string
    {
        return match (true) {
            $subject instanceof Lead => self::LEAD,
            $subject instanceof Deal => self::DEAL,
            default => throw new UnsupportedMorphTargetException(
                'CRM polymorphic targets may only be Lead or Deal. Host App\\Models are not allowed.'
            ),
        };
    }
}
