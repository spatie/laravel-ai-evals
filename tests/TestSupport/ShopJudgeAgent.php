<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

use Spatie\LaravelAiEvals\Judge\JudgeAgent;

class ShopJudgeAgent extends JudgeAgent
{
    public function instructions(): string
    {
        return 'You grade the answers of a web shop assistant.';
    }
}
