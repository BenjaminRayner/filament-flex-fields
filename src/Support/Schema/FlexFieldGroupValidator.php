<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class FlexFieldGroupValidator
{
    public function __construct(
        private readonly SchemaImportExport $importExport = new SchemaImportExport,
    ) {}

    /**
     * @return array{ok: bool, errors: list<string>}
     */
    public function validateGroup(FlexFieldGroup $group): array
    {
        $errors = [];

        $schema = $group->toRegistrySchema();
        $dryRun = $this->importExport->dryRunValidate($schema);
        if (! $dryRun['ok']) {
            return $dryRun;
        }
        $errors = [...$errors, ...$dryRun['errors']];

        $fields = $group->fields ?? [];

        if (! is_array($fields)) {
            $errors[] = 'Fields must be an array.';

            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        $slugs = [];
        $sectionIds = $this->collectSectionIds(is_array($group->sections) ? array_values($group->sections) : []);
        $formulas = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                $errors[] = "Field at index {$index} must be an object.";

                continue;
            }

            $slug = $field['slug'] ?? null;

            if (is_string($slug) && filled($slug)) {
                if (isset($slugs[$slug])) {
                    $errors[] = "Duplicate field slug \"{$slug}\" within this group.";
                }

                $slugs[$slug] = true;
            }

            foreach (['config', 'visible_when', 'required_when', 'disabled_when'] as $jsonKey) {
                if (! array_key_exists($jsonKey, $field)) {
                    continue;
                }

                $value = $field[$jsonKey];

                if ($value === null || $value === '' || is_array($value)) {
                    continue;
                }

                if (! is_string($value)) {
                    $errors[] = "Field \"{$slug}\" has invalid {$jsonKey} (must be JSON object or array).";

                    continue;
                }

                try {
                    json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $errors[] = "Field \"{$slug}\" has invalid JSON in {$jsonKey}.";
                }
            }

            try {
                $definition = FlexFieldDefinition::fromArray($this->normalizeFieldAttributes($field));
                foreach ([$definition->visibleWhen, $definition->requiredWhen, $definition->disabledWhen] as $condition) {
                    if ($condition !== null) {
                        JsonFieldConditions::assertValid($condition);
                    }
                }
            } catch (Throwable $exception) {
                $label = is_string($slug) && filled($slug) ? $slug : (string) $index;
                $errors[] = "Field \"{$label}\" could not be parsed: {$exception->getMessage()}";
            }

            $sectionId = $field['section_id'] ?? null;

            if (is_string($sectionId) && filled($sectionId) && ! isset($sectionIds[$sectionId])) {
                $label = is_string($slug) && filled($slug) ? $slug : (string) $index;
                $errors[] = "Field \"{$label}\" references unknown section \"{$sectionId}\".";
            }

            if (is_string($slug) && filled($slug)) {
                $formula = FieldFormula::resolve($field);

                if (is_string($formula) && trim($formula) !== '') {
                    $formulas[$slug] = trim($formula);
                }
            }

            $type = FieldType::tryFrom((string) ($field['type'] ?? ''));

            if ($type?->requiresConfiguredOptions()) {
                $normalized = $this->normalizeFieldAttributes($field);
                $config = is_array($normalized['config'] ?? null) ? $normalized['config'] : [];
                $options = $config['options'] ?? [];

                if (! is_array($options) || $options === []) {
                    $label = is_string($slug) && filled($slug) ? $slug : (string) $index;
                    $errors[] = "Field \"{$label}\" requires at least one option.";
                }
            }
        }

        if ($formulas !== [] && FormulaEngine::detectCycle($formulas) !== []) {
            $errors[] = 'Field formulas contain a circular dependency.';
        }

        foreach (is_array($group->sections) ? $group->sections : [] as $index => $section) {
            if (! is_array($section)) {
                $errors[] = "Section at index {$index} must be an object.";

                continue;
            }

            $sectionId = $section['id'] ?? null;

            if (! is_string($sectionId) || ! filled($sectionId)) {
                $errors[] = "Section at index {$index} is missing an id.";
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
        ];
    }

    public function assertValidGroup(FlexFieldGroup $group): void
    {
        $result = $this->validateGroup($group);

        if ($result['ok']) {
            return;
        }

        throw ValidationException::withMessages([
            'fields' => implode("\n", $result['errors']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public function normalizeFieldAttributes(array $field): array
    {
        $this->assertFieldList([$field]);
        $field = $this->decodeJsonAttributes($field);
        foreach (['type_settings', 'field_options', 'field_matrix_rows', 'field_matrix_columns'] as $key) {
            if (array_key_exists($key, $field) && $field[$key] !== null && ! is_array($field[$key])) {
                throw new InvalidArgumentException('Field '.$key.' must be an array.');
            }
        }
        $field = FlexFieldTypeSettingsStorage::mergeIntoField($field);
        $field = FlexFieldMatrixStorage::mergeIntoField($field);
        $field = $this->mergeFieldOptionsIntoConfig($field);

        unset($field['field_options'], $field['field_matrix_rows'], $field['field_matrix_columns'], $field['type_settings']);

        return $field;
    }

    /**
     * @param  list<array<string, mixed>>|null  $fields
     * @return list<array<string, mixed>>
     */
    public function prepareFieldsForForm(?array $fields): array
    {
        if ($fields === null) {
            return [];
        }
        $this->assertFieldList($fields);

        return array_values(array_map(
            fn (array $field): array => $this->prepareFieldForForm($field),
            $fields,
        ));
    }

    /**
     * @param  list<array<string, mixed>>|null  $fields
     * @return list<array<string, mixed>>
     */
    public function normalizeFields(?array $fields): array
    {
        if ($fields === null) {
            return [];
        }
        $this->assertFieldList($fields);

        return array_values(array_map(
            fn (array $field): array => $this->normalizeFieldAttributes($field),
            $fields,
        ));
    }

    /** @param array<array-key, mixed> $fields */
    private function assertFieldList(array $fields): void
    {
        SchemaPayloadContract::budget()->assert($fields);
        if (! array_is_list($fields) || count($fields) > SchemaPayloadContract::MAX_FIELDS) {
            throw new InvalidArgumentException('Field groups require a bounded field list.');
        }
        foreach ($fields as $field) {
            if (! is_array($field) || (array_key_exists('type', $field) && ! is_string($field['type']))) {
                throw new InvalidArgumentException('Field group records must be objects with a string type.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function prepareFieldForForm(array $field): array
    {
        $field = $this->decodeJsonAttributes($field);
        $type = FieldType::tryFrom((string) ($field['type'] ?? ''));
        $config = is_array($field['config'] ?? null) ? $field['config'] : [];

        if ($type?->supportsUserDefinedOptions()) {
            $storageKey = $type->usesSuggestionsInsteadOfOptions() ? 'suggestions' : 'options';
            $field['field_options'] = FlexFieldOptionStorage::configToRepeater($config[$storageKey] ?? [], $type);
        }

        if ($type === FieldType::MatrixChoice) {
            $field['field_matrix_rows'] = FlexFieldMatrixStorage::configToRepeater($config['rows'] ?? []);
            $field['field_matrix_columns'] = FlexFieldMatrixStorage::configToRepeater($config['columns'] ?? []);
        }

        if ($type !== null) {
            $field['type_settings'] = FlexFieldTypeSettingsStorage::extractFromConfig($type, $config);
        }

        unset($field['config']);

        return $field;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function decodeJsonAttributes(array $field): array
    {
        foreach (['config', 'visible_when', 'required_when', 'disabled_when'] as $jsonKey) {
            if (! array_key_exists($jsonKey, $field)) {
                continue;
            }

            $value = $field[$jsonKey];

            if ($value === null || $value === '') {
                unset($field[$jsonKey]);

                continue;
            }

            if (is_string($value)) {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new InvalidArgumentException('Invalid field JSON attribute: '.$jsonKey, 0, $exception);
                }
                if (! is_array($decoded)) {
                    throw new InvalidArgumentException('Field JSON attribute must contain an object or array: '.$jsonKey);
                }
                $field[$jsonKey] = $decoded;
            } elseif (! is_array($value)) {
                throw new InvalidArgumentException('Field JSON attribute must contain an object or array: '.$jsonKey);
            }
        }

        return $field;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, true>
     */
    private function collectSectionIds(array $sections): array
    {
        $ids = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $id = $section['id'] ?? null;

            if (is_string($id) && filled($id)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function mergeFieldOptionsIntoConfig(array $field): array
    {
        if (! array_key_exists('field_options', $field)) {
            return $field;
        }

        $type = FieldType::tryFrom((string) ($field['type'] ?? ''));

        if (! $type?->supportsUserDefinedOptions()) {
            return $field;
        }

        $config = is_array($field['config'] ?? null) ? $field['config'] : [];
        $optionsState = is_array($field['field_options']) ? $field['field_options'] : [];

        if ($type->usesSuggestionsInsteadOfOptions()) {
            $config['suggestions'] = FlexFieldOptionStorage::repeaterToSuggestions($optionsState);
        } else {
            $config['options'] = FlexFieldOptionStorage::repeaterToConfigList($optionsState, $type);
        }

        $field['config'] = $config;

        return $field;
    }
}
