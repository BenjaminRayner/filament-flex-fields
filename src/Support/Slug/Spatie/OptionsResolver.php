<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionFunction;
use Spatie\Sluggable\Actions\GenerateSlugAction;
use Spatie\Sluggable\SlugOptions;
use Spatie\Sluggable\Support\SluggableAttributeResolver;

/** Never mutate Spatie's attribute cache or options shared by a model instance. */
final class OptionsResolver
{
    public static function available(): bool
    {
        return class_exists(SlugOptions::class) && class_exists(GenerateSlugAction::class);
    }

    public static function forModel(Model $model, string $target = 'slug'): ?SlugOptions
    {
        if (! self::available()) {
            return null;
        }
        $options = method_exists($model, 'getSlugOptions') ? $model->getSlugOptions()
            : (class_exists(SluggableAttributeResolver::class) ? SluggableAttributeResolver::resolveOptions($model::class) : null);
        if ($options === null) {
            return null;
        }
        if (! $options instanceof SlugOptions) {
            throw new InvalidArgumentException('getSlugOptions() must return Spatie SlugOptions.');
        }
        $resolved = clone $options;
        if (! isset($resolved->slugField) || $resolved->slugField === '') {
            $resolved->saveSlugsTo($target);
        }
        // Cached option closures can still be bound to the original record. Only
        // model-bound closures transfer; service-bound/static closures stay intact.
        foreach (['generateSlugFrom', 'skipGenerateWhen', 'extraScopeCallback', 'suffixGenerator'] as $property) {
            if (isset($resolved->{$property}) && $resolved->{$property} instanceof Closure) {
                $callback = $resolved->{$property};
                $bound = (new ReflectionFunction($callback))->getClosureThis();
                if ($bound instanceof Model && $bound::class === $model::class) {
                    $resolved->{$property} = $callback->bindTo($model) ?? $callback;
                }
            }
        }

        return $resolved;
    }

    public static function forClass(string $class, string $target = 'slug'): ?SlugOptions
    {
        return self::available() && class_exists($class) && is_subclass_of($class, Model::class)
            ? self::forModel(new $class, $target) : null;
    }

    /** @return list<string> */
    public static function sources(SlugOptions $options, ?string $fallback = null): array
    {
        if (isset($options->generateSlugFrom) && is_array($options->generateSlugFrom)) {
            return array_values($options->generateSlugFrom);
        }

        return filled($fallback) ? [$fallback] : [];
    }
}
