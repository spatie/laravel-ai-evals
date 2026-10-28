<?php

namespace Spatie\LaravelAiEvals\Checks;

use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;
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

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        try {
            $result = DB::transaction(fn (): mixed => ($this->check)($transcript));
        } catch (Throwable $exception) {
            return new CheckOutcome($this->label, passed: false, reason: $exception::class.': '.$exception->getMessage());
        }

        return match (true) {
            $result === true => new CheckOutcome($this->label, passed: true),
            $result === false => new CheckOutcome($this->label, passed: false),
            is_string($result) => new CheckOutcome($this->label, passed: false, reason: $result),
            default => new CheckOutcome($this->label, passed: false, reason: 'Returned '.get_debug_type($result).' instead of true, false or a failure reason.'),
        };
    }
}
