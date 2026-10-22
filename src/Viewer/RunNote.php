<?php

namespace Spatie\LaravelAiTests\Viewer;

class RunNote
{
    public function __construct(
        public int $run,
        public string $check,
        public bool $passed,
        public ?string $reason,
    ) {}

    public function checkHtml(): string
    {
        return Html::withCode($this->check);
    }
}
