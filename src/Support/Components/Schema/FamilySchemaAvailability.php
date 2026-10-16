<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use InvalidArgumentException;

/** Value-free preflight: inspect installed metadata without constructing optional adapters. */
final class FamilySchemaAvailability
{
    public function __construct(private readonly ComponentRegistry $registry) {}

    /**
     * @param  list<FamilySchemaDefinition>  $definitions
     * @return array{format: int, ready: bool, issues: list<array{family: string, slug: string, code: string, configurationVersion: int, valueVersion: int, installedConfigurationVersion: int|null, installedValueVersion: int|null}>}
     */
    public function inspect(array $definitions): array
    {
        if (! array_is_list($definitions) || count($definitions) > 256) {
            throw new InvalidArgumentException('Family availability requires a bounded definition list.');
        }
        $families = $this->registry->compiledGraph()->families->families;
        $issues = [];
        $seen = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof FamilySchemaDefinition || isset($seen[$definition->slug])) {
                throw new InvalidArgumentException('Family availability requires typed definitions with unique slugs.');
            }
            $seen[$definition->slug] = true;
            $family = $families[$definition->family] ?? null;
            $code = match (true) {
                $family === null => 'family-unavailable',
                $family->schema === null => 'schema-unavailable',
                $definition->configurationVersion > $family->configurationVersion => 'configuration-newer-than-installed',
                $definition->configurationVersion < $family->configurationVersion => 'configuration-migration-required',
                $definition->valueVersion !== $family->valueVersion => 'value-migration-required',
                default => null,
            };
            if ($code === null && $family?->schema !== null) {
                try {
                    $family->schema->configuration->resolve([$family->schema->packageDefaults, $definition->config]);
                } catch (InvalidArgumentException) {
                    $code = 'configuration-invalid';
                }
            }
            if ($code !== null) {
                $issues[] = ['family' => $definition->family, 'slug' => $definition->slug, 'code' => $code,
                    'configurationVersion' => $definition->configurationVersion, 'valueVersion' => $definition->valueVersion,
                    'installedConfigurationVersion' => $family?->configurationVersion, 'installedValueVersion' => $family?->valueVersion];
            }
        }

        return ['format' => 1, 'ready' => $issues === [], 'issues' => $issues];
    }
}
