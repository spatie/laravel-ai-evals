<?php

namespace Spatie\LaravelAiTests;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelAiTests\Commands\LaravelAiTestsCommand;

class LaravelAiTestsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ai-tests')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_ai_tests_table')
            ->hasCommand(LaravelAiTestsCommand::class);
    }
}
