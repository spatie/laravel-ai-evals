<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

use Spatie\LaravelAiEvals\Drivers\AgentDriver;

class ShopDriver extends AgentDriver
{
    public function __construct(
        public readonly User $customer,
    ) {
        parent::__construct(SupportAgent::class, as: $customer);
    }

    public static function forNewCustomer(): static
    {
        return new static(User::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
        ]));
    }
}
