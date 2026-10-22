<?php

namespace Spatie\LaravelAiTests\Viewer;

class RunNote
{
    public function __construct(
        public ?int $run,
        public ?string $check,
        public string $html,
    ) {}
}
