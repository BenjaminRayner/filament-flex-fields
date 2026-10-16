<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Paths follow the mounted schema, including repeaters and named form roots. */
trait ResolvesState
{
    protected function isRootMounted(): bool
    {
        try {
            $this->getContainer();

            return true;
        } catch (\Error) {
            return false;
        }
    }

    protected function resolveOwnStatePath(): string
    {
        return $this->isRootMounted() ? ($this->getStatePath() ?? $this->getName()) : $this->getName();
    }

    protected function resolveSourcePath(string $source): string
    {
        $own = $this->resolveOwnStatePath();
        $root = Str::before($own, '.');
        if (str_contains($own, '.') && str_starts_with($source, $root.'.')) {
            return $source;
        }
        $parent = str_contains($own, '.') ? Str::beforeLast($own, '.') : '';

        return $parent === '' ? $source : $parent.'.'.$source;
    }

    protected function resolveSiblingStatePath(string $name): string
    {
        return str_contains($name, '.') ? $name : $this->resolveSourcePath($name);
    }

    protected function resolveRecord(): ?Model
    {
        $record = $this->isRootMounted() ? $this->getRecord() : null;

        return $record instanceof Model ? $record : null;
    }

    public function getOperation(): string
    {
        return $this->isRootMounted() ? $this->getContainer()->getOperation() : 'create';
    }

    protected function readFormAttributeValue(string $name, ?Get $get = null): mixed
    {
        $absolute = $this->resolveSourcePath($name);
        $value = $get !== null ? $get($absolute, isAbsolute: true)
            : ($this->isRootMounted() ? data_get($this->getLivewire(), $absolute) : null);

        return is_scalar($value) || is_array($value) || $value === null ? $value : null;
    }

    protected function readSiblingFormValue(string $name, ?Get $get = null): ?string
    {
        $value = $this->readFormAttributeValue($name, $get);
        if (is_array($value)) {
            $value = $value[$this->getSlugSourceLocale()] ?? null;
        }

        return $value === null ? null : (string) $value;
    }

    /** @return array<string, mixed> */
    protected function collectModelFormAttributes(?Get $get = null): array
    {
        $class = $this->getSpatieModelClass();
        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return [];
        }
        $model = $this->resolveRecord() ?? new $class;
        $parent = Str::beforeLast($this->resolveOwnStatePath(), '.');
        $state = $this->isRootMounted() ? data_get($this->getLivewire(), $parent, []) : [];
        $names = array_unique([...array_keys($model->getAttributes()), ...$model->getFillable(), ...array_keys(is_array($state) ? $state : [])]);
        $values = [];
        foreach ($names as $name) {
            if (! is_string($name) || in_array($name, [$model->getKeyName(), 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $value = $this->readFormAttributeValue($name, $get);
            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        return $values;
    }
}
