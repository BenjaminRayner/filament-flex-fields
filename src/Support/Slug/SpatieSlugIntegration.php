<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug;

use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\OptionsResolver;
use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\PreviewContext;
use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\PreviewGenerator;
use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\PreviewResult;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\Actions\BuildSelfHealingRouteKeyAction;
use Spatie\Sluggable\SlugOptions;
use Spatie\Sluggable\Support\Config;

/** Stable optional integration API; policy and preview modules contain the implementation. */
class SpatieSlugIntegration
{
    public static function isAvailable(): bool
    {
        return OptionsResolver::available();
    }

    /** @param array<string, mixed> $formAttributes */
    public static function generate(string $source, Model $model, string $slugField = 'slug', ?string $sourceField = null, array $formAttributes = [], ?string $locale = null, bool $force = false, ?string $currentSlug = null): string
    {
        return self::preview($source, $model, $slugField, $sourceField, $formAttributes, $locale, $force, $currentSlug)->value;
    }

    /** @param array<string, mixed> $formAttributes */
    public static function preview(string $source, Model $model, string $slugField = 'slug', ?string $sourceField = null, array $formAttributes = [], ?string $locale = null, bool $force = false, ?string $currentSlug = null): PreviewResult
    {
        $context = PreviewContext::create($model, $source, $slugField, $sourceField, $formAttributes, $locale);

        return $context === null ? new PreviewResult(SlugGenerator::fromString($source, locale: $locale), true)
            : PreviewGenerator::run($context, $force, $currentSlug);
    }

    /** @return list<string> */
    public static function resolveSourceFieldNames(SlugOptions $options, ?string $fallbackSourceField = null): array
    {
        return OptionsResolver::sources($options, $fallbackSourceField);
    }

    /** @param callable(string):(?string) $readSiblingValue
     * @return array<string, scalar|null>
     */
    public static function collectFormAttributes(Model $model, SlugOptions $options, string $primarySource, ?string $primaryField, ?string $configuredSourceField, callable $readSiblingValue): array
    {
        $primary = $configuredSourceField ?? $primaryField;
        $attributes = filled($primary) ? [$primary => $primarySource] : [];
        foreach (OptionsResolver::sources($options, $primary) as $field) {
            if (! array_key_exists($field, $attributes) && ($value = $readSiblingValue($field)) !== null) {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }

    public static function resolveSlugOptions(Model $model, string $fallbackSlugField): ?SlugOptions
    {
        return OptionsResolver::forModel($model, $fallbackSlugField);
    }

    public static function resolveSlugOptionsForModelClass(string $modelClass, string $fallbackSlugField = 'slug'): ?SlugOptions
    {
        return OptionsResolver::forClass($modelClass, $fallbackSlugField);
    }

    public static function usesSelfHealingUrls(Model $model, string $fallbackSlugField = 'slug'): bool
    {
        return self::resolveSlugOptions($model, $fallbackSlugField)?->selfHealingUrls === true;
    }

    public static function getSelfHealingSeparator(Model $model, string $fallbackSlugField = 'slug'): string
    {
        return self::resolveSlugOptions($model, $fallbackSlugField)?->selfHealingSeparator ?? SlugGenerator::DEFAULT_SEPARATOR;
    }

    public static function buildSelfHealingRouteKey(string $slug, Model $record, string $fallbackSlugField = 'slug'): string
    {
        $options = self::resolveSlugOptions($record, $fallbackSlugField);
        $key = $record->getKey();
        if ($options === null || ! $options->selfHealingUrls || $key === null) {
            return $slug;
        }

        return Config::getAction(Config::ACTION_BUILD_SELF_HEALING_ROUTE_KEY, BuildSelfHealingRouteKeyAction::class)
            ->execute($slug, $key, $options->selfHealingSeparator);
    }
}
