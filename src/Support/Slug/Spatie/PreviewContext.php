<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Sluggable\HasTranslatableSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Sluggable\Support\TraitDetector;

/** A disposable model snapshot keeps originals, connection, casts and identity. */
final readonly class PreviewContext
{
    private function __construct(public Model $model, public SlugOptions $options, public string $locale, public bool $translated) {}

    /** @param array<string, mixed> $attributes */
    public static function create(Model $record, ?string $source, string $target, ?string $sourceField, array $attributes, ?string $locale): ?self
    {
        $model = clone $record;
        if ($locale !== null && method_exists($model, 'setLocale')) {
            $model->setLocale($locale);
        }
        $options = OptionsResolver::forModel($model, $target);
        if ($options === null) {
            return null;
        }
        // The form's previous preview is not a manual model override. The original
        // model's own customized slug is retained for Spatie's native policy.
        unset($attributes[$target], $attributes[$options->slugField]);
        foreach ($attributes as $name => $value) {
            $model->setAttribute($name, $value);
        }
        $sources = OptionsResolver::sources($options, $sourceField);
        if ($source !== null && $sourceField !== null && $sourceField !== $target && in_array($target, $sources, true)) {
            throw new InvalidArgumentException('The Spatie slug source points to its output column instead of the configured title source.');
        }
        $primary = $sourceField ?? ($sources[0] ?? null);
        if ($primary !== null && $source !== null) {
            $model->setAttribute($primary, $source);
        }
        // Resolve again after hydration: conditional options and model-bound
        // scopes must see the current form values, not stale database attributes.
        $options = OptionsResolver::forModel($model, $target);
        if ($options === null) {
            return null;
        }
        $options->saveSlugsTo($target);
        $translated = trait_exists(HasTranslatableSlug::class) && TraitDetector::uses($model, HasTranslatableSlug::class);
        $locale ??= method_exists($model, 'getLocale') ? $model->getLocale() : (string) config('app.locale', 'en');

        return new self($model, $options, $locale, $translated);
    }

    public function existing(): string
    {
        $value = $this->translated && method_exists($this->model, 'getTranslation')
            ? $this->model->getTranslation($this->options->slugField, $this->locale, false)
            : $this->model->getAttribute($this->options->slugField);

        return is_string($value) ? $value : '';
    }
}
