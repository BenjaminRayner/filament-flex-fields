<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use InvalidArgumentException;

class ColorSwatchField extends Field
{
    use HasControlSize;
    use HasExtraAlpineAttributes;

    protected string $view = 'filament-flex-fields::forms.components.color-swatch-field';

    /**
     * @var array<string, string> | Closure
     */
    protected array|Closure $colors = [];

    protected string|Closure|null $sectionLabel = null;

    protected string|BackedEnum|Htmlable|Closure|null $sectionIcon = null;

    protected bool|array|Closure $tooltips = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules([
            'nullable',
            'string',
            fn (ColorSwatchField $component): In => Rule::in(array_keys($component->getColors())),
        ]);
    }

    /**
     * @param  array<string, string> | Closure  $colors
     */
    public function colors(array|Closure $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function sectionLabel(string|Closure|null $label): static
    {
        $this->sectionLabel = $label;

        return $this;
    }

    public function sectionIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->sectionIcon = $icon;

        return $this;
    }

    /**
     * @param  array<string, string> | bool | Closure  $tooltips
     */
    public function tooltips(bool|array|Closure $tooltips = true): static
    {
        $this->tooltips = $tooltips;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        $colors = $this->evaluate($this->colors);
        foreach ($colors as $key => $color) {
            if ((string) $key === '' || ! is_string($color) || trim($color) === ''
                || strlen($color) > 1024 || preg_match('/[;{}<>\\\\\x00-\x1f]/', $color)) {
                throw new InvalidArgumentException('Swatches require nonempty keys and CSS color values without declaration delimiters.');
            }
        }

        return $colors;
    }

    public function getSectionLabel(): ?string
    {
        $label = $this->evaluate($this->sectionLabel);

        return filled($label) ? (string) $label : null;
    }

    public function getDefaultSectionIcon(): string|BackedEnum|Htmlable
    {
        $icon = config('filament-flex-fields.ui.color_swatch_section_icon');

        if (is_string($icon) && filled($icon)) {
            return $icon;
        }

        return FlexIcon::Palette;
    }

    public function getSectionIcon(): string|BackedEnum|Htmlable|null
    {
        $icon = $this->evaluate($this->sectionIcon);

        if (filled($icon)) {
            return $icon;
        }

        if (filled($this->getSectionLabel())) {
            return $this->getDefaultSectionIcon();
        }

        return null;
    }

    public function hasTooltips(): bool
    {
        $tooltips = $this->evaluate($this->tooltips);

        if (is_array($tooltips)) {
            return $tooltips !== [];
        }

        return (bool) $tooltips;
    }

    public function getColorLabel(string $key): string
    {
        $tooltips = $this->evaluate($this->tooltips);

        if (is_array($tooltips) && array_key_exists($key, $tooltips)) {
            return (string) $tooltips[$key];
        }

        return str($key)->replace(['_', '-'], ' ')->title()->toString();
    }

    /**
     * One evaluated snapshot per render; state never includes labels or CSS values.
     *
     * @return array{items: list<array{key: string, color: string, label: string, selected: bool}>, keys: list<string>, disabled: bool, size: string, tooltips: bool, label: ?string, icon: string|BackedEnum|Htmlable|null}
     */
    public function getSwatchPresentation(): array
    {
        $colors = $this->getColors();
        $tooltips = $this->evaluate($this->tooltips);
        $state = $this->getState();
        $items = [];
        foreach ($colors as $key => $color) {
            $key = (string) $key;
            $label = is_array($tooltips) && array_key_exists($key, $tooltips)
                ? (string) $tooltips[$key]
                : str($key)->replace(['_', '-'], ' ')->title()->toString();
            $items[] = ['key' => $key, 'color' => $color, 'label' => $label, 'selected' => $state === $key];
        }
        $label = $this->getSectionLabel();
        $icon = $this->evaluate($this->sectionIcon);

        return [
            'items' => $items, 'keys' => array_column($items, 'key'),
            'disabled' => $this->isDisabled(), 'size' => $this->getSize(),
            'tooltips' => is_array($tooltips) ? $tooltips !== [] : (bool) $tooltips,
            'label' => $label, 'icon' => filled($icon) ? $icon : ($label !== null ? $this->getDefaultSectionIcon() : null),
        ];
    }

    public function isLightSwatch(string $hex): bool
    {
        $normalized = strtolower(trim($hex));

        return in_array($normalized, ['#fff', '#ffffff', 'white', 'rgb(255, 255, 255)', 'rgb(255 255 255)'], true);
    }
}
