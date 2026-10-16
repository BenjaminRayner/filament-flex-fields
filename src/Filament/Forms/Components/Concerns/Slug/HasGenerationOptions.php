<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Bjanczak\FilamentFlexFields\Support\Slug\SlugGenerator;
use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\PreviewContext;
use Bjanczak\FilamentFlexFields\Support\Slug\SpatieSlugIntegration;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Sluggable\SlugOptions;

trait HasGenerationOptions
{
    protected string|Closure|null $slugOptionSpatieModel = null;

    protected string|Closure $slugOptionSpatieSlugField = 'slug';

    protected string|Closure|null $slugOptionSpatieSourceField = null;

    protected string|Closure|null $slugOptionSlugSeparator = null;

    protected int|Closure|null $slugOptionMaxSlugLength = null;

    protected string|Closure|null $slugOptionRecordSlug = null;

    protected bool|Closure $slugOptionServerSideGeneration = false;

    public function spatieModel(string|Closure|null $value): static
    {
        $this->slugOptionSpatieModel = $value;

        return $this;
    }

    public function spatieSlugField(string|Closure $value = 'slug'): static
    {
        $this->slugOptionSpatieSlugField = $value;

        return $this;
    }

    public function spatieSourceField(string|Closure|null $value): static
    {
        $this->slugOptionSpatieSourceField = $value;

        return $this;
    }

    public function slugSeparator(string|Closure $value = '-'): static
    {
        $this->slugOptionSlugSeparator = $value;

        return $this;
    }

    public function maxSlugLength(int|Closure|null $value): static
    {
        $this->slugOptionMaxSlugLength = $value;

        return $this;
    }

    public function recordSlug(string|Closure|null $value): static
    {
        $this->slugOptionRecordSlug = $value;

        return $this;
    }

    public function serverSideGeneration(bool|Closure $value = true): static
    {
        $this->slugOptionServerSideGeneration = $value;

        return $this;
    }

    protected ?Closure $slugGeneratorCallback = null;

    public function slugifyUsing(?Closure $callback): static
    {
        $this->slugGeneratorCallback = $callback;

        return $this;
    }

    public function getSeparator(): string
    {
        $configured = $this->evaluate($this->slugOptionSlugSeparator);
        $options = $configured === null ? $this->getSpatieSlugOptions() : null;
        $separator = $configured ?? ($options !== null ? $options->slugSeparator : '-');
        if ($separator === '' || strlen($separator) > 8 || preg_match('~[a-z0-9\s/]~i', $separator)) {
            throw new InvalidArgumentException('A slug separator must be nonempty punctuation, at most eight bytes.');
        }

        return $separator;
    }

    public function getMaxSlugLength(): ?int
    {
        $length = $this->evaluate($this->slugOptionMaxSlugLength);

        return is_numeric($length) && (int) $length > 0 ? (int) $length : null;
    }

