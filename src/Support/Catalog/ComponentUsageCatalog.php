<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;

/** Read the installed registry; never construct a field, model or optional integration. */
final class ComponentUsageCatalog
{
    /** @return list<array{id: string, kind: string, label: string}> */
    public function listing(ComponentRegistry $registry, ?ComponentKind $kind = null): array
    {
        $result = [];
        foreach ($registry->all() as $definition) {
            if ($kind !== null && $definition->kind !== $kind) {
                continue;
            }
            $result[] = ['id' => $definition->id, 'kind' => $definition->kind->value,
                'label' => $definition->catalog === null ? $definition->id : CatalogValue::localized($definition->catalog->label)];
        }
        usort($result, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        return $result;
    }

    /** @return array<string, mixed> */
    public function describe(ComponentRegistry $registry, string $id): array
    {
        $definition = $registry->definition($id);
        $catalog = $definition->catalog;
        $renderer = $definition->surface->class ?? $definition->childContract->renderer ?? $definition->guidance?->renderer;

        return [
            'id' => $id,
            'providerPackage' => $registry->packageAssets()->bindings[$id]->manifest->package
                ?? $definition->surface?->family->package->assets->manifest->package,
            'kind' => $definition->kind->value,
            'label' => $catalog === null ? $id : CatalogValue::localized($catalog->label),
            'description' => $catalog === null ? null : CatalogValue::localized($catalog->description),
            'renderer' => $renderer,
            'rendererSource' => match (true) {
                $definition->surface !== null => 'declared-surface',
                $definition->childContract !== null => 'declared-child-contract',
                $renderer !== null => 'declared-guidance',
                default => 'not-declared',
            },
            'documentation' => $definition->guidance->documentation ?? ($catalog?->official === true && $catalog->documentation !== null
                ? 'https://flex-fields.bjanczak.com/docs/'.pathinfo($catalog->documentation, PATHINFO_FILENAME) : null),
            'capabilities' => array_keys($definition->capabilities),
            'uses' => $definition->uses,
            'stylesFrom' => $definition->stylesFrom,
            'configurationOptions' => array_keys($definition->childContract?->configuration->options ?? []),
            'examples' => array_map(static fn (CodeExample $example, int $index): array => [
                'id' => $index + 1,
                'label' => $example->label ?? basename($example->path),
            ], $this->examples($registry, $id), array_keys($this->examples($registry, $id))),
            'guidance' => $definition->guidance?->export(),
            'scope' => 'Installed declarations and application examples, not the complete fluent PHP API. Unknown renderer metadata is not inferred. Values, records, source paths and browser state are not inspected.',
        ];
    }

    /** @return list<CodeExample> */
    public function examples(ComponentRegistry $registry, string $id): array
    {
        $definition = $registry->definition($id);

        return $definition->guidance !== null && $definition->guidance->examples !== []
            ? $definition->guidance->examples : ($definition->catalog->examples ?? []);
    }
}
