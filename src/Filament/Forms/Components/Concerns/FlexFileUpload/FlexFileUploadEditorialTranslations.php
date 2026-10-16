<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Metadata\EditorialMetadata;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocales;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

/** @mixin BaseFileUpload */
trait FlexFileUploadEditorialTranslations
{
    /** @var array<string, string>|list<string>|Closure|null */
    protected array|Closure|null $flexEditorialLocales = null;

    /** @var list<string> */
    protected array $flexEditorialTranslated = ['title', 'alt', 'caption', 'copyright'];

    protected string|Closure|null $flexEditorialDefaultLocale = null;

    protected bool|Closure $flexEditorialFallback = false;

    /** @var list<string> */
    protected array $flexEditorialRequiredInDefaultLocale = [];

    /** @param array<string, string>|list<string>|Closure|null $locales
     * @param  list<string>  $fields
     */
    public function fileMetadataTranslations(array|Closure|null $locales, array $fields = ['title', 'alt', 'caption', 'copyright']): static
    {
        if (in_array('tags', $fields, true)) {
            throw new InvalidArgumentException('Metadata tags are shared identities and cannot be translated fields.');
        }
        $this->flexEditorialLocales = $locales;
        $this->flexEditorialTranslated = $fields;

        return $this;
    }

    public function fileMetadataDefaultLocale(string|Closure|null $locale): static
    {
        $this->flexEditorialDefaultLocale = $locale;

        return $this;
    }

    /** Fallback resolves display text only; it never writes a missing translation. */
    public function fileMetadataFallback(bool|Closure $condition = true): static
    {
        $this->flexEditorialFallback = $condition;

        return $this;
    }

    /** @param list<string> $fields */
    public function fileMetadataRequiredInDefaultLocale(array $fields): static
    {
        $this->flexEditorialRequiredInDefaultLocale = $fields;

        return $this;
    }

    /** @return array<string, string> */
    public function getFileMetadataLocales(): array
    {
        return $this->flexEditorialLocales === null ? [] : TranslatableLocales::resolve($this->evaluate($this->flexEditorialLocales));
    }

    public function getFileMetadataDefaultLocale(): ?string
    {
        $locales = $this->getFileMetadataLocales();
        $locale = $this->evaluate($this->flexEditorialDefaultLocale) ?? array_key_first($locales);
        if ($locale !== null && (! is_string($locale) || ! isset($locales[$locale]))) {
            throw new InvalidArgumentException('The metadata default locale must belong to the configured locales.');
        }

        return $locale;
    }

    /** @param array<string, mixed> $metadata */
    public function resolveFileEditorialText(array $metadata, string $field, ?string $locale = null): ?string
    {
        $templates = $this->getFileMetadataTemplates();
        $locales = array_keys($this->getFileMetadataLocales());
        $data = EditorialMetadata::validate($metadata, array_map(static fn (Field $template): string => $template->getName(), $templates), $this->flexEditorialTranslated, $locales);
        $default = $this->getFileMetadataDefaultLocale();
        $locale ??= $default;
        if ($locale !== null && ! in_array($locale, $locales, true)) {
            throw new InvalidArgumentException('Display locale must belong to the metadata locales.');
        }

        return EditorialMetadata::text($data, $field, $locale, $this->evaluate($this->flexEditorialFallback) ? $default : null);
    }

    /** Read through the normal record/collection/access-policy authorization boundary. */
    public function getFileEditorialText(string $fileKey, string $field, ?string $locale = null): ?string
    {
        return $this->resolveFileEditorialText($this->readFileEditorialMetadata($fileKey), $field, $locale);
    }

    private function configureEditorialField(Field $field, string $path, bool $customRules = true): Field
    {
        [$name, $locale] = array_pad(explode('.', $path, 2), 2, null);
        if (in_array($name, $this->flexEditorialRequiredInDefaultLocale, true) && $locale === $this->getFileMetadataDefaultLocale()) {
            $field->required();
        }
        if ($customRules && isset($this->flexEditorialRules[$path])) {
            $rules = $this->flexEditorialRules[$path];
            $field->rules(is_string($rules) || is_array($rules) ? $rules : [$rules]);
        }

        return $field;
    }
}
