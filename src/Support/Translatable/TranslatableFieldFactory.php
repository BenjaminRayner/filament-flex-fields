<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields\TranslatableTab;
use Closure;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

final class TranslatableFieldFactory
{
    /**
     * @param  Closure(Field, string, TranslatableTab): Field|null  $localeFieldUsing
     * @param  Closure(Field, string): string|null  $storageAttributeUsing
     */
    public static function make(Field $template, string $locale, TranslatableTab $tab, bool $spatieTranslatable = false, ?Closure $localeFieldUsing = null, ?Closure $storageAttributeUsing = null, bool $useTemplateStatePath = false): Field
    {
        $injections = ['template' => $template, 'locale' => $locale, 'tab' => $tab];
        $custom = $localeFieldUsing === null ? null : $template->evaluate($localeFieldUsing, $injections);
        if ($custom !== null && ! $custom instanceof Field) {
            throw new InvalidArgumentException('localeFieldUsing must return a Filament Field or null.');
        }
        // Isolate custom renderers too; their own paths remain compatible by default.
        $field = ($custom ?? $template)->getClone();
        $path = TranslatableAttributePath::localeStatePath($template, $locale);
        /** @var Closure|null $storageResolver */
        $storageResolver = $storageAttributeUsing;
        $attribute = $storageResolver === null ? TranslatableAttributePath::storageAttribute($template) : $template->evaluate($storageResolver, $injections);
        if (! is_string($attribute) || trim($attribute) === '') {
            throw new InvalidArgumentException('storageAttributeUsing must return a non-empty model attribute.');
        }
        if ($spatieTranslatable) {
            SpatieTranslatableIntegration::requireAvailable();
        }
        if ($custom === null || $useTemplateStatePath) {
            $field->name($path)->statePath($path);
        }
        TranslatableHydrator::applyToField($field, $attribute, $locale, $spatieTranslatable,
            $spatieTranslatable && str_contains($template->getName(), '->') ? $template->getName() : null);

        return $field;
    }
}
