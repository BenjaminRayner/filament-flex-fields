<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionFeed;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Composer\InstalledVersions;
use InvalidArgumentException;
use LogicException;

/**
 * App-owned discovery registry. No marketplace network calls or automatic installs.
 *
 * @phpstan-type CatalogEntry array{listed: bool, id: string, label: string, description: string, categories: list<string>, kinds: list<string>, keywords: list<string>, icon: string|null, image: string|null, heroImage: string|null, categoryLabel: string, website: string|null, privacyPolicy: string|null, termsOfService: string|null, version: string|null, author: string, official: bool, type: string, installed: bool, popularRank: int|null, newAndNoteworthy: bool, sort: int, repository: string|null, composerPackage: string|null, demoSlug: string|null, demoClass: string|null}
 */
final class FlexCatalog
{
    /** @var array<string, RecommendedPlugin> */
    private array $plugins = [];

    /** @var array<string, bool> */
    private array $installedPackages = [];

    private ?string $entryCacheKey = null;

    /** @var array<string, CatalogEntry> */
    private array $entryCache = [];

    private int $pluginRevision = 0;

    private bool $frozen = false;

    public function __construct(private readonly ComponentRegistry $components, private readonly CategoryRegistry $categories) {}

    public function registerPlugin(RecommendedPlugin $plugin): void
    {
        if ($this->frozen) {
            throw new LogicException('Register FLEX catalog plugins during provider boot, before collection freezes.');
        }
        if (count($this->plugins) >= 512) {
            throw new LogicException('FLEX catalog plugin registration budget exhausted.');
        }
        if (isset($this->plugins[$plugin->id]) || $this->components->has($plugin->id)) {
            throw new LogicException('Duplicate FLEX catalog identity: '.$plugin->id);
        }
        $this->validateCategories([$plugin->category->value]);
        $this->plugins[$plugin->id] = $plugin;
        $this->pluginRevision++;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    /** @return list<array{id: string, label: string, icon: string}> */
    public function categories(): array
    {
        return array_values(array_map(static fn (CategoryDefinition $category): array => ['id' => $category->id, 'label' => CatalogValue::localized($category->label), 'icon' => $category->icon], $this->categories->all()));
    }

    /** @return array<string, CatalogEntry> */
    public function entries(): array
    {
        // A single locale/revision snapshot keeps repeated lookups O(1), including worker lifetimes.
        // Provider registration invalidates it automatically; no filesystem scan or remote lookup.
        $locale = function_exists('app') && method_exists(app(), 'getLocale') ? app()->getLocale() : 'en';
        $key = $this->components->revision().':'.$this->pluginRevision.':'.$locale;
        if ($this->entryCacheKey === $key) {
            return $this->entryCache;
        }
        $entries = [];
        foreach ($this->components->all() as $id => $component) {
            $presentation = $component->catalog;
            if ($presentation === null) {
                continue;
            }
            $this->validateCategories([$presentation->category->value]);
            $entries[$id] = $this->present($id, $presentation, 'component', true, [$component->kind->value]);
        }
        foreach ($this->plugins as $plugin) {
            $installed = $plugin->composerPackage !== null && ($this->installedPackages[$plugin->composerPackage] ??= InstalledVersions::isInstalled($plugin->composerPackage));
            $kinds = array_map(static fn (ComponentKind $kind): string => $kind->value, $plugin->kinds);
            $entries[$plugin->id] = [...$this->present($plugin->id, $plugin, 'plugin', $installed, $kinds), 'repository' => $plugin->repository, 'composerPackage' => $plugin->composerPackage,
                'version' => $installed ? (InstalledVersions::getPrettyVersion($plugin->composerPackage) ?? $plugin->version) : $plugin->version];
        }
        foreach ($this->components->all() as $id => $component) {
            $parent = $component->catalog?->groupedWith;
            if ($parent === null) {
                continue;
            }
            if ($component->kind !== ComponentKind::Column) {
                throw new LogicException('Grouped playground variants must be table columns: '.$id);
            }
            if ($parent === $id || ! $this->components->has($parent) || ! isset($entries[$parent]) || $this->components->definition($parent)->catalog?->groupedWith !== null || $entries[$parent]['demoClass'] === null) {
                throw new LogicException('Grouped catalog surfaces require an ungrouped component demo: '.$id);
            }
            $entries[$parent]['kinds'] = array_values(array_unique([...$entries[$parent]['kinds'], $component->kind->value]));
            $entries[$parent]['keywords'] = array_values(array_unique([...$entries[$parent]['keywords'], ...$entries[$id]['keywords']]));
        }
        uasort($entries, static fn (array $left, array $right): int => [$left['sort'], $left['label'], $left['id']] <=> [$right['sort'], $right['label'], $right['id']]);
        $this->entryCacheKey = $key;

        return $this->entryCache = $entries;
    }

    /** @return CatalogEntry|null */
    public function find(string $id): ?array
    {
        return $this->entries()[$id] ?? null;
    }

    /** @return array<string, CatalogEntry> */
    public function groupedEntriesFor(string $id): array
    {
        return array_filter($this->entries(), fn (array $entry): bool => $this->components->has($entry['id']) && $this->components->definition($entry['id'])->catalog?->groupedWith === $id);
    }

    /**
     * @param  list<string>  $kinds
     * @return CatalogEntry
     */
    private function present(string $id, ComponentPresentation|RecommendedPlugin $source, string $type, bool $installed, array $kinds): array
    {
        return [
            'listed' => ! $source instanceof ComponentPresentation || ($source->listed && $source->groupedWith === null),
            'id' => $id, 'label' => CatalogValue::localized($source->label), 'description' => CatalogValue::localized($source->description),
            'categories' => [$source->category->value], 'kinds' => $kinds, 'keywords' => $source->keywords, 'icon' => $source->icon, 'image' => $source->image, 'heroImage' => $source->heroImage,
            'categoryLabel' => CatalogValue::localized($this->categories->all()[$source->category->value]->label),
            'website' => $source->website ?? ($source instanceof ComponentPresentation && $source->official ? PromotionFeed::WEBSITE : null),
            'privacyPolicy' => $source->privacyPolicy, 'termsOfService' => $source->termsOfService, 'version' => null,
            'author' => $source->author, 'official' => $source instanceof ComponentPresentation && $source->official,
            'type' => $type, 'installed' => $installed, 'popularRank' => $source->popularRank, 'newAndNoteworthy' => $source->newAndNoteworthy,
            'sort' => $source->sort, 'repository' => null, 'composerPackage' => null,
            'demoSlug' => $source instanceof ComponentPresentation ? $source->demoSlug : null,
            'demoClass' => $source instanceof ComponentPresentation ? $source->demoClass : null,
        ];
    }

    /** @param list<string> $categories */
    private function validateCategories(array $categories): void
    {
        foreach ($categories as $category) {
            if (! $this->categories->has($category)) {
                throw new InvalidArgumentException('Unknown FLEX category: '.$category);
            }
        }
    }
}
