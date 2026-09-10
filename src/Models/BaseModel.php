<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    public function getTable(): string
    {
        $prefix = (string) config('crm.tables.prefix', 'crm_');
        $table = $this->table ?? str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));

        if ($prefix !== '' && str_starts_with($table, $prefix)) {
            return $table;
        }

        return $prefix . $table;
    }
}
