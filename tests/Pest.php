<?php

use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Results\EvaluationResult;
use Spatie\LaravelAiEvals\Runner;
use Spatie\LaravelAiEvals\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function runEvaluation(Evaluation $evaluation): EvaluationResult
{
    return app(Runner::class)->run($evaluation);
}

/** @return array<int, array{0: string, 1: bool, 2: ?string}> */
function outcomes(EvaluationResult $result, int $run = 1, int $turn = 1): array
{
    return array_map(
        fn (CheckOutcome $outcome) => [$outcome->label, $outcome->passed, $outcome->reason],
        $result->runs[$run - 1]->turns[$turn - 1]->outcomes,
    );
}

function failureMessage(Evaluation $evaluation): string
{
    try {
        $evaluation->execute();
    } catch (AssertionFailedError $exception) {
        return $exception->getMessage();
    }

    throw new RuntimeException("The evaluation \"{$evaluation->description}\" passed.");
}
