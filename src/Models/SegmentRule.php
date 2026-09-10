<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Crm\Enums\SegmentRuleField;
use Karnoweb\Crm\Enums\SegmentRuleOperator;

class SegmentRule extends BaseModel
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => SegmentRuleField::class,
            'operator' => SegmentRuleOperator::class,
            'value' => 'json',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function scalarValue(): mixed
    {
        $value = $this->value;

        if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
            return $value['value'];
        }

        return $value;
    }
}
