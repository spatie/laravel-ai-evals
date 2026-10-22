<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public User $user;

    public function run(): void
    {
        $this->user = User::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
        ]);
    }
}