    public function getSpatieSourceField(): ?string
    {
        $value = $this->evaluate($this->slugOptionSpatieSourceField);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getSpatieSlugField(): string
    {
        return (string) $this->evaluate($this->slugOptionSpatieSlugField);
    }

    public function getSpatieModelClass(): ?string
    {
        $configured = $this->evaluate($this->slugOptionSpatieModel);
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        if (($record = $this->resolveRecord()) !== null) {
            return $record::class;
        }
        $model = $this->isRootMounted() ? $this->getModel() : null;

        return is_string($model) && $model !== '' ? $model : null;
    }

    public function usesSpatieIntegration(): bool
    {
        return $this->getSpatieSlugOptions() !== null;
    }

    /** Resolve policies from the actual record, including model-dependent options. */
    public function getSpatieSlugOptions(): ?SlugOptions
    {
        $class = $this->getSpatieModelClass();
        if ($class === null || ! is_subclass_of($class, Model::class) || ! SpatieSlugIntegration::isAvailable()) {
            return null;
        }
        $record = $this->resolveRecord();

        return SpatieSlugIntegration::resolveSlugOptions($record instanceof $class ? $record : new $class, $this->getSpatieSlugField());
    }

    /** Hydrate a disposable native model for live/final uniqueness policies. */
    protected function resolveSpatieValidationContext(): ?PreviewContext
    {
        $class = $this->getSpatieModelClass();
        if ($this->getSpatieSlugOptions() === null || $class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }
        $record = $this->resolveRecord();

        return PreviewContext::create($record instanceof $class ? $record : new $class, null, $this->getSpatieSlugField(),
            $this->resolvePrimarySourceFieldName(), $this->collectModelFormAttributes(), $this->getSlugSourceLocale());
    }

    public function shouldUseServerSideGeneration(): bool
    {
        return $this->slugGeneratorCallback !== null || (bool) $this->evaluate($this->slugOptionServerSideGeneration)
            || $this->usesTranslatableTitle() || $this->usesSpatieIntegration();
    }

    public function getRecordSlug(): ?string
    {
        $value = $this->evaluate($this->slugOptionRecordSlug);
        if (! is_string($value) || $value === '') {
            $record = $this->resolveRecord();
            $column = $this->getSpatieSlugField();
            $value = $record !== null && method_exists($record, 'isTranslatableAttribute') && $record->isTranslatableAttribute($column) && method_exists($record, 'getTranslation')
                ? $record->getTranslation($column, $this->getSlugSourceLocale(), false)
                : $record?->getAttribute($column);
        }

        return is_string($value) && $value !== '' ? $this->normalizeSlug($value) : null;
    }

    public function normalizeSlug(string $value): string
    {
        return SlugGenerator::normalize($value, $this->getSeparator(), $this->allowsHomepageSlug());
    }

    public function generateSlugFromSource(string $source, ?Get $get = null, bool $force = false): string
    {
        if ($this->slugGeneratorCallback !== null) {
            $result = $this->evaluate($this->slugGeneratorCallback, ['source' => $source]);
            if (! is_string($result)) {
                throw new InvalidArgumentException('A slug generator must return a string.');
            }

            return $this->normalizeSlug($result);
        }
        $locale = $this->getSlugSourceLocale();
        if (! $this->usesSpatieIntegration()) {
            return SlugGenerator::fromString($source, $this->getSeparator(), $this->getMaxSlugLength(), $locale);
        }
        $class = $this->getSpatieModelClass();
        if ($class === null || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('The slug model must extend Eloquent Model.');
        }
        $record = $this->resolveRecord();
        $model = $record instanceof $class ? $record : new $class;
        $options = SpatieSlugIntegration::resolveSlugOptions($model, $this->getSpatieSlugField());
        if ($options === null) {
            throw new InvalidArgumentException('Missing Spatie slug options.');
        }
        $attributes = array_replace($this->collectModelFormAttributes($get), SpatieSlugIntegration::collectFormAttributes(
            $model, $options, $source, $this->resolvePrimarySourceFieldName(), $this->getSpatieSourceField(),
            fn (string $field): ?string => $this->readSiblingFormValue($field, $get),
        ));

        return $this->normalizeSlug(SpatieSlugIntegration::generate(
            $source, $model, $this->getSpatieSlugField(), $this->resolvePrimarySourceFieldName(), $attributes, $locale, $force,
            $this->isRootMounted() && is_string($this->getState()) ? $this->getState() : $this->getRecordSlug(),
        ));
    }

    protected function resolvePrimarySourceFieldName(): ?string
    {
        if ($this->getSpatieSourceField() !== null) {
            return $this->getSpatieSourceField();
        }
        $source = $this->usesTranslatableTitle() ? $this->getTranslatableTitleFieldName() : $this->evaluate($this->slugOptionSource);

        return is_string($source) && $source !== '' ? Str::afterLast($source, '.') : null;
    }
}
