<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Metadata;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Small JSON documents, revalidated at persistence rather than trusting browser form state. */
final class EditorialMetadata
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     * @param  list<string>  $translated
     * @param  list<string>  $locales
     * @param  array<string, mixed>  $customRules
     * @return array<string, mixed>
     */
    public static function validate(array $data, array $fields, array $translated = [], array $locales = [], array $customRules = []): array
    {
        if (count($fields) > 32 || count($locales) > 32 || strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 65536) {
            throw new InvalidArgumentException('File metadata exceeds its configured document budget.');
        }
        if (in_array('tags', $translated, true)) {
            throw new InvalidArgumentException('Metadata tags cannot be translated.');
        }
        $rules = ['metadata' => ['array:'.implode(',', $fields)]];
        foreach ($fields as $name) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1 || $name === 'flex_capture') {
                throw new InvalidArgumentException('Metadata field names must be simple application keys.');
            }
            if (in_array($name, $translated, true) && $locales !== []) {
                $rules['metadata.'.$name] = ['nullable', 'array:'.implode(',', $locales)];
                foreach ($locales as $locale) {
                    $rules['metadata.'.$name.'.'.$locale] = ['nullable', 'string', 'max:4000'];
                }
            } elseif ($name === 'tags') {
                $rules['metadata.tags'] = ['nullable', 'array', 'max:50'];
                $rules['metadata.tags.*'] = ['string', 'max:80', 'distinct'];
            } else {
                $rules['metadata.'.$name] = ['nullable', 'string', 'max:4000'];
            }
        }
        foreach ($customRules as $path => $rule) {
            if (! in_array(explode('.', $path)[0], $fields, true)) {
                throw new InvalidArgumentException('Custom metadata rules must belong to declared editor fields.');
            }
            $rules['metadata.'.$path] = [...($rules['metadata.'.$path] ?? []), ...(is_string($rule) ? explode('|', $rule) : (is_array($rule) ? $rule : [$rule]))];
        }
        Validator::make(['metadata' => $data], $rules)->validate();

        return $data;
    }

    /** Resolve text without materializing fallback translations in the stored document.
     * @param  array<string, mixed>  $data
     */
    public static function text(array $data, string $field, ?string $locale = null, ?string $fallbackLocale = null): ?string
    {
        $value = $data[$field] ?? null;
        if (is_array($value)) {
            $value = $locale === null ? null : ($value[$locale] ?? null);
            if ((! is_string($value) || trim($value) === '') && $fallbackLocale !== null) {
                $value = $data[$field][$fallbackLocale] ?? null;
            }
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
