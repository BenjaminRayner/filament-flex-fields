<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocales;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableTitle;
use Closure;

trait HasTranslationOptions
{
    protected bool|Closure $slugOptionTranslatableTitle = false;

    protected string|Closure|null $slugOptionSlugSourceLocale = null;

    protected bool|Closure $slugOptionSpatieTranslatable = false;

    protected string|Closure|null $slugOptionTranslatableTitleField = null;

    public function translatableTitle(bool|Closure $value = true): static
    {
        $this->slugOptionTranslatableTitle = $value;

        return $this;
    }

    public function slugSourceLocale(string|Closure|null $value): static
    {
        $this->slugOptionSlugSourceLocale = $value;

        return $this;
    }

    public function spatieTranslatable(bool|Closure $value = true): static
    {
        $this->slugOptionSpatieTranslatable = $value;

        return $this;
    }

    public function shouldUseSpatieTranslatable(): bool
    {
        return (bool) $this->evaluate($this->slugOptionSpatieTranslatable);
    }

    public function translatableTitleField(string|Closure|null $value): static
    {
        $this->slugOptionTranslatableTitleField = $value;

        return $this;
    }

    /** @var array<string, string>|list<string>|Closure|null */
    protected array|Closure|null $slugLocales = null;

    /** @param array<string, string>|list<string>|Closure $locales */
    public function titleLocales(array|Closure $locales): static
    {
        $this->slugLocales = $locales;

        return $this->translatableTitle();
    }

    /** @return array<string, string> */
    public function getTitleLocales(): array
    {
        $locales = $this->evaluate($this->slugLocales);

        return TranslatableLocales::resolve(is_array($locales) ? $locales : (FlexFieldsConfig::getSlugTranslatableLocales() ?? []));
    }

    public function usesTranslatableTitle(): bool
    {
        return (bool) $this->evaluate($this->slugOptionTranslatableTitle) && $this->getTitleLocales() !== [];
    }

    public function getSlugSourceLocale(): string
    {
        $configured = $this->evaluate($this->slugOptionSlugSourceLocale);
        $record = $this->resolveRecord();
        $locale = filled($configured) ? (string) $configured : (FlexFieldsConfig::getSlugSourceLocale()
            ?? ($record !== null && method_exists($record, 'getLocale') ? $record->getLocale() : (string) config('app.locale', 'en')));
        // Validate locale syntax without rewriting exact regional JSON keys.
        TranslatableLocales::resolve([$locale]);
        $locales = $this->getTitleLocales();
        if ($locales === [] || array_key_exists($locale, $locales)) {
            return $locale;
        }
        foreach (array_keys($locales) as $key) {
            if (strcasecmp($key, $locale) === 0) {
                return $key;
            }
        }

        return array_key_first($locales);
    }

    public function getTranslatableTitleFieldName(): string
    {
        $name = $this->evaluate($this->slugOptionTranslatableTitleField);
        if (is_string($name) && $name !== '') {
            return $name;
        }
        $source = $this->evaluate($this->slugOptionSource);
        if (! is_string($source) || $source === '') {
            return (string) config('filament-flex-fields.slug.field_title', 'title');
        }
        $parts = explode('.', $source);
        if (count($parts) > 1 && array_key_exists(end($parts), $this->getTitleLocales())) {
            array_pop($parts);
        }

        return implode('.', $parts);
    }

    public function getSourceStatePath(): ?string
    {
        if ($this->usesTranslatableTitle()) {
            return $this->resolveSourcePath(TranslatableTitle::sourcePath($this->getTranslatableTitleFieldName(), $this->getSlugSourceLocale()));
        }
        $source = $this->evaluate($this->slugOptionSource);

        return is_string($source) && $source !== '' ? $this->resolveSourcePath($source) : null;
    }
}
