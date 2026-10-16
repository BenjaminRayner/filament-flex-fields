<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalSharedCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;

class IconSvgCache
{
    /**
     * @param  list<string>  $iconNames
     * @param  callable(list<string>): array<string, string>  $renderMissing
     * @return array<string, string>
     */
    public function rememberMany(array $iconNames, callable $renderMissing): array
    {
        $iconNames = array_values(array_unique(array_filter(
            array_map(static fn (mixed $icon): string => is_string($icon) ? trim($icon) : '', $iconNames),
            static fn (string $icon): bool => $icon !== '',
        )));

        if ($iconNames === []) {
            return [];
        }

        $resolved = [];
        $missing = [];
        $keyMap = [];

        foreach ($iconNames as $icon) {
            $keyMap[$this->cacheKey($icon)] = $icon;
        }

        $cache = RequestState::resolve(OptionalSharedCache::class);
        $cachedByKey = [];
        foreach (array_chunk(array_keys($keyMap), 128) as $keys) {
            $cachedByKey += $cache->readMany($keys, static fn (mixed $value): bool => is_string($value) && $value !== '' && strlen($value) <= 262144);
        }

        foreach ($keyMap as $cacheKey => $icon) {
            $cached = $cachedByKey[$cacheKey] ?? null;

            if (is_string($cached) && $cached !== '') {
                $resolved[$icon] = $cached;

                continue;
            }

            $missing[] = $icon;
        }

        if ($missing !== []) {
            $rendered = $renderMissing($missing);
            $ttl = $this->ttl();

            $writes = [];
            $requested = array_fill_keys($missing, true);
            foreach ($rendered as $icon => $html) {
                if (! isset($requested[$icon]) || ! is_string($html) || $html === '') {
                    continue;
                }
                $resolved[$icon] = $html;
                if (strlen($html) <= 262144) {
                    $writes[$this->cacheKey($icon)] = $html;
                }
            }
            if ($writes !== []) {
                foreach (array_chunk($writes, 128, true) as $batch) {
                    $cache->writeMany($batch, $ttl);
                }
            }
        }

        return $resolved;
    }

    protected function cacheKey(string $icon): string
    {
        return 'fff.icon-svg.'.IconCatalogFingerprint::namespace().'.'.hash('sha256', trim($icon));
    }

    protected function ttl(): int
    {
        $days = (int) config('filament-flex-fields.ui.icon_picker_svg_cache_days', 30);

        return min(90, max(1, $days)) * 86400;
    }
}
