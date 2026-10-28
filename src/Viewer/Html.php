<?php

namespace Spatie\LaravelAiTests\Viewer;

use Illuminate\Support\Str;

class Html
{
    public static function withCode(string $text): string
    {
        return (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', e($text));
    }

    public static function markdown(string $markdown, int $parentHeadingLevel): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ], [new NestedHeadings($parentHeadingLevel)]);
    }
}
