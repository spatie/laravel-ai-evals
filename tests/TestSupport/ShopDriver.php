<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Spatie\LaravelAiTests\Drivers\AgentDriver;

class ShopDriver extends AgentDriver
{
    public function __construct(
        public readonly User $customer,
    ) {
        parent::__construct(SupportAgent::class, as: $customer);
    }

    public static function forNewCustomer(): self
    {
        return new self(User::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
        ]));
    }
}
