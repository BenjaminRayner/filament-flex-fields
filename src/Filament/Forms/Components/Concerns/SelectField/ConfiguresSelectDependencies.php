<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

/** @mixin SelectField */
trait ConfiguresSelectDependencies
{
    /** @var list<string> */
    protected array $selectDependencyPaths = [];

    protected bool|Closure $resetOnDependencyChange = false;

    /** Clear the child immediately in the browser; keep server-side reset/validation. */
    public function resetOnDependencyChange(bool|Closure $condition = true): static
    {
        $this->resetOnDependencyChange = $condition;

        return $this;
    }

    public function shouldResetOnDependencyChange(): bool
    {
        return (bool) $this->evaluate($this->resetOnDependencyChange);
    }

    /**
     * Parent fields must synchronize their state (for example through live()).
     * Paths follow Filament Get's relative, ancestor and repeater resolution.
     *
     * @param  string|list<string>  $paths
     * @param  Closure(mixed ...$parentValues): array<array-key, mixed>  $resolveOptions
     */
    public function dependsOn(string|array $paths, Closure $resolveOptions): static
    {
        $paths = is_array($paths) ? array_values($paths) : [$paths];
        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException('Select dependencies must be non-empty state paths.');
            }
        }

        $this->selectDependencyPaths = $paths;

        return $this->options(function (Get $get) use ($paths, $resolveOptions): array {
            $values = [];
            foreach ($paths as $path) {
                $values[] = $get($path);
            }

            $resolved = $resolveOptions(...$values);

            return is_array($resolved) ? $resolved : [];
        });
    }

    /** @return list<string> */
    public function getSelectDependencyStatePaths(): array
    {
        return array_values(array_unique(array_map(
            fn (string $path): string => $this->resolveRelativeStatePath($path),
            $this->selectDependencyPaths,
        )));
    }
}
