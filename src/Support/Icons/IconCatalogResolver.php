<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalSharedCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Illuminate\Support\Str;

class IconCatalogResolver
{
    public const int DEFAULT_PER_PAGE = 64;

    public const int MAX_PER_PAGE = 96;

    public function __construct(
        private Factory $factory,
        private IconsManifest $manifest,
    ) {}

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     */
    public function indexFor(
        array $catalog,
        array $whitelist = [],
        array $exclude = [],
        ?int $limitPerSet = null,
    ): IconCatalogIndex {
        $fingerprint = IconCatalogFingerprint::of([
            IconCatalogIndexBuilder::CACHE_REVISION,
            $catalog,
            $whitelist,
            $exclude,
            $limitPerSet,
        ]);

        $cacheKey = 'fff.icon-index.'.IconCatalogFingerprint::namespace().'.'.$fingerprint;
        $ttlDays = (int) config('filament-flex-fields.ui.icon_picker_index_cache_days', 7);

        $data = RequestState::resolve(OptionalSharedCache::class)->remember($cacheKey, min(90, max(1, $ttlDays)) * 86400, function () use ($catalog, $whitelist, $exclude, $limitPerSet): array {
            return $this->buildIndex($catalog, $whitelist, $exclude, $limitPerSet)->toArray();
        }, IconCatalogPayload::index(...));

        return IconCatalogIndex::fromArray($data);
    }

