<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Locale\LocaleContext;
use Illuminate\Translation\Translator;

final class Translations
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $translation = __($key, $replace, $locale);

        return is_string($translation) ? $translation : $key;
    }

    /** @param array<string, mixed> $replace */
    public static function forContext(string $key, LocaleContext $context, array $replace = []): string
    {
        $translator = app(Translator::class);
        foreach ($context->translationChain as $locale) {
            $translation = $translator->get($key, $replace, $locale, false);
            if (is_string($translation) && $translation !== $key) {
                return $translation;
            }
        }

        return $key;
    }
}
