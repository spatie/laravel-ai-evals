<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CancelOrderTool implements Tool
{
    public function description(): string
    {
        return 'Cancel an order by its number.';
    }

    public function handle(Request $request): string
    {
        return "Order {$request['order']} was cancelled.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order' => $schema->string()->required(),
        ];
    }
}
