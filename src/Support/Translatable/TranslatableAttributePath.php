<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Translatable;

use Filament\Forms\Components\Field;

/**
 * Resolves form state paths and model storage attributes for locale fields.
 */
final class TranslatableAttributePath
{
    public static function relativeBasePath(Field $template): string
    {
        $relativePath = $template->getStatePath(isAbsolute: false);
        $name = $template->getName();
        if (str_contains($name, '->') && $relativePath === str_replace('>', '', $name)) {
            return str_replace('->', '.', $name);
        }

        if (filled($relativePath)) {
            return (string) $relativePath;
        }

        return (string) $template->getName();
    }

    public static function localeStatePath(Field $template, string $locale): string
    {
        TranslatableLocales::assertLocale($locale);

        return self::relativeBasePath($template).'.'.$locale;
    }

    public static function storageAttribute(Field $template): string
    {
        return (string) $template->getName();
    }
}
