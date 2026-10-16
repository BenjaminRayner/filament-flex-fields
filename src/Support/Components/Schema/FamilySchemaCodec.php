<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use InvalidArgumentException;

/** Portable opt-in exchange; the built-in FieldType enum/schema format stays compatible. */
final class FamilySchemaCodec
{
    public function __construct(private readonly FamilySchemaRenderer $renderer) {}

    /** @param list<FamilySchemaDefinition> $definitions */
    public function export(array $definitions): string
    {
        $json = json_encode(['format' => 1, 'families' => array_map(static fn (FamilySchemaDefinition $definition): array => $definition->export(), $this->normalize($definitions))], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 1048576) {
            throw new InvalidArgumentException('Family schema exchange exceeds its byte budget.');
        }

        return $json;
    }

    /** @return list<FamilySchemaDefinition> */
    public function import(string $json): array
    {
        if (strlen($json) > 1048576) {
            throw new InvalidArgumentException('Family schema exchange exceeds its byte budget.');
        }
        $data = json_decode($json, true, 24, JSON_THROW_ON_ERROR);
        if (! is_array($data) || array_diff(array_keys($data), ['format', 'families']) !== [] || ($data['format'] ?? null) !== 1
            || ! is_array($data['families'] ?? null) || ! array_is_list($data['families']) || count($data['families']) > 256) {
            throw new InvalidArgumentException('Invalid family schema exchange envelope.');
        }
        $definitions = [];
        foreach ($data['families'] as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Family schema records must be objects.');
            }
            $definitions[] = FamilySchemaDefinition::fromArray($item);
        }

        return $this->normalize($definitions);
    }

    /**
     * @param  list<FamilySchemaDefinition>  $definitions
     * @return list<FamilySchemaDefinition>
     */
    private function normalize(array $definitions): array
    {
        if (! array_is_list($definitions) || count($definitions) > 256) {
            throw new InvalidArgumentException('Family schema exchange needs a bounded list.');
        }
        $seen = [];
        $result = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof FamilySchemaDefinition || isset($seen[$definition->slug])) {
                throw new InvalidArgumentException('Family schema requires typed definitions with unique slugs.');
            }
            $seen[$definition->slug] = true;
            $result[] = $this->renderer->normalize($definition);
        }
        if (strlen(json_encode(array_map(static fn (FamilySchemaDefinition $definition): array => $definition->export(), $result), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE)) > 1048576) {
            throw new InvalidArgumentException('Family schema exchange exceeds its byte budget.');
        }

        return $result;
    }
}
