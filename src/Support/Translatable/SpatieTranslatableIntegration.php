<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class SpatieTranslatableIntegration
{
    public static function isAvailable(): bool
    {
        return trait_exists('Spatie\\Translatable\\HasTranslations');
    }

    public static function modelUsesTranslatable(mixed $model, string $attribute): bool
    {
        return $model instanceof Model
            && self::isAvailable()
            && in_array('Spatie\\Translatable\\HasTranslations', class_uses_recursive($model), true)
            && method_exists($model, 'getTranslatableAttributes')
            && in_array($attribute, $model->getTranslatableAttributes(), true);
    }

    public static function requireAvailable(): void
    {
        if (! self::isAvailable()) {
            throw new LogicException('TranslatableFields::spatieTranslatable() requires the optional spatie/laravel-translatable package. Install ^6.0 or use JSON/array storage without this option.');
        }
    }

    /** @return array<string, mixed> */
    public static function translations(Model $model, string $attribute): array
    {
        self::requireAvailable();
        if (! self::modelUsesTranslatable($model, $attribute)) {
            throw new LogicException("The model must use HasTranslations and declare [{$attribute}] as translatable.");
        }

        // Read the complete map: editing must never materialize fallback text.
        $reader = [$model, 'getTranslations'];
        if (! is_callable($reader)) {
            throw new LogicException('HasTranslations must expose getTranslations().');
        }
        /** @var array<string, mixed> $translations */
        $translations = $reader($attribute);

        $mutatorKey = str_replace('->', '-', $attribute);
        if ($model->hasGetMutator($mutatorKey) || $model->hasAttributeMutator($mutatorKey)) {
            $translationReader = [$model, 'getTranslation'];
            if (! is_callable($translationReader)) {
                throw new LogicException('HasTranslations must expose getTranslation().');
            }
            foreach (array_keys($translations) as $locale) {
                // Existing locales only: getters work without invoking missing-key fallbacks.
                $translations[$locale] = $translationReader($attribute, $locale, false);
            }
        }

        return $translations;
    }

    /** @param class-string<Model>|null $modelClass */
    public static function assertCompatible(?string $modelClass, string $attribute, bool $spatieTranslatable): void
    {
        if (! $spatieTranslatable) {
            return;
        }
        self::requireAvailable();
        if ($modelClass === null) {
            return;
        }
        if (! is_subclass_of($modelClass, Model::class) || ! self::modelUsesTranslatable(new $modelClass, $attribute)) {
            throw new LogicException("Spatie translation storage requires a compatible Eloquent model for [{$attribute}].");
        }
    }

    /** @param class-string<Model>|null $modelClass */
    public static function shouldUseSpatieHydration(?string $modelClass, string $attribute, bool $spatieTranslatable): bool
    {
        self::assertCompatible($modelClass, $attribute, $spatieTranslatable);

        return $spatieTranslatable;
    }
}
