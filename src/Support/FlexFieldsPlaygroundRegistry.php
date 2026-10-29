<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Enums\PlaygroundCategory;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundComponentPage;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundAccess;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundRegistryState;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Filament\Pages\PageConfiguration;

/**
 * Legacy page API backed by the same component schemas as the FLEX catalog.
 *
 * @phpstan-type PlaygroundDefinition array{component: string, slug: string, label: string, description: string, playground: string, sort: int, icon: string, category: PlaygroundCategory, docs_path: string|null}
 */
class FlexFieldsPlaygroundRegistry
{
    /** @return array<string, PlaygroundDefinition> */
    public static function definitions(): array
    {
        $registry = ComponentAssetCatalog::registry();
        $state = RequestState::resolve(PlaygroundRegistryState::class);
        $key = spl_object_id($registry).':'.$registry->revision().':'.app()->getLocale();
        if ($state->key === $key) {
            return $state->definitions;
        }
        $definitions = [];
        foreach ($registry->all() as $id => $component) {
            $catalog = $component->catalog;
            if ($catalog === null || $catalog->groupedWith !== null || $catalog->demoClass === null || $catalog->demoSlug === null) {
                continue;
            }
            if (isset($definitions[$catalog->demoSlug])) {
                throw new \LogicException('Duplicate FLEX demo slug: '.$catalog->demoSlug);
            }
            $definitions[$catalog->demoSlug] = [
                'component' => $id, 'slug' => $catalog->demoSlug, 'label' => CatalogValue::localized($catalog->label),
                'description' => CatalogValue::localized($catalog->description), 'playground' => $catalog->demoClass,
                'sort' => $catalog->sort, 'icon' => $catalog->icon ?? FlexIcon::LayoutCells,
                'category' => PlaygroundCategory::tryFrom(str_replace('-', '_', $catalog->category->value)) ?? PlaygroundCategory::Guides,
                'docs_path' => $catalog->documentation,
            ];
        }
        $state->key = $key;

        return $state->definitions = $definitions;
    }

    /** @return array<string, PlaygroundDefinition> */
    public static function ordered(): array
    {
        $definitions = static::definitions();
        uasort($definitions, static fn (array $left, array $right): int => [$left['category']->sort(), $left['sort']] <=> [$right['category']->sort(), $right['sort']]);

        return $definitions;
    }

    /** @return array<string, array<string, PlaygroundDefinition>> */
    public static function groupedByCategory(): array
    {
        $groups = array_fill_keys(array_map(static fn ($category): string => $category->value, PlaygroundCategory::cases()), []);
        foreach (static::ordered() as $slug => $definition) {
            $groups[$definition['category']->value][$slug] = $definition;
        }

        return $groups;
    }

    public static function categoryForSlug(string $slug): PlaygroundCategory
    {
        return static::find($slug)['category'] ?? PlaygroundCategory::Pickers;
    }

    public static function docsPathFor(string $slug): ?string
    {
        return static::find($slug)['docs_path'] ?? null;
    }

    /** @return list<string> */
    public static function docsExemptSlugs(): array
    {
        return array_keys(array_filter(static::definitions(), static fn (array $definition): bool => $definition['docs_path'] === null));
    }

    /** @return PlaygroundDefinition|null */
    public static function find(?string $slug): ?array
    {
        return $slug === null ? null : (static::definitions()[$slug] ?? null);
    }

    public static function firstSlug(): ?string
    {
        return array_key_first(static::ordered());
    }

    public static function isEnabled(): bool
    {
        return PlaygroundAccess::isEnabled();
    }

    /** @return list<PageConfiguration> */
    public static function pageConfigurations(): array
    {
        // Registration can run before a panel becomes current; the plugin owns its panel switch.
        return ! FlexFieldsConfig::isPlaygroundEnabled() ? [] : array_map(static fn (string $slug): PageConfiguration => PageConfiguration::make(FlexFieldsPlaygroundComponentPage::class, $slug)->slug($slug), array_keys(static::ordered()));
    }
}
