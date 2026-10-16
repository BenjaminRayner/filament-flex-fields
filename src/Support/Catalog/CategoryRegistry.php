<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use LogicException;

final class CategoryRegistry
{
    /** @var array<string, CategoryDefinition> */
    private array $categories = [];

    public function __construct()
    {
        foreach (glob(dirname(__DIR__, 3).'/resources/catalog/categories/*.php') ?: [] as $path) {
            $definition = require $path;
            if (! $definition instanceof CategoryDefinition || basename($path, '.php') !== $definition->id) {
                throw new LogicException('Invalid category schema: '.$path);
            }
            if (! CatalogCategory::tryFrom($definition->id) || isset($this->categories[$definition->id])) {
                throw new LogicException('Invalid official category identity.');
            }
            $this->categories[$definition->id] = $definition;
        }
        if (count($this->categories) !== count(CatalogCategory::cases())) {
            throw new LogicException('Incomplete official FLEX taxonomy.');
        }
        uasort($this->categories, static fn ($left, $right): int => [$left->sort, $left->id] <=> [$right->sort, $right->id]);
    }

    public function has(string $id): bool
    {
        return isset($this->categories[$id]);
    }

    /** @return array<string, CategoryDefinition> */
    public function all(): array
    {
        return $this->categories;
    }
}
