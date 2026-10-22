<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LookupOrderTool implements Tool
{
    public function name(): string
    {
        return 'lookup_order';
    }

    public function description(): string
    {
        return 'Look up an order by its number.';
    }

    public function handle(Request $request): string
    {
        return "Order {$request['order']} shipped yesterday.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order' => $schema->string()->required(),
        ];
    }
}
