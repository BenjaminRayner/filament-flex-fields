<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

/** @phpstan-import-type LocalizedText from CatalogValue */
final readonly class CategoryDefinition
{
    /**
     * @param  LocalizedText  $label
     */
    public function __construct(public string $id, public string|array $label, public string $icon = 'hugeicons-grid-view', public int $sort = 100)
    {
        CatalogValue::id($id);
        CatalogValue::text($label, 80);
        if (! preg_match('/^hugeicons-[a-z0-9-]+$/D', $icon)) {
            throw new \InvalidArgumentException('Catalog category icons use Hugeicons.');
        }
    }
}
