<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Support\Intelligence\FormulaEngine;
use Bjanczak\FilamentFlexFields\Support\Schema\Conditions\ConditionRuleParser;
use Bjanczak\FilamentFlexFields\Support\Schema\Configuration\BuiltinConfigurationContract;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class SchemaImportExport
{
    public const EXPORT_VERSION = 1;

    /**
     * @param  array<string, mixed>  $schema
     */
    public function export(array $schema): string
    {
        $this->assertValid($schema);
        $payload = [
            'version' => self::EXPORT_VERSION,
            'schema' => $schema,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        SchemaPayloadContract::budget()->assertEncoded($json);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $json): array
    {
        try {
            SchemaPayloadContract::budget()->assertEncoded($json);
            $decoded = json_decode($json, true, 40, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new JsonException('Invalid flex field schema JSON: '.$exception->getMessage(), $exception->getCode(), $exception);
        }

        if (! is_array($decoded) || array_diff(array_keys($decoded), ['version', 'schema']) !== []) {
            throw new JsonException('Flex field schema export must decode to an object.');
        }

        $version = $decoded['version'] ?? null;

        if ($version !== self::EXPORT_VERSION) {
            throw new JsonException(sprintf(
                'Unsupported flex field schema export version (expected %d).',
                self::EXPORT_VERSION,
            ));
        }

        $schema = $decoded['schema'] ?? null;

        if (! is_array($schema)) {
            throw new JsonException('Flex field schema export is missing a schema object.');
        }

        try {
            $this->assertValid($schema);
        } catch (Throwable $exception) {
            throw new JsonException('Invalid flex field schema: '.$exception->getMessage(), 0, $exception);
        }

        return $schema;
    }

    /** @param array<string, mixed> $schema */
    public function assertValid(array $schema): void
    {
        $result = $this->dryRunValidate($schema);
        if (! $result['ok']) {
            throw new InvalidArgumentException(implode("\n", $result['errors']));
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{ok: bool, errors: list<string>}
     */
    public function dryRunValidate(array $schema): array
    {
        $errors = [];
        try {
            SchemaPayloadContract::assert($schema);
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => [$exception->getMessage()]];
        }

        if (! isset($schema['target']) || ! is_string($schema['target']) || trim($schema['target']) === '') {
            $errors[] = 'Schema target must be a non-empty string (Eloquent model class).';
        }

        if (! isset($schema['fields']) || ! is_array($schema['fields'])) {
            $errors[] = 'Schema fields must be an array.';
        } else {
            $sectionIds = $this->collectSectionIds(is_array($schema['sections'] ?? null) ? $schema['sections'] : []);
            $seenSlugs = [];
            $formulas = [];

            foreach ($schema['fields'] as $index => $field) {
                if (! is_array($field)) {
                    $errors[] = "Field at index {$index} must be an object.";

                    continue;
                }

                $slug = $field['slug'] ?? null;

                if (! is_string($slug) || trim($slug) === '') {
                    $errors[] = "Field at index {$index} is missing a slug.";
                } elseif (isset($seenSlugs[$slug])) {
                    $errors[] = "Duplicate field slug \"{$slug}\".";
                } else {
                    $seenSlugs[$slug] = true;
                }

                $label = $field['label'] ?? null;

                if (! is_string($label) || trim($label) === '') {
                    $errors[] = "Field at index {$index} is missing a label.";
                }

                $type = $field['type'] ?? null;

                if (! is_string($type) || trim($type) === '') {
                    $errors[] = "Field at index {$index} is missing a type.";

                    continue;
                }

                $fieldType = FieldType::tryFrom($type);
                if ($fieldType === null) {
                    $errors[] = "Field at index {$index} uses unknown type \"{$type}\".";
                } else {
                    try {
                        BuiltinConfigurationContract::assert($fieldType, $field['config'] ?? []);
                    } catch (Throwable $exception) {
                        $errors[] = "Field at index {$index} has invalid configuration: {$exception->getMessage()}";
                    }
                }

                foreach (['visible_when' => 'visibleWhen', 'required_when' => 'requiredWhen', 'disabled_when' => 'disabledWhen'] as $conditionKey => $alias) {
                    try {
                        ConditionRuleParser::parse($field[$conditionKey] ?? $field[$alias] ?? null);
                    } catch (Throwable $exception) {
                        $errors[] = "Field at index {$index} has invalid {$conditionKey}: {$exception->getMessage()}";
                    }
                }

                $sectionId = $field['section_id'] ?? null;

                if (is_string($sectionId) && filled($sectionId) && ! isset($sectionIds[$sectionId])) {
                    $errors[] = "Field at index {$index} references unknown section \"{$sectionId}\".";
                }

                if (is_string($slug) && filled($slug)) {
                    $formula = FieldFormula::resolve($field);

                    if (is_string($formula) && trim($formula) !== '') {
                        $formulaErrors = FormulaEngine::validate($formula);

                        if ($formulaErrors === []) {
                            $formulas[$slug] = trim($formula);
                        }

                        foreach ($formulaErrors as $formulaError) {
                            $errors[] = "Field at index {$index} has invalid formula: {$formulaError}";
                        }
                    }
                }
            }

            if ($formulas !== [] && FormulaEngine::detectCycle($formulas) !== []) {
                $errors[] = 'Field formulas contain a circular dependency.';
            }
        }

        if (isset($schema['sections']) && ! is_array($schema['sections'])) {
            $errors[] = 'Schema sections must be an array when provided.';
        } elseif (is_array($schema['sections'] ?? null)) {
            $seenSectionIds = [];

            foreach ($schema['sections'] as $index => $section) {
                if (! is_array($section)) {
                    $errors[] = "Section at index {$index} must be an object.";

                    continue;
                }

                $sectionId = $section['id'] ?? null;

                if (! is_string($sectionId) || trim($sectionId) === '') {
                    $errors[] = "Section at index {$index} is missing an id.";

                    continue;
                }

                if (isset($seenSectionIds[$sectionId])) {
                    $errors[] = "Duplicate section id \"{$sectionId}\".";

                    continue;
                }

                $seenSectionIds[$sectionId] = true;

                try {
                    ConditionRuleParser::parse($section['visible_when'] ?? $section['visibleWhen'] ?? null);
                } catch (Throwable $exception) {
                    $errors[] = "Section at index {$index} has invalid visible_when: {$exception->getMessage()}";
                }
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, mixed>  $sections
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
     * @param  array<string, mixed>  $schema
     */
    public function checksum(array $schema): string
    {
        SchemaPayloadContract::budget()->assert($schema);
        $normalized = json_encode($this->canonicalize($schema), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        SchemaPayloadContract::budget()->assertEncoded($normalized);

        return hash('sha256', $normalized);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value, int $depth = 0): array
    {
        if ($depth > 64) {
            throw new InvalidArgumentException('Schema exceeds the canonicalization depth limit.');
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $entry) {
            if (is_array($entry)) {
                $value[$key] = $this->canonicalize($entry, $depth + 1);
            }
        }

        return $value;
    }
}
