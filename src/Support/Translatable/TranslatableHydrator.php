<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use WeakMap;

/** Prepares locale maps before native field hydration; never replaces field hooks. */
final class TranslatableHydrator
{
    /** @var WeakMap<Field, TranslatableFieldBinding>|null */
    private static ?WeakMap $bindings = null;

    public static function applyToField(Field $field, string $storageAttribute, string $locale, bool $spatie = false, ?string $outputAttribute = null): void
    {
        self::$bindings ??= new WeakMap;
        self::$bindings[$field] = new TranslatableFieldBinding($storageAttribute, $locale, $spatie, $outputAttribute);
    }

    /** @param array<string, mixed> $state */
    public static function dehydrateNestedAttribute(Field $field, array &$state): void
    {
        $binding = self::$bindings[$field] ?? null;
        if ($binding?->outputAttribute === null) {
            return;
        }
        $path = $field->getStatePath();
        $parent = substr($path ?? '', 0, -strlen($binding->locale) - 1);
        $attributePath = str_replace('->', '.', $binding->outputAttribute);
        if (! str_ends_with($parent, $attributePath) || ! Arr::has($state, $parent)) {
            return;
        }
        $updated = $state;
        $output = substr($parent, 0, -strlen($attributePath)).$binding->outputAttribute;
        Arr::set($updated, $output, Arr::get($updated, $parent));
        Arr::forget($updated, $parent);
        // Do not submit an empty JSON parent beside its Spatie arrow attribute.
        $ancestor = substr($parent, 0, (int) strrpos($parent, '.'));
        while ($ancestor !== '' && Arr::get($updated, $ancestor) === []) {
            Arr::forget($updated, $ancestor);
            $ancestor = str_contains($ancestor, '.') ? substr($ancestor, 0, (int) strrpos($ancestor, '.')) : '';
        }
        // Arrow remapping changes nested values, not the string keys of the root.
        /** @var array<string, mixed> $updated */
        $state = $updated;
    }

    public static function resolveRenderedState(Field $field): mixed
    {
        // Null/empty are authoritative user edits, not requests for record fallback.
        return $field->getState();
    }

    /**
     * Prepare once per parent attribute, even with many locale children.
     *
     * @param  array<string, true>  $preparedPaths
     */
    public static function prepareField(Field $field, bool $fromRecord, array &$preparedPaths): void
    {
        $binding = self::$bindings[$field] ?? null;
        if ($binding === null) {
            return;
        }
        $path = $field->getStatePath();
        if (! is_string($path) || ! str_ends_with($path, '.'.$binding->locale)) {
            return;
        }
        $parentPath = substr($path, 0, -strlen($binding->locale) - 1);
        if (isset($preparedPaths[$parentPath])) {
            return;
        }
        $preparedPaths[$parentPath] = true;
        $livewire = $field->getLivewire();
        $missing = new \stdClass;
        $value = data_get($livewire, $parentPath, $missing);
        $translations = SpatieTranslatableState::translations($value);
        $record = $field->getRecord();

        if ($binding->spatie && $record === null) {
            SpatieTranslatableIntegration::assertCompatible($field->getModel(), $binding->attribute, true);
        }
        if ($binding->spatie && $record instanceof Model) {
            // Validate opt-in even when the caller supplies an explicit state map.
            if (! SpatieTranslatableIntegration::modelUsesTranslatable($record, $binding->attribute)) {
                SpatieTranslatableIntegration::translations($record, $binding->attribute);
            }
        }
        if ($translations === null && $fromRecord && ($value === $missing || ($value !== null && ! is_array($value)) || (is_array($value) && SpatieTranslatableState::looksLikeTipTapDocument($value)))) {
            $translations = self::recordTranslations($record, $binding);
        }
        if ($translations === null) {
            return;
        }
        foreach ($translations as $locale => $translation) {
            $translations[$locale] = SpatieTranslatableState::hydrate($translation);
        }
        data_set($livewire, $parentPath, $translations);
    }

    public static function resolveLocaleState(Field $component, string $storageAttribute, string $locale): mixed
    {
        $path = $component->getStatePath();
        $parent = is_string($path) && str_ends_with($path, '.'.$locale) ? substr($path, 0, -strlen($locale) - 1) : $component->resolveRelativeStatePath($storageAttribute, isAbsolute: true);
        $translations = SpatieTranslatableState::translations(data_get($component->getLivewire(), $parent));

        return $translations[$locale] ?? null;
    }

    public static function hydrateFromFormState(Field $component, string $storageAttribute, string $locale): void
    {
        $component->state(self::resolveLocaleState($component, $storageAttribute, $locale));
    }

    public static function hydrateFromRecord(Field $component, mixed $record, string $storageAttribute, string $locale): void
    {
        $binding = self::$bindings[$component] ?? new TranslatableFieldBinding($storageAttribute, $locale, false);
        $translations = self::recordTranslations($record, $binding);
        $component->state(SpatieTranslatableState::hydrate($translations[$locale] ?? null));
    }

    /** @return array<string, mixed>|null */
    private static function recordTranslations(mixed $record, TranslatableFieldBinding $binding): ?array
    {
        if ($binding->spatie && $record instanceof Model) {
            return SpatieTranslatableIntegration::translations($record, $binding->attribute);
        }
        $attributes = $record instanceof Model ? $record->getAttributes() : (is_array($record) ? $record : []);
        $segments = explode('->', $binding->attribute);
        $value = $attributes[array_shift($segments)] ?? null;
        if ($segments !== []) {
            $value = Arr::get(is_string($value) ? json_decode($value, true) : $value, implode('.', $segments));
        }

        return SpatieTranslatableState::translations($value);
    }
}
