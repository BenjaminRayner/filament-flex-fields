<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Data\FlexFieldSchema;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaPayloadContract;
use InvalidArgumentException;

class FlexFieldSchemaMigrator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function migrate(array $schema): array
    {
        SchemaPayloadContract::budget()->assert($schema);
        $version = array_key_exists('version', $schema) ? $schema['version'] : 0;
        if (! is_int($version) || $version < 0 || $version > FlexFieldSchema::CURRENT_VERSION) {
            throw new InvalidArgumentException('Unsupported flex field schema version.');
        }

        if ($version === 0) {
            $schema = $this->migrateFromVersion0($schema);
            $schema['version'] = FlexFieldSchema::CURRENT_VERSION;
        }

        SchemaPayloadContract::assert($schema);

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function migrateFromVersion0(array $schema): array
    {
        if (isset($schema['field']) && ! isset($schema['fields'])) {
            $schema['fields'] = $schema['field'];
            unset($schema['field']);
        }
        if (array_key_exists('field', $schema)) {
            throw new InvalidArgumentException('Ambiguous legacy schema field/fields keys.');
        }

        if (! array_key_exists('fields', $schema)) {
            $schema['fields'] = [];
        }
        if (! is_array($schema['fields']) || ! array_is_list($schema['fields']) || count($schema['fields']) > SchemaPayloadContract::MAX_FIELDS) {
            throw new InvalidArgumentException('Legacy schema fields must be a bounded list.');
        }

        $schema['fields'] = array_map(
            fn (mixed $field): array => is_array($field) ? $this->migrateFieldFromVersion0($field)
                : throw new InvalidArgumentException('Legacy field records must be objects.'),
            $schema['fields'],
        );

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function migrateFieldFromVersion0(array $field): array
    {
        if (array_key_exists('name', $field) && ! array_key_exists('slug', $field)) {
            $field['slug'] = $field['name'];
        }

        if (array_key_exists('default', $field) && ! array_key_exists('default_value', $field) && ! array_key_exists('defaultValue', $field)) {
            $field['default_value'] = $field['default'];
        }

        if (array_key_exists('required', $field) && ! array_key_exists('is_required', $field) && ! array_key_exists('isRequired', $field)) {
            $field['is_required'] = $field['required'];
        }
        unset($field['name'], $field['default'], $field['required']);

        return $field;
    }
}
