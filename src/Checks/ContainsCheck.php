<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class ContainsCheck extends Check
{
    public function __construct(
        protected string $needle,
    ) {}

    public function label(): string
    {
        return 'Contains '.static::quote($this->needle);
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $contains = mb_stripos($transcript->answer(), $this->needle) !== false;

        return $this->outcome($contains, 'Does not contain '.static::quote($this->needle).'.');
    }
}
