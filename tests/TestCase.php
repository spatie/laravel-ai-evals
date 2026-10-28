<?php

namespace Spatie\LaravelAiEvals\Tests;

use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelAiEvals\AiEvals;
use Spatie\LaravelAiEvals\Plugin;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Plugin::enableEvalMode();
    }

    protected function tearDown(): void
    {
        Plugin::resetEvalMode();

        AiEvals::flush();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('ai.conversations.generate_title', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/ai/database/migrations');
    }
}
