<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Locale;

use InvalidArgumentException;

/** Instance-owned formatting and translation availability; never mutates app locale. */
final readonly class LocaleContext
{
    public LocaleIdentifier $formatting;

    /** @var list<string> */
    public array $translationChain;

    public string $direction;

    /** @param list<string>|null $translationLocales */
    public function __construct(
        ?string $locale = null,
        ?array $translationLocales = null,
        string $fallback = 'en',
        ?string $direction = null,
    ) {
        $formatting = new LocaleIdentifier(FlexFieldsLocale::formattingLocale($locale));
        $translationLocales ??= FlexFieldsLocale::supportedLocales();
        if (! array_is_list($translationLocales) || $translationLocales === [] || count($translationLocales) > 128) {
            throw new InvalidArgumentException('Invalid FLEX translation locale catalog.');
        }
        $available = [];
        foreach ($translationLocales as $candidate) {
            if (! is_string($candidate)) {
                throw new InvalidArgumentException('Invalid FLEX translation locale.');
            }
            $available[(new LocaleIdentifier($candidate))->php] = true;
        }
        $fallbackLocale = (new LocaleIdentifier($fallback))->php;
        if (! isset($available[$fallbackLocale])) {
            throw new InvalidArgumentException('The FLEX translation fallback must be available.');
        }
        $chain = [];
        foreach ([...$formatting->candidates(), $fallbackLocale] as $candidate) {
            if (isset($available[$candidate])) {
                $chain[$candidate] = true;
            }
        }
        if ($direction !== null && ! in_array($direction, ['ltr', 'rtl'], true)) {
            throw new InvalidArgumentException('Invalid FLEX text direction.');
        }
        $this->formatting = $formatting;
        $this->translationChain = array_keys($chain);
        $this->direction = $direction ?? $formatting->direction();
    }
}
