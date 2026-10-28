<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

class ShippingApiTool
{
    public function __construct(
        protected string $apiKey,
    ) {}

    public function name(): string
    {
        return 'track_shipment';
    }
}
