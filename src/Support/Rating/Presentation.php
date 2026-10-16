<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rating;

use BackedEnum;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

final class Presentation
{
    /** @return array{value: ?float, max: int, size: string, color: string, iconMarkup: string, fills: list<float>, showValue: bool} */
    public static function make(mixed $state, int $max, string $size, string $color, string|BackedEnum|Htmlable $icon, bool $showValue, bool $table = false): array
    {
        RatingValue::assertMaximum($max);
        if (! in_array($size, ['sm', 'md', 'lg'], true)) {
            throw new InvalidArgumentException('Rating size must be sm, md or lg.');
        }
        $value = RatingValue::display($state, $max);
        $iconSize = $table ? ($size === 'lg' ? IconSize::Medium : IconSize::Small)
            : match ($size) {
                'sm' => IconSize::Small, 'lg' => IconSize::Large, default => IconSize::Medium
            };

        return [
            'value' => $value, 'max' => $max, 'size' => $size, 'color' => $color,
            'iconMarkup' => \Filament\Support\generate_icon_html($icon, size: $iconSize)?->toHtml() ?? '',
            'fills' => array_map(static fn (int $index): float => round(RatingValue::fill($value, $index) * 100, 4), range(1, $max)),
            'showValue' => $showValue,
        ];
    }
}
