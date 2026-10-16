<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Enums\FlexFieldSectionType;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldWidth;
use Bjanczak\FilamentFlexFields\Support\Values\FieldStoragePath;
use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;
use InvalidArgumentException;

/** Data shape shared by exchange, migration and database group validation. */
final class SchemaPayloadContract
{
    public const MAX_BYTES = 2097152;

    public const MAX_FIELDS = 256;

    public const MAX_SECTIONS = 128;

    public static function budget(): JsonDataBudget
    {
        return new JsonDataBudget(self::MAX_BYTES, 16384, 32);
    }

    /** @param array<string, mixed> $schema */
    public static function assert(array $schema): void
    {
        self::budget()->assert($schema);
        if (array_diff(array_keys($schema), ['key', 'label', 'target', 'version', 'fields', 'sections', 'active', 'sort']) !== []) {
            throw new InvalidArgumentException('Schema contains undeclared metadata keys.');
        }
        foreach (['fields' => self::MAX_FIELDS, 'sections' => self::MAX_SECTIONS] as $key => $limit) {
            if (array_key_exists($key, $schema) && (! is_array($schema[$key]) || ! array_is_list($schema[$key]) || count($schema[$key]) > $limit)) {
                throw new InvalidArgumentException('Schema '.$key.' requires a bounded list.');
            }
        }
        if (array_key_exists('version', $schema) && $schema['version'] !== 1) {
            throw new InvalidArgumentException('Unsupported schema payload version.');
        }
        foreach (['target', 'key', 'label'] as $key) {
            if (array_key_exists($key, $schema) && (! is_string($schema[$key]) || strlen($schema[$key]) > 1024 || str_contains($schema[$key], "\0"))) {
                throw new InvalidArgumentException('Schema '.$key.' must be bounded text.');
            }
        }
        if (array_key_exists('active', $schema) && ! is_bool($schema['active'])) {
            throw new InvalidArgumentException('Schema active must be a boolean.');
        }
        self::assertSort($schema);
        $paths = [];
        foreach ($schema['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException('Schema fields must be objects.');
            }
            foreach ([['default_value', 'defaultValue'], ['help_text', 'helpText'],
                ['is_required', 'isRequired', 'required'], ['is_active', 'isActive'], ['is_visible', 'isVisible'],
                ['hidden_label', 'hiddenLabel'], ['visible_when', 'visibleWhen'], ['required_when', 'requiredWhen'],
                ['disabled_when', 'disabledWhen'], ['formula', 'calculated'], ['is_encrypted', 'isEncrypted']] as $aliases) {
                $present = array_intersect_key($field, array_fill_keys($aliases, true));
                if (count($present) > 1) {
                    $first = reset($present);
                    foreach ($present as $value) {
                        if ($value !== $first) {
                            throw new InvalidArgumentException('Field contains conflicting '.$aliases[0].' aliases.');
                        }
                    }
                }
            }
            if (array_diff(array_keys($field), ['slug', 'label', 'type', 'config', 'validation', 'default_value', 'defaultValue',
                'help_text', 'helpText', 'placeholder', 'is_required', 'isRequired', 'is_active', 'isActive', 'is_visible', 'isVisible',
                'hidden_label', 'hiddenLabel', 'sort', 'visible_when', 'visibleWhen', 'required_when', 'requiredWhen',
                'disabled_when', 'disabledWhen', 'formula', 'calculated', 'section_id', 'width', 'is_encrypted', 'isEncrypted', 'required']) !== []) {
                throw new InvalidArgumentException('Field contains undeclared metadata keys; place field settings in config.');
            }
            if (isset($field['slug'])) {
                if (! is_string($field['slug'])) {
                    throw new InvalidArgumentException('Field slugs must be literal storage paths.');
                }
                FieldStoragePath::assert($field['slug']);
                $paths[$field['slug']] = true;
            }
            foreach (['is_required', 'isRequired', 'required', 'is_active', 'isActive', 'is_visible', 'isVisible', 'hidden_label', 'hiddenLabel', 'is_encrypted', 'isEncrypted'] as $key) {
                if (array_key_exists($key, $field) && ! is_bool($field[$key])) {
                    throw new InvalidArgumentException('Field '.$key.' must be a boolean.');
                }
            }
            foreach (['label', 'type', 'help_text', 'helpText', 'placeholder', 'section_id', 'formula', 'calculated'] as $key) {
                if (array_key_exists($key, $field) && $field[$key] !== null && (! is_string($field[$key]) || strlen($field[$key]) > 65536 || str_contains($field[$key], "\0"))) {
                    throw new InvalidArgumentException('Field '.$key.' must be bounded text or null.');
                }
            }
            self::assertSort($field);
            if (isset($field['width']) && $field['width'] !== '' && (! is_string($field['width']) || FlexFieldWidth::tryFrom($field['width']) === null)) {
                throw new InvalidArgumentException('Field width must be a supported field width.');
            }
            if (array_key_exists('config', $field) && (! is_array($field['config']) || ($field['config'] !== [] && array_is_list($field['config'])))) {
                throw new InvalidArgumentException('Field configuration must be a map.');
            }
            if (array_key_exists('validation', $field)) {
                $rules = $field['validation'];
                if (! is_array($rules) || ! array_is_list($rules) || count($rules) > 128) {
                    throw new InvalidArgumentException('Field validation requires a bounded rule list.');
                }
                foreach ($rules as $rule) {
                    if (! is_string($rule) || trim($rule) === '' || strlen($rule) > 4096 || str_contains($rule, "\0")) {
                        throw new InvalidArgumentException('Imported validation rules must be bounded strings.');
                    }
                }
            }
        }
        foreach (array_keys($paths) as $path) {
            while (str_contains($path, '.')) {
                $path = substr($path, 0, (int) strrpos($path, '.'));
                if (isset($paths[$path])) {
                    throw new InvalidArgumentException('Field storage paths overlap.');
                }
            }
        }
        foreach ($schema['sections'] ?? [] as $section) {
            if (! is_array($section) || array_diff(array_keys($section), ['id', 'label', 'type', 'sort', 'description', 'visible_when', 'visibleWhen', 'is_active', 'isActive']) !== []) {
                throw new InvalidArgumentException('Section contains invalid or undeclared metadata.');
            }
            foreach (['id', 'label', 'description'] as $key) {
                if (array_key_exists($key, $section) && $section[$key] !== null && (! is_string($section[$key]) || strlen($section[$key]) > 65536 || str_contains($section[$key], "\0"))) {
                    throw new InvalidArgumentException('Section text metadata must be text or null.');
                }
            }
            self::assertSort($section);
            if (array_key_exists('type', $section) && (! is_string($section['type']) || FlexFieldSectionType::tryFrom($section['type']) === null)) {
                throw new InvalidArgumentException('Section type is unsupported.');
            }
            foreach (['is_active', 'isActive'] as $key) {
                if (array_key_exists($key, $section) && ! is_bool($section[$key])) {
                    throw new InvalidArgumentException('Section activation flags must be booleans.');
                }
            }
        }
    }

    /** @param array<string, mixed> $metadata */
    private static function assertSort(array $metadata): void
    {
        if (array_key_exists('sort', $metadata) && ! is_int($metadata['sort'])) {
            throw new InvalidArgumentException('Schema ordering must be an integer.');
        }
    }
}
