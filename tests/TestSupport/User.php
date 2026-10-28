<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];
}
