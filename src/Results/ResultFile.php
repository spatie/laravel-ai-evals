<?php

namespace Spatie\LaravelAiTests\Results;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Spatie\LaravelAiTests\Viewer\TurnSection;
use Symfony\Component\Yaml\Yaml;

class ResultFile
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $slug,
        public array $data,
    ) {}

    public static function fromPath(string $path): self
    {
        $data = Yaml::parseFile($path);

        return new self(basename($path, '.yaml'), is_array($data) ? $data : []);
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
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

    /** @return array<int, TurnSection> */
    public function turns(): array
    {
        $turns = is_array($this->get('turns')) ? array_values($this->get('turns')) : [];

        return array_map(
            fn (mixed $turn, int $index): TurnSection => TurnSection::fromData($index + 1, is_array($turn) ? $turn : []),
            $turns,
            array_keys($turns),
        );
    }
}
