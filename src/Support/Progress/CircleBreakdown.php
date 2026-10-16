<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Progress;

use Bjanczak\FilamentFlexFields\Support\ProgressColor;
use InvalidArgumentException;

/** Static rounded annular sectors; no chart library or client measurements. */
final readonly class CircleBreakdown
{
    public const COLORS = ['#a3e635', '#4c8df6', '#b47af4', '#e861b5', '#f59e0b', '#2dd4bf'];

    /** @var list<array{label: string, value: float, displayValue: string, color: string, ratio: float, path: string}> */
    public array $segments;

    public float $total;

    public ?int $activeIndex;

    /** @param array<array-key, mixed> $segments */
    public function __construct(array $segments, ?int $activeIndex = null, float $gap = 3.0)
    {
        if (count($segments) > 64 || ! is_finite($gap) || $gap < 0 || $gap > 12) {
            throw new InvalidArgumentException('Circle breakdown supports up to 64 segments and a finite gap of 0–12 degrees.');
        }
        $normalized = [];
        $total = 0.0;
        foreach (array_values($segments) as $index => $segment) {
            if (! is_array($segment) || ! isset($segment['label'], $segment['value']) || ! is_string($segment['label']) || trim($segment['label']) === ''
                || (! is_int($segment['value']) && ! is_float($segment['value'])) || ! is_finite((float) $segment['value']) || $segment['value'] < 0) {
                throw new InvalidArgumentException('Each circle segment requires a label and a finite non-negative numeric value.');
            }
            if (isset($segment['displayValue']) && ! is_string($segment['displayValue'])) {
                throw new InvalidArgumentException('Circle segment displayValue must be a string.');
            }
            $color = $segment['color'] ?? self::COLORS[$index % count(self::COLORS)];
            if (! is_string($color)) {
                throw new InvalidArgumentException('Circle segment color must be a string.');
            }
            $value = (float) $segment['value'];
            $total += $value;
            if (! is_finite($total)) {
                throw new InvalidArgumentException('Circle segment total must be finite.');
            }
            $normalized[] = ['label' => $segment['label'], 'value' => $value, 'displayValue' => $segment['displayValue'] ?? number_format($value, 0, '.', ','),
                'color' => ProgressColor::css($color), 'ratio' => 0.0, 'path' => ''];
        }
        $this->total = $total;
        if ($activeIndex !== null && ! isset($normalized[$activeIndex])) {
            throw new InvalidArgumentException('Circle activeSegment must reference an existing segment.');
        }
        $positiveCount = count(array_filter($normalized, static fn (array $segment): bool => $segment['value'] > 0));
        $gap = $positiveCount <= 1 ? 0.0 : min($gap, 90.0 / $positiveCount);
        $available = 180.0 - $gap * max(0, $positiveCount - 1);
        $angle = 0.0;
        $largest = 0.0;
        $dominant = null;
        foreach ($normalized as $index => &$segment) {
            $segment['ratio'] = $total > 0 ? $segment['value'] / $total : 0.0;
            if ($segment['value'] <= 0) {
                continue;
            }
            $span = $available * $segment['ratio'];
            $segment['path'] = self::sector($angle, $angle + $span);
            $angle += $span + $gap;
            if ($segment['value'] > $largest) {
                $largest = $segment['value'];
                $dominant = $index;
            }
        }
        unset($segment);
        $this->activeIndex = $activeIndex ?? $dominant;
        $this->segments = $normalized;
    }

    public static function sector(float $start, float $end): string
    {
        $outer = 104.0;
        $inner = 78.0;
        $half = deg2rad(max(0.000001, $end - $start) / 2.0);
        $sine = sin($half);
        $corner = min(13.0, $outer * $sine / (1.0 + $sine), $inner * $sine / max(0.000001, 1.0 - $sine)) * 0.999999;
        $outerOffset = rad2deg(asin($corner / ($outer - $corner)));
        $innerOffset = rad2deg(asin($corner / ($inner + $corner)));
        $outerEdge = sqrt(($outer - $corner) ** 2 - $corner ** 2);
        $innerEdge = sqrt(($inner + $corner) ** 2 - $corner ** 2);
        $point = static fn (float $radius, float $angle): string => self::number(120.0 - $radius * cos(deg2rad($angle))).' '.self::number(110.0 - $radius * sin(deg2rad($angle)));
        $c = self::number($corner);

        return 'M '.$point($outerEdge, $start)
            ." A {$c} {$c} 0 0 1 ".$point($outer, $start + $outerOffset)
            .' A 104 104 0 0 1 '.$point($outer, $end - $outerOffset)
            ." A {$c} {$c} 0 0 1 ".$point($outerEdge, $end)
            .' L '.$point($innerEdge, $end)
            ." A {$c} {$c} 0 0 1 ".$point($inner, $end - $innerOffset)
            .' A 78 78 0 0 0 '.$point($inner, $start + $innerOffset)
            ." A {$c} {$c} 0 0 1 ".$point($innerEdge, $start).' Z';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
