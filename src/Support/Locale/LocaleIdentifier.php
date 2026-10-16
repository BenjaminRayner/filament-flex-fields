<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Locale;

use InvalidArgumentException;

/** Bounded language/script/region/variant/extension IDs for PHP and browser formatting. */
final readonly class LocaleIdentifier
{
    public string $php;

    public string $tag;

    public string $language;

    public ?string $script;

    private string $base;

    public function __construct(string $locale)
    {
        if (strlen($locale) > 128 || preg_match('/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{1,8})*$/D', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid FLEX locale identifier.');
        }
        $parts = explode('_', str_replace('-', '_', $locale));
        $language = strtolower(array_shift($parts));
        $normalized = [$language];
        $script = null;
        if (isset($parts[0]) && preg_match('/^[a-zA-Z]{4}$/D', $parts[0]) === 1) {
            $script = ucfirst(strtolower(array_shift($parts)));
            $normalized[] = $script;
        }
        if (isset($parts[0]) && preg_match('/^(?:[a-zA-Z]{2}|[0-9]{3})$/D', $parts[0]) === 1) {
            $normalized[] = strtoupper(array_shift($parts));
        }
        $variants = [];
        while (isset($parts[0]) && strlen($parts[0]) !== 1) {
            $part = array_shift($parts);
            $part = strtolower($part);
            if (preg_match('/^(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})$/D', $part) !== 1 || isset($variants[$part])) {
                throw new InvalidArgumentException('Invalid FLEX locale variant.');
            }
            $variants[$part] = true;
            $normalized[] = $part;
        }
        $this->base = implode('_', $normalized);
        $normalized = [...$normalized, ...LocaleExtensions::normalize($parts)];
        $this->php = implode('_', $normalized);
        $this->tag = implode('-', $normalized);
        $this->language = $language;
        $this->script = $script;
    }

    /** @return list<string> */
    public function candidates(): array
    {
        $parts = explode('_', $this->base);
        // Remove extensions as a complete block, never produce dangling singleton keys.
        $candidates = $this->php === $this->base ? [] : [$this->php];
        do {
            $candidates[] = implode('_', $parts);
            array_pop($parts);
        } while ($parts !== []);

        return $candidates;
    }

    public function direction(): string
    {
        if ($this->script !== null) {
            return in_array($this->script, ['Arab', 'Hebr', 'Syrc', 'Thaa', 'Nkoo', 'Adlm', 'Rohg', 'Samr', 'Mand'], true) ? 'rtl' : 'ltr';
        }

        return in_array($this->language, ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv'], true) ? 'rtl' : 'ltr';
    }
}
