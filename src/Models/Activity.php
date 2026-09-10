<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return MorphTo<Lead|Deal, $this>
     */
    public function activityable(): MorphTo
    {
        return $this->morphTo();
    }
}
