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

    /** @param array<string, mixed> $data */
    public static function fromData(int $run, array $data): self
    {
        return new self(
            run: $run,
            check: (string) ($data['check'] ?? ''),
            passed: ($data['passed'] ?? false) === true,
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }

    public function checkHtml(): string
    {
        return Html::withCode($this->check);
    }
}
