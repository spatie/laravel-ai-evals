<?php

namespace Spatie\LaravelAiEvals\Checks;

use Illuminate\Support\Str;
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
        $contains = Str::contains($transcript->answer(), $this->needle, ignoreCase: true);

        return $this->outcome($contains, 'Does not contain '.static::quote($this->needle).'.');
    }
}