    /**
     * @param  list<string>  $setNames
     * @return array<string, array{prefix: string, label: string, icons: list<string>}>
     */
    public function buildCatalogFromManifest(array $setNames, bool $fresh = false): array
    {
        $setNames = array_values(array_unique($setNames));
        sort($setNames);

        if ($setNames === []) {
            return [];
        }

        $bundledManifest = $fresh ? null : $this->readBundledManifest();

        if ($bundledManifest === null) {
            return $this->buildCatalogFromBladeIcons($setNames);
        }

        $registered = $this->factory->all();
        $catalog = [];
        $missing = [];
        foreach ($setNames as $name) {
            if (isset($bundledManifest[$name], $registered[$name])
                && ($registered[$name]['prefix'] ?? $name) === $bundledManifest[$name]['prefix']) {
                $catalog[$name] = $bundledManifest[$name];
            } else {
                $missing[] = $name;
            }
        }

        // Bundled sets accelerate known catalogs; custom sets still come from Blade Icons.
        $resolved = $catalog + $this->buildCatalogFromBladeIcons($missing);
        $ordered = [];
        foreach ($setNames as $name) {
            if (isset($resolved[$name])) {
                $ordered[$name] = $resolved[$name];
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, array{prefix: string, label: string, icons: list<string>}>|null
     */
    protected function readBundledManifest(): ?array
    {
        if (! config('filament-flex-fields.ui.icon_picker_use_bundled_manifest', true)) {
            return null;
        }

        $path = dirname(__DIR__, 3).'/resources/dist/icon-catalog-manifest.json';

        if (! is_file($path)) {
            return null;
        }

        $payload = file_get_contents($path, false, null, 0, 4_194_305);
        if (! is_string($payload) || strlen($payload) > 4_194_304) {
            return null;
        }
        $decoded = json_decode($payload, true, 8);

        return IconCatalogPayload::catalog($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $setNames
     * @return array<string, array{prefix: string, label: string, icons: list<string>}>
     */
    protected function buildCatalogFromBladeIcons(array $setNames): array
    {
        $sets = $this->factory->all();
        $filteredSets = array_intersect_key($sets, array_flip($setNames));
        $manifest = $this->manifest->getManifest($filteredSets);
        $catalog = [];

        foreach ($setNames as $setName) {
            if (! isset($sets[$setName], $manifest[$setName])) {
                continue;
            }

            $prefix = (string) ($sets[$setName]['prefix'] ?? $setName);
            $icons = [];

            foreach ($manifest[$setName] as $pathIcons) {
                if (! is_array($pathIcons)) {
                    continue;
                }

                foreach ($pathIcons as $icon) {
                    if (! is_string($icon) || $icon === '') {
                        continue;
                    }

                    $icons[] = $this->fullIconName($prefix, $icon);
                }
            }

            $icons = array_values(array_unique($icons));
            sort($icons, SORT_NATURAL | SORT_FLAG_CASE);

            $catalog[$setName] = [
                'prefix' => $prefix,
                'label' => $this->formatSetLabel($setName, $prefix),
                'icons' => $icons,
            ];
        }

        return $catalog;
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     */
    protected function buildIndex(
        array $catalog,
        array $whitelist,
        array $exclude,
        ?int $limitPerSet,
    ): IconCatalogIndex {
        return (new IconCatalogIndexBuilder)->build(
            $catalog, $whitelist, $exclude, $limitPerSet,
            fn (string $icon): string => $this->formatIconLabel($icon),
        );
    }

    /**
     * @param  list<array{name: string, label: string, set: string, nameLower: string, labelLower: string}>  $entries
     * @return array<string, list<array{name: string, label: string, set: string, nameLower: string, labelLower: string}>>
     */
    protected function partitionEntriesBySet(array $entries): array
    {
        $partitioned = [];

        foreach ($entries as $entry) {
            $partitioned[$entry['set']][] = $entry;
        }

        return $partitioned;
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     */
    protected function resolveIconSetKey(array $catalog, string $icon): ?string
    {
        foreach ($catalog as $setKey => $setData) {
            if (str_starts_with($icon, $setData['prefix'].'-') && in_array($icon, $setData['icons'], true)) {
                return $setKey;
            }
        }

        return null;
    }

    /**
     * @param  list<string>|string|null  $requested
     * @return list<string>
     */
    public function resolveSetNames(array|string|null $requested = null): array
    {
        $allSets = $this->factory->all();

        if ($requested === null) {
            return array_keys($allSets);
        }

        if (is_string($requested)) {
            $requested = [$requested];
        }

        $resolved = [];

        foreach ($requested as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            if (isset($allSets[$value])) {
                $resolved[] = $value;

                continue;
            }

            foreach ($allSets as $name => $config) {
                if (($config['prefix'] ?? '') === $value) {
                    $resolved[] = $name;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  list<string>  $setNames
     * @return array<string, array{prefix: string, label: string, icons: list<string>}>
     */
    public function catalogFor(array $setNames): array
    {
        $setNames = array_values(array_unique($setNames));
        sort($setNames);

        if ($setNames === []) {
            return [];
        }

        $source = [
            $setNames,
            array_intersect_key($this->factory->all(), array_flip($setNames)),
            (bool) config('filament-flex-fields.ui.icon_picker_use_bundled_manifest', true),
        ];
        $cacheKey = 'fff.icon-catalog.'.IconCatalogFingerprint::namespace().'.'.IconCatalogFingerprint::of($source);
        $ttlDays = (int) config('filament-flex-fields.ui.icon_picker_catalog_cache_days', 7);

        return RequestState::resolve(OptionalSharedCache::class)->remember($cacheKey, min(90, max(1, $ttlDays)) * 86400, function () use ($setNames): array {
            return $this->buildCatalogFromManifest($setNames);
        }, IconCatalogPayload::catalog(...));
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     * @return array{
     *     icons: list<array{name: string, label: string}>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     hasMore: bool,
     *     sets: list<array{key: string, prefix: string, label: string, count: int}>
     * }
     */
    public function search(
        array $catalog,
        ?string $query = null,
        ?string $set = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        array $whitelist = [],
        array $exclude = [],
        ?int $limitPerSet = null,
        bool $includeSetSummaries = false,
    ): array {
        return $this->indexFor($catalog, $whitelist, $exclude, $limitPerSet)
            ->search($query, $set, $page, $perPage, $includeSetSummaries);
    }

    public function fullIconName(string $prefix, string $icon): string
    {
        return $prefix.'-'.str_replace('.', '-', $icon);
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public function collectIcons(
        array $catalog,
        ?string $set = null,
        array $whitelist = [],
        array $exclude = [],
        ?int $limitPerSet = null,
    ): array {
        $entries = $this->buildIndex($catalog, $whitelist, $exclude, $limitPerSet)->toArray()['entries'];
        $icons = [];
        foreach ($entries as $entry) {
            if ($set !== null && $entry['set'] !== $set && ($catalog[$entry['set']]['prefix'] ?? null) !== $set) {
                continue;
            }
            $icons[] = $entry['name'];
        }
        sort($icons, SORT_NATURAL | SORT_FLAG_CASE);

        return $icons;
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     */
    protected function iconMatchesSet(array $catalog, string $set, string $icon): bool
    {
        if (isset($catalog[$set])) {
            return in_array($icon, $catalog[$set]['icons'], true);
        }

        foreach ($catalog as $setData) {
            if (($setData['prefix'] ?? '') === $set && in_array($icon, $setData['icons'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{prefix: string, label: string, icons: list<string>}>  $catalog
     * @param  list<string>  $whitelist
     * @param  list<string>  $exclude
     * @return list<array{key: string, prefix: string, label: string, count: int}>
     */
    public function summarizeSets(
        array $catalog,
        array $whitelist = [],
        array $exclude = [],
        ?int $limitPerSet = null,
    ): array {
        return $this->indexFor($catalog, $whitelist, $exclude, $limitPerSet)->setSummaries();
    }

    public function formatIconLabel(string $icon): string
    {
        $icon = trim($icon);

        if ($icon === '') {
            return '';
        }

        $name = str($icon)->after('-')->replace(['.', '-'], ' ')->trim();

        if ($name->isEmpty()) {
            return Str::headline($icon);
        }

        return Str::headline((string) $name);
    }

    protected function formatSetLabel(string $setName, string $prefix): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $prefix !== '' ? $prefix : $setName));
    }
}
