<?php

namespace Spatie\LaravelAiEvals\Transcript;

enum ToolCallStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Pending = 'pending';
    case NoResult = 'no_result';

    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }
}
