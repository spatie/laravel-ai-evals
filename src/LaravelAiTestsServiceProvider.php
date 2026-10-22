<?php

namespace Spatie\LaravelAiTests;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelAiTests\Http\Controllers\ResultsIndexController;
use Spatie\LaravelAiTests\Http\Controllers\ShowResultController;
use Spatie\LaravelAiTests\Http\Middleware\EnsureLocalEnvironment;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAiTestsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ai-tests')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        if (! config('ai-tests.viewer.enabled')) {
            return;
        }

        Route::prefix(config('ai-tests.viewer.prefix'))
            ->middleware(EnsureLocalEnvironment::class)
            ->group(function (): void {
                Route::get('/', ResultsIndexController::class)->name('ai-tests.index');

                Route::get('{slug}', ShowResultController::class)
                    ->where('slug', '[a-z0-9-]+')
                    ->name('ai-tests.show');
            });
    }
}
