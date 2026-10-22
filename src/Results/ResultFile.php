<?php

namespace Spatie\LaravelAiTests\Results;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ResultFile
{
    /** @param array<string, mixed> $frontmatter */
    public function __construct(
        public string $slug,
        public array $frontmatter,
        public string $body,
    ) {}

    public static function fromPath(string $path): self
    {
        $contents = File::get($path);
        $slug = basename($path, '.md');

        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $matches) !== 1) {
            return new self($slug, [], $contents);
        }

        return new self($slug, Yaml::parse($matches[1]) ?? [], $matches[2]);
    }

    public function get(string $key): mixed
    {
        return $this->frontmatter[$key] ?? null;
    }

    public function title(): string
    {
        return (string) ($this->get('title') ?? $this->slug);
    }

    public function status(): string
    {
        $passed = $this->get('passed');

        if (! is_bool($passed)) {
            return 'unknown';
        }

        return $passed ? 'passed' : 'failed';
    }

    public function ranAt(): ?CarbonImmutable
    {
        $ranAt = $this->get('ran_at');

        try {
            return match (true) {
                is_int($ranAt) => CarbonImmutable::createFromTimestamp($ranAt),
                is_string($ranAt) => CarbonImmutable::parse($ranAt),
                default => null,
            };
        } catch (InvalidFormatException) {
            return null;
        }
    }

    public function percentage(string $key): ?string
    {
        $value = $this->get($key);

        return is_numeric($value) ? round((float) $value * 100).'%' : null;
    }
}
