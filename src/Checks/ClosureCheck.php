<?php

namespace Spatie\LaravelAiTests\Checks;

use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;
use Throwable;

class ClosureCheck extends Check
{
    /** @param Closure(Transcript): mixed $check */
    public function __construct(
        protected string $label,
        protected Closure $check,
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function kind(): string
    {
        return 'code';
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        try {
            $result = DB::transaction(fn (): mixed => ($this->check)($transcript));
        } catch (Throwable $exception) {
            return new CheckOutcome($this, passed: false, reason: $exception->getMessage());
        }

        return match (true) {
            $result === true => new CheckOutcome($this, passed: true),
            $result === false => new CheckOutcome($this, passed: false),
            is_string($result) => new CheckOutcome($this, passed: false, reason: $result),
            default => new CheckOutcome($this, passed: false, reason: 'Returned '.get_debug_type($result).' instead of true, false or a failure reason.'),
        };
    }
}
