<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\ColorPicker;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Closure;

/** @mixin FlexColorPickerField */
trait ConfiguresColorPicker
{
    /** @var array<string, mixed> */
    protected array $colorPickerOptions = [];

    public function layout(string|Closure $value): static
    {
        $this->colorPickerOptions['layout'] = $value;

        return $this;
    }

    public function format(string|Closure $value): static
    {
        $this->colorPickerOptions['format'] = $value;

        return $this;
    }

    /** @param list<string>|Closure $formats */
    public function allowedFormats(array|Closure $formats): static
    {
        $this->colorPickerOptions['allowedFormats'] = $formats;

        return $this;
    }

    public function alpha(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['alphaEnabled'] = $enabled;

        return $this;
    }

    public function opacity(bool|Closure $enabled = true): static
    {
        return $this->alpha($enabled);
    }

    public function eyedropper(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['eyedropperEnabled'] = $enabled;

        return $this;
    }

    public function trigger(string|Closure $value): static
    {
        $this->colorPickerOptions['trigger'] = $value;

        return $this;
    }

    public function iconOnly(): static
    {
        return $this->trigger('icon')->showValue(false);
    }

    public function swatchOnly(): static
    {
        return $this->trigger('swatch')->showValue(false);
    }

    public function inline(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['inline'] = $enabled;

        return $this;
    }

    public function showValue(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['showValue'] = $enabled;

        return $this;
    }

    public function showFormatSelector(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['showFormatSelector'] = $enabled;

        return $this;
    }

    public function showSwatches(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['showSwatches'] = $enabled;

        return $this;
    }

    public function showFields(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['showFields'] = $enabled;

        return $this;
    }

    public function channelFields(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['channelFields'] = $enabled;

        return $this;
    }

    public function showChannelLabels(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['showChannelLabels'] = $enabled;

        return $this;
    }

    public function closeOnSelect(bool|Closure $enabled = true): static
    {
        $this->colorPickerOptions['closeOnSelect'] = $enabled;

        return $this;
    }

    public function placement(string|Closure $value): static
    {
        $this->colorPickerOptions['placement'] = $value;

        return $this;
    }

    public function panelWidth(int|Closure $value): static
    {
        $this->colorPickerOptions['panelWidth'] = $value;

        return $this;
    }

    public function panelRadius(int|Closure $value): static
    {
        $this->colorPickerOptions['panelRadius'] = $value;

        return $this;
    }

    public function animationDuration(int|Closure $value): static
    {
        $this->colorPickerOptions['animationDuration'] = $value;

        return $this;
    }

    public function gridColumns(int|Closure $value): static
    {
        $this->colorPickerOptions['gridColumns'] = $value;

        return $this;
    }

    public function gridRows(int|Closure $value): static
    {
        $this->colorPickerOptions['gridRows'] = $value;

        return $this;
    }

    /** @param list<string>|Closure|null $colors */
    public function gridColors(array|Closure|null $colors): static
    {
        $this->colorPickerOptions['gridColors'] = $colors;

        return $this;
    }

    public function hex(): static
    {
        return $this->format(self::FORMAT_HEX);
    }

    public function rgb(): static
    {
        return $this->format(self::FORMAT_RGB);
    }

    public function rgba(): static
    {
        return $this->format(self::FORMAT_RGBA);
    }

    public function hsl(): static
    {
        return $this->format(self::FORMAT_HSL);
    }

    public function hsla(): static
    {
        return $this->format('hsla')->alpha();
    }
}
