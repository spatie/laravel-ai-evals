<?php

use Spatie\LaravelAiEvals\Evaluation;

if (! function_exists('evaluate')) {
    function evaluate(string $description): Evaluation
    {
        $evaluation = new Evaluation($description);

        test($description, fn () => $evaluation->execute());

        return $evaluation;
    }
}
