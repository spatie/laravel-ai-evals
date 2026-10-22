<?php

namespace Spatie\LaravelAiTests\Transcript;

enum ToolCallStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Pending = 'pending';
}
