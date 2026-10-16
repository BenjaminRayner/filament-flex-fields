<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\PriceRange;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;

final class Histogram
{
    /** @param list<int|float> $source
     * @return array{count: int, bars: list<array<int, float>>}
     */
    public static function presentation(array $source): array
    {
        return RequestState::resolve(ScopedRenderCache::class)->remember(self::class, hash('sha256', serialize($source)),
            fn (): array => self::profiles($source));
    }

    /** @param list<int|float> $source
     * @return array{count: int, bars: list<array<int, float>>}
     */
    private static function profiles(array $source): array
    {
        $count = min(128, max(64, (int) (ceil(count($source) / 8) * 8)));
        $bars = array_fill(0, $count, []);
        for ($profile = 8; $profile <= $count; $profile += 8) {
            for ($index = 0; $index < $count; $index++) {
                if ($index >= $profile) {
                    continue;
                }
                $start = (int) floor($index * count($source) / $profile);
                $end = max($start + 1, (int) floor(($index + 1) * count($source) / $profile));
                $peak = 8.0;
                for ($sample = $start; $sample < $end; $sample++) {
                    $peak = max($peak, (float) ($source[$sample] ?? 8));
                }
                $bars[$index][$profile] = $peak;
            }
        }

        return ['count' => $count, 'bars' => $bars];
    }
}
