<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ColorPicker;

/** Server palette matches the runtime, reserving the exact first-paint footprint. */
final class ColorPickerPresentation
{
    private const SWATCHES = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#06B6D4', '#3B82F6', '#8B5CF6', '#EC4899', '#F43F5E'];

    /**
     * @param  array<string, mixed>  $configuration
     * @return list<string>
     */
    public static function palette(array $configuration): array
    {
        $layout = $configuration['layout'];
        if (! $configuration['showSwatches'] && ! in_array($layout, ['grid', 'swatches'], true)) {
            return [];
        }
        if (is_array($configuration['gridColors'] ?? null) && $configuration['gridColors'] !== []) {
            $colors = [];
            foreach ($configuration['gridColors'] as $color) {
                if (! is_string($color)) {
                    throw new \InvalidArgumentException('Palette colors must be strings.');
                }
                $colors[] = $color;
            }

            return $colors;
        }
        if ($configuration['showSwatches'] || $layout === 'swatches') {
            return self::SWATCHES;
        }
        $columns = $configuration['gridColumns'];
        $rows = $configuration['gridRows'];
        $palette = [];
        for ($row = 0; $row < $rows; $row++) {
            $progress = $row / max(1, $rows - 1);
            for ($column = 0; $column < $columns; $column++) {
                $hue = $column / $columns * 6;
                $value = (97 - $progress * 70) / 100;
                $chroma = $value * (22 + $progress * 66) / 100;
                $secondary = $chroma * (1 - abs(fmod($hue, 2) - 1));
                $offset = $value - $chroma;
                $channels = match ((int) floor($hue)) {
                    0 => [$chroma, $secondary, 0], 1 => [$secondary, $chroma, 0],
                    2 => [0, $chroma, $secondary], 3 => [0, $secondary, $chroma],
                    4 => [$secondary, 0, $chroma], default => [$chroma, 0, $secondary],
                };
                $palette[] = '#'.implode('', array_map(static fn (float|int $channel): string => strtoupper(str_pad(dechex((int) round(($channel + $offset) * 255)), 2, '0', STR_PAD_LEFT)), $channels));
            }
        }

        return $palette;
    }
}
