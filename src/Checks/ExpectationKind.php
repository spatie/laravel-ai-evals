<?php

namespace Spatie\LaravelAiTests\Checks;

enum ExpectationKind: string
{
    case Tool = 'tool';
    case Mentions = 'mentions';
    case MaxWords = 'max_words';
    case Code = 'code';
    case Rubric = 'rubric';
}
