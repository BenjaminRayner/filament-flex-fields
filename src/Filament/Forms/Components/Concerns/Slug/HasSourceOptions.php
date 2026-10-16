<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use InvalidArgumentException;

trait HasSourceOptions
{
    protected string|Closure|null $slugOptionSource = null;

    protected bool|Closure $slugOptionSourceLive = true;

    protected bool|Closure $slugOptionTitleReadOnly = false;

    protected bool|Closure $slugOptionSlugReadOnly = false;

    public function source(string|Closure|null $value): static
    {
        $this->slugOptionSource = $value;

        return $this;
    }

    public function sourceLive(bool|Closure $value = true): static
    {
        $this->slugOptionSourceLive = $value;

        return $this;
    }

    public function isSourceLive(): bool
    {
        return (bool) $this->evaluate($this->slugOptionSourceLive);
    }

    public function titleReadOnly(bool|Closure $value = true): static
    {
        $this->slugOptionTitleReadOnly = $value;

        return $this;
    }

    public function isTitleReadOnly(): bool
    {
        return (bool) $this->evaluate($this->slugOptionTitleReadOnly);
    }

    public function slugReadOnly(bool|Closure $value = true): static
    {
        $this->slugOptionSlugReadOnly = $value;

        return $this;
    }

    protected ?Field $slugTitleField = null;

    protected ?Closure $slugTitleWrapper = null;

    protected ?Closure $slugTitleUpdated = null;

    protected ?Closure $slugValueUpdated = null;

    public function titleField(Field $field): static
    {
        $this->slugTitleField = clone $field;
        if ($this->slugOptionSource === null) {
            $this->source($field->getName());
        }

        return $this;
    }

    public function titleFieldWrapper(?Closure $callback): static
    {
        $this->slugTitleWrapper = $callback;

        return $this;
    }

    public function titleAfterStateUpdated(?Closure $callback): static
    {
        $this->slugTitleUpdated = $callback;

        return $this;
    }

    public function slugAfterStateUpdated(?Closure $callback): static
    {
        $this->slugValueUpdated = $callback;

        return $this;
    }

    public function isSlugReadOnly(): bool
    {
        return $this->isReadOnly() || (bool) $this->evaluate($this->slugOptionSlugReadOnly);
    }

    public function getConfiguredTitleField(): ?Field
    {
        if ($this->slugTitleField === null) {
            return null;
        }
        $field = clone $this->slugTitleField;
        $path = $this->resolveSourcePath($field->getName());
        $parent = $this->isRootMounted() ? ($this->getContainer()->getStatePath() ?? '') : '';
        $field->statePath($parent !== '' && str_starts_with($path, $parent.'.') ? substr($path, strlen($parent) + 1) : $path)->live($this->isSourceLive());
        if ($field instanceof TextInput) {
            $field->readOnly($this->isTitleReadOnly());
        }
        if ($this->slugTitleUpdated !== null) {
            $field->afterStateUpdated($this->slugTitleUpdated);
        }
        if ($this->isRootMounted()) {
            $field->container($this->getContainer());
        }
        if ($this->slugTitleWrapper !== null) {
            $wrapped = $this->evaluate($this->slugTitleWrapper, ['field' => $field]);
            if ($wrapped !== null && ! $wrapped instanceof Field) {
                throw new InvalidArgumentException('The slug title wrapper must return a Field or null.');
            }

            return $wrapped ?? $field;
        }

        return $field;
    }
}
