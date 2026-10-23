<?php

namespace Spatie\LaravelAiTests\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelAiTests\LaravelAiTestsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        File::deleteDirectory(config('ai-tests.results_path'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LaravelAiTestsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('ai.conversations.generate_title', false);
        $app['config']->set('ai-tests.results_path', sys_get_temp_dir().'/laravel-ai-tests/results');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/ai/database/migrations');
    }
}
