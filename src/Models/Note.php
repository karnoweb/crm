<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends BaseModel
{
    /**
     * @return MorphTo<Lead|Deal, $this>
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
