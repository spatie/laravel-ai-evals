<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];
}
