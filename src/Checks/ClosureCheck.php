<?php

namespace Spatie\LaravelAiEvals\Checks;

use Closure;
use Illuminate\Support\Facades\DB;
use Pest\Expectation as PestExpectation;
use Pest\Expectations\EachExpectation;
use Pest\Expectations\HigherOrderExpectation;
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
            return $this->outcome(false, $exception::class.': '.$exception->getMessage());
        }

        return match (true) {
            $result === true, $this->isExpectation($result) => $this->outcome(true),
            $result === false => $this->outcome(false),
            is_string($result) => $this->outcome(false, $result),
            default => $this->outcome(false, 'Returned '.get_debug_type($result).' instead of true, false or a failure reason.'),
        };
    }

    protected function isExpectation(mixed $result): bool
    {
        return $result instanceof PestExpectation
            || $result instanceof HigherOrderExpectation
            || $result instanceof EachExpectation;
    }
}
