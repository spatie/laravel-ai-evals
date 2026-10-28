<?php

namespace Spatie\LaravelAiEvals;

use Illuminate\Support\Env;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;

class Plugin implements HandlesArguments
{
    use HandleArguments;

    protected const EvalModeVariable = 'PEST_EVALS';

    protected static bool $evalMode = false;

    public static function isEvalMode(): bool
    {
        return static::$evalMode
            || Env::get(static::EvalModeVariable) === '1'
            || Parallel::getGlobal(static::EvalModeVariable) === true;
    }

    public static function enableEvalMode(): void
    {
        static::$evalMode = true;
        $_SERVER[static::EvalModeVariable] = '1';
        $_ENV[static::EvalModeVariable] = '1';
        putenv(static::EvalModeVariable.'=1');

        Parallel::setGlobal(static::EvalModeVariable, true);
    }

    public static function resetEvalMode(): void
    {
        static::$evalMode = false;
        unset($_SERVER[static::EvalModeVariable], $_ENV[static::EvalModeVariable]);
        putenv(static::EvalModeVariable);

        $parallelVariable = 'PEST_PARALLEL_GLOBAL_'.static::EvalModeVariable;
        unset($_SERVER[$parallelVariable], $_ENV[$parallelVariable]);
        putenv($parallelVariable);
    }

    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--evals', $arguments)) {
            return $arguments;
        }

        static::enableEvalMode();

        return $this->popArgument('--evals', $arguments);
    }
}
