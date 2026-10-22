<?php

namespace Spatie\LaravelAiTests\Judge;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class JudgeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        You grade one turn of a conversation between a user and an AI assistant.

        You get the user's message, every tool call the assistant made in this turn with its arguments, status and a truncated result, and the assistant's answer. Then you get numbered criteria.

        Judge each criterion on its own, strictly and literally. A criterion passes only when the transcript clearly satisfies it. Treat the tool calls and results as the ground truth: when the answer claims an action or a fact, check it against them. A claimed change only counts when the matching tool call completed. Long tool results are truncated and end with "...": a fact missing from a truncated result is not evidence that the answer invented it. Do not reward effort or partial answers.

        Return one verdict per criterion, in the given order, with a one or two sentence reason that points at the evidence.
        PROMPT;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'verdicts' => $schema->array()
                ->items($schema->object([
                    'criterion' => $schema->integer()->description('The criterion number.')->required(),
                    'reason' => $schema->string()->description('Why the criterion passes or fails, pointing at the evidence.')->required(),
                    'pass' => $schema->boolean()->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }
}
