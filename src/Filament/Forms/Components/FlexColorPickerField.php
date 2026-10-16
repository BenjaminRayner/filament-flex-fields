<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\ColorPicker\ConfiguresColorPicker;
use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Support\ColorPicker\ColorValue;
use Bjanczak\FilamentFlexFields\Support\ColorPicker\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration as Config;
use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

class FlexColorPickerField extends Field
{
    use CanBeReadOnly;
    use ConfiguresColorPicker;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;

    public const string LAYOUT_ADVANCED = 'advanced';

    public const string LAYOUT_GRID = 'grid';

    public const string FORMAT_HEX = 'hex';

    public const string FORMAT_RGB = 'rgb';

    public const string FORMAT_HSL = 'hsl';

    public const string FORMAT_RGBA = 'rgba';

    protected string $view = 'filament-flex-fields::forms.components.flex-color-picker-field';

    protected string|Closure $variant = 'primary';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(function (self $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if ($value === null || $value === '') {
                    return;
                }
                if (! ColorValue::valid($value, $component->isAlphaEnabled())) {
                    $fail(__('filament-flex-fields::color-picker.invalid'));
                }
            };
        });
    }

    public function variant(string|Closure $value): static
    {
        $this->variant = $value;

        return $this;
    }

    public function getVariant(): string
    {
        return Config::choice($this->evaluate($this->variant), ['primary', 'secondary', 'soft', 'flat'], 'color.variant');
    }

    /** @return array<string, mixed> */
    public function getPickerConfiguration(): array
    {
        return PickerConfiguration::resolve(array_map(fn (mixed $value): mixed => $this->evaluate($value), $this->colorPickerOptions));
    }

    public function getLayout(): string
    {
        return $this->getPickerConfiguration()['layout'];
    }

    public function getFormat(): string
    {
        $format = $this->getPickerConfiguration()['format'];

        return ! $this->isAlphaEnabled() && in_array($format, ['rgba', 'hsla'], true) ? substr($format, 0, -1) : $format;
    }

    public function isAlphaEnabled(): bool
    {
        return $this->getPickerConfiguration()['alphaEnabled'];
    }

    public function isEyedropperEnabled(): bool
    {
        return $this->getPickerConfiguration()['eyedropperEnabled'];
    }

    public function getGridColumns(): int
    {
        return $this->getPickerConfiguration()['gridColumns'];
    }

    public function getGridRows(): int
    {
        return $this->getPickerConfiguration()['gridRows'];
    }

    /** @return list<string>|null */
    public function getGridColors(): ?array
    {
        return $this->getPickerConfiguration()['gridColors'];
    }

    public function isValidColorString(?string $value): bool
    {
        return $value === null || trim($value) === '' || ColorValue::valid($value, $this->isAlphaEnabled());
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-flex-color-picker-field', 'fff-flex-text-input-field', 'fff-flex-color-picker-field--'.$this->getSize(), 'fff-rounding-'.$this->getRounding()];
    }
}
