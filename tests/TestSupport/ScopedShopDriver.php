<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Spatie\LaravelAiTests\Drivers\DescribesContext;

class ScopedShopDriver extends ShopDriver implements DescribesContext
{
    public function context(): array
    {
        return ['Scope' => 'All stores', 'Page' => 'Order details'];
    }
}
