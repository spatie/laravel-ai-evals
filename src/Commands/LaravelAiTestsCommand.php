<?php

namespace Spatie\LaravelAiTests\Commands;

use Illuminate\Console\Command;

class LaravelAiTestsCommand extends Command
{
    public $signature = 'laravel-ai-tests';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
