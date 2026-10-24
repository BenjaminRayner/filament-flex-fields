<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use BackedEnum;
use BladeUI\Icons\Factory;
use Filament\Support\Contracts\ScalableIcon;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\ComponentAttributeBag;
use WeakMap;

/** Request-owned, bounded presentation cache for named control glyphs only. */
final class RenderedControlIcon
{
    /** @var WeakMap<Factory, array{sets: array<mixed>, html: array<string, string>, bytes: int}> */
    private WeakMap $factories;

    public function __construct()
    {
        $this->factories = new WeakMap;
    }

    public function render(string|BackedEnum|Htmlable|null $icon, ?IconSize $size = null, ?ComponentAttributeBag $attributes = null): string
    {
        $size ??= IconSize::Medium;
        $values = $attributes?->getAttributes() ?? [];
        // User HTML, image URLs, IDs, ARIA names, nonces and custom attribute
        // objects retain native evaluation. Never retain owner/user content.
        $eligible = ! $icon instanceof Htmlable && array_diff(array_keys($values), ['class', 'stroke-width']) === [];
        foreach ($values as $value) {
            $eligible = $eligible && (is_string($value) || is_int($value) || is_float($value));
        }
        if (! $eligible) {
            return \Filament\Support\generate_icon_html($icon, size: $size, attributes: $attributes)?->toHtml() ?? '';
        }
        if ($icon instanceof ScalableIcon) {
            $icon = $icon->getIconForSize($size);
        } elseif ($icon instanceof BackedEnum && is_string($icon->value)) {
            $icon = $icon->value;
        }
        if (! is_string($icon) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $icon)) {
            return \Filament\Support\generate_icon_html($icon, size: $size, attributes: $attributes)?->toHtml() ?? '';
        }
        $factory = app(Factory::class);
        $sets = $factory->all();
        $partition = $this->factories[$factory] ?? ['sets' => $sets, 'html' => [], 'bytes' => 0];
        if ($partition['sets'] !== $sets) {
            $partition = ['sets' => $sets, 'html' => [], 'bytes' => 0];
        }
        $key = serialize([$icon, $size->value, $values]);
        if (isset($partition['html'][$key])) {
            return $partition['html'][$key];
        }
        $html = \Filament\Support\generate_icon_html($icon, size: $size, attributes: $attributes)?->toHtml() ?? '';
        if (count($partition['html']) < 256 && $partition['bytes'] + strlen($html) <= 1048576) {
            $partition['html'][$key] = $html;
            $partition['bytes'] += strlen($html);
        }
        $this->factories[$factory] = $partition;

        return $html;
    }
}
