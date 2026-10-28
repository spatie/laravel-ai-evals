<?php

namespace Spatie\LaravelAiEvals;

use Illuminate\Support\Env;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use LogicException;
use Spatie\LaravelAiEvals\Judge\JudgeAgent;

class AiEvals
{
    /** @var class-string<Agent>|Agent */
    protected static string|Agent $judge = JudgeAgent::class;

    protected static int $repeat = 1;

    protected static float $minPassRate = 1.0;

    /** @param class-string<Agent>|Agent $agent */
    public static function judgeUsing(string|Agent $agent): void
    {
        if (! is_subclass_of($agent, HasStructuredOutput::class)) {
            $name = is_string($agent) ? $agent : $agent::class;

            throw new LogicException("The judge agent {$name} must implement ".HasStructuredOutput::class.'. Extend '.JudgeAgent::class.' to keep its output schema.');
        }

        static::$judge = $agent;
    }

    public static function repeat(int $times, ?float $minPassRate = null): void
    {
        static::$repeat = $times;

        if ($minPassRate !== null) {
            static::$minPassRate = $minPassRate;
        }
    }

    public static function judgeAgent(): Agent
    {
        return is_string(static::$judge) ? app(static::$judge) : static::$judge;
    }

    public static function judgeProvider(): ?string
    {
        return Env::get('PEST_EVALS_LARAVEL_SCORING_PROVIDER') ?: null;
    }

    public static function judgeModel(): ?string
    {
        return Env::get('PEST_EVALS_LARAVEL_SCORING_MODEL') ?: null;
    }

    public static function repeatCount(?int $repeat): int
    {
        $forcedRepeat = Env::get('AI_EVALS_REPEAT');

        if ($forcedRepeat !== null && $forcedRepeat !== '') {
            return (int) $forcedRepeat;
        }

        return $repeat ?? static::$repeat;
    }

    public static function minPassRate(?float $minPassRate): float
    {
        return $minPassRate ?? static::$minPassRate;
    }

    public static function flush(): void
    {
        static::$judge = JudgeAgent::class;
        static::$repeat = 1;
        static::$minPassRate = 1.0;
    }
}
