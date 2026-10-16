<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\IconPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogFingerprint;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndex;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndexBuilder;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/** @mixin IconPickerField */
trait ResolvesIconPickerCatalog
{
    /**
     * @return list<string>|null
     */
    public function getConfiguredSets(): ?array
    {
        $sets = $this->evaluate($this->sets);

        if ($sets === null) {
            $defaultSets = config('filament-flex-fields.ui.icon_picker_sets');

            if (is_array($defaultSets) && $defaultSets !== []) {
                return $this->normalizeIconPolicyNames($defaultSets, 'sets');
            }

            return null;
        }

        if (is_string($sets)) {
            return $this->normalizeIconPolicyNames([$sets], 'sets');
        }

        if (! is_array($sets)) {
            throw new InvalidArgumentException('Icon sets must resolve to a string, array or null.');
        }

        return $this->normalizeIconPolicyNames($sets, 'sets');
    }

    /**
     * @return list<string>
     */
    public function getResolvedSetNames(): array
    {
        return $this->getCatalogResolver()->resolveSetNames($this->getConfiguredSets());
    }

    /**
     * @return list<string>
     */
    public function getWhitelistedIcons(): array
    {
        $icons = $this->evaluate($this->icons);

        if ($icons === null) {
            return [];
        }
        if (! is_array($icons)) {
            throw new InvalidArgumentException('Icon whitelist must resolve to an array or null.');
        }

        return $this->normalizeIconPolicyNames($icons, 'whitelist');
    }

    /**
     * @return list<string>
     */
    public function getExcludedIcons(): array
    {
        $icons = $this->evaluate($this->excludeIcons);

        if (! is_array($icons)) {
            throw new InvalidArgumentException('Icon exclusions must resolve to an array.');
        }

        return $this->normalizeIconPolicyNames($icons, 'exclusions');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function normalizeIconPolicyNames(array $values, string $policy): array
    {
        if (count($values) > 100000) {
            throw new InvalidArgumentException("Icon {$policy} exceeds its entry budget.");
        }
        $result = [];
        $seen = [];
        $bytes = 0;
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("Icon {$policy} accepts only string identities.");
            }
            $name = trim($value);
            $bytes += strlen($name);
            if (! mb_check_encoding($name, 'UTF-8') || strlen($name) > 4096 || $bytes > 8 * 1024 * 1024) {
                throw new InvalidArgumentException("Icon {$policy} exceeds its byte budget.");
            }
            if ($name !== '' && ! isset($seen[$name])) {
                $seen[$name] = true;
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{prefix: string, label: string, icons: list<string>}>
     */
    public function getIconCatalog(): array
    {
        $sets = $this->getResolvedSetNames();
        $signature = IconCatalogFingerprint::of($sets);
        if ($this->memoizedCatalogSignature !== $signature || $this->memoizedCatalog === null) {
            $this->memoizedCatalog = $this->getCatalogResolver()->catalogFor($sets);
            $this->memoizedCatalogSignature = $signature;
            $this->memoizedIndex = null;
        }

        return $this->memoizedCatalog;
    }

    protected function getCatalogIndex(): IconCatalogIndex
    {
        $dynamic = $this->sets === null || $this->sets instanceof Closure || $this->icons instanceof Closure
            || $this->excludeIcons instanceof Closure || $this->limitPerSet instanceof Closure;
        if ($this->memoizedIndex !== null && ! $dynamic) {
            return $this->memoizedIndex;
        }
        $catalog = $this->getIconCatalog();
        $whitelist = $this->getWhitelistedIcons();
        $exclude = $this->getExcludedIcons();
        $limit = $this->getLimitPerSet();
        $signature = IconCatalogFingerprint::of([IconCatalogIndexBuilder::CACHE_REVISION, $catalog, $whitelist, $exclude, $limit]);
        if ($this->memoizedIndex === null || $signature !== $this->memoizedIndexSignature) {
            $this->memoizedIndex = $this->getCatalogResolver()->indexFor($catalog, $whitelist, $exclude, $limit);
            $this->memoizedIndexSignature = $signature;
            $this->memoizedSearchFingerprint = null;
        }

        return $this->memoizedIndex;
    }

    /**
     * @return list<array{key: string, prefix: string, label: string, count: int}>
     */
    public function getAvailableSetsForJs(): array
    {
        return $this->getCatalogIndex()->setSummaries();
    }

    public function isAllowedIcon(string $icon): bool
    {
        $icon = trim($icon);

        if ($icon === '') {
            return true;
        }

        return $this->getCatalogIndex()->isAllowed($icon);
    }

    /**
     * @return array{
     *     icons: list<array{name: string, label: string}>,
     *     total: int,
     *     page: int,
     *     perPage: int,
     *     hasMore: bool,
     *     sets: list<array{key: string, prefix: string, label: string, count: int}>
     * }
     */
    public function searchIcons(string $query = '', ?string $set = null, int $page = 1): array
    {
        if (! mb_check_encoding($query, 'UTF-8') || strlen($query) > 4096 || mb_strlen($query) > 256) {
            throw new InvalidArgumentException('Icon search query exceeds its length budget.');
        }
        if ($set !== null && (strlen($set) > 4096 || ! mb_check_encoding($set, 'UTF-8'))) {
            throw new InvalidArgumentException('Icon set identity exceeds its UTF-8 or byte budget.');
        }
        $query = trim($query);
        $index = $this->getCatalogIndex();
        $page = max(1, min($page, 500));
        $perPage = $this->getPerPage();
        $includeSetSummaries = $page === 1 && $query === '' && $set === null;

        $minutes = (int) config('filament-flex-fields.ui.icon_picker_search_cache_minutes', 60);

        $whitelisted = $this->getWhitelistedIcons();
        $excluded = $this->getExcludedIcons();

        if ($minutes <= 0 || ! empty($whitelisted) || ! empty($excluded)) {
            return $index->search(
                query: $query,
                set: $set,
                page: $page,
                perPage: $perPage,
                includeSetSummaries: $includeSetSummaries,
            );
        }

        $cacheKey = sprintf(
            'fff.icon-search.'.IconCatalogFingerprint::namespace().'.%s.%s',
            $this->getSearchCacheFingerprint(),
            md5(json_encode([$query, $set, $page, $perPage, $includeSetSummaries], JSON_THROW_ON_ERROR)),
        );

        return Cache::remember($cacheKey, now()->addMinutes($minutes), fn (): array => $index->search(
            query: $query,
            set: $set,
            page: $page,
            perPage: $perPage,
            includeSetSummaries: $includeSetSummaries,
        ));
    }

    public function getIconPickerConfigurationFingerprint(): string
    {
        return $this->getSearchCacheFingerprint();
    }

    protected function getSearchCacheFingerprint(): string
    {
        if ($this->memoizedIndexSignature === null) {
            $this->getCatalogIndex();
        }

        return $this->memoizedSearchFingerprint ??= (string) $this->memoizedIndexSignature;
    }

    protected function getCatalogResolver(): IconCatalogResolver
    {
        return app(IconCatalogResolver::class);
    }
}
