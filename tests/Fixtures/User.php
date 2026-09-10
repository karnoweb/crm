<?php

declare(strict_types=1);

namespace Karnoweb\Crm\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
