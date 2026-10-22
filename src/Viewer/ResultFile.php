<?php

namespace Spatie\LaravelAiTests\Viewer;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ResultFile
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $slug,
        public array $data,
        public ?string $error = null,
    ) {}

    public static function fromPath(string $path): self
    {
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            report($exception);

            return new self(basename($path, '.yaml'), [], $exception->getMessage());
        }

        return new self(basename($path, '.yaml'), is_array($data) ? $data : []);
    }

    /** @return Collection<int, self> */
    public static function allIn(string $directory): Collection
    {
        if (! File::isDirectory($directory)) {
            return collect();
        }

        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'yaml')
            ->map(fn (SplFileInfo $file): self => self::fromPath($file->getPathname()))
            ->sortBy([
                fn (self $a, self $b): int => ($a->status() !== 'failed') <=> ($b->status() !== 'failed'),
                fn (self $a, self $b): int => strcasecmp($a->title(), $b->title()),
            ])
            ->values();
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function title(): string
    {
        return Value::string($this->get('title')) ?? $this->slug;
    }

    public function text(string $key): ?string
    {
        return Value::string($this->get($key));
    }

    public function status(): string
    {
        $passed = $this->get('passed');

        if (! is_bool($passed)) {
            return 'unknown';
        }

        return $passed ? 'passed' : 'failed';
    }

    /** @return array<string, mixed> */
    public function facts(): array
    {
        return array_filter([
            'Model' => $this->text('model'),
            'Judge' => $this->text('judge_model'),
            'Seeder' => $this->text('seeder'),
            'Test file' => $this->text('test_file'),
        ]);
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
