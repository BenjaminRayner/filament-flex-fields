<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField;

use Bjanczak\FilamentFlexFields\Support\Nps\OptionValue;

trait PresentsNpsField
{
    /** @return array{options: list<array{value: string|int, label: string|int, disabled: bool, selected: bool, tone: ?string, style: string, icon: ?string, image: ?string}>, variant: string, size: string, rounding: string, colorCoded: bool, disabled: bool, required: bool, minLabel: ?string, maxLabel: ?string, focus: ?string} */
    public function getNpsPresentation(): array
    {
        $options = $this->getOptions();
        $variant = $this->getVariant();
        $disabled = $this->isDisabled();
        $coded = $this->isColorCoded();
        $disabledKeys = array_fill_keys(array_map(strval(...), $this->getDisabledOptions()), true);
        $current = OptionValue::identity($this->getState());
        $backgrounds = $coded ? [] : $this->getColors();
        $texts = $coded ? [] : $this->getTextColors();
        $icons = $variant === 'emojis' ? $this->getIcons() : [];
        $images = $variant === 'emojis' ? $this->getEmojiImages() : [];
        $rows = [];
        $focus = null;
        foreach ($options as $value => $label) {
            $key = (string) $value;
            $blocked = $disabled || isset($disabledKeys[$key]);
            $selected = $current === $key;
            if (! $blocked && ($focus === null || $selected)) {
                $focus = $key;
            }
            $variables = $coded ? [] : $this->npsStyleVariables($value, $backgrounds, $texts);
            $rows[] = [
                'value' => $value, 'label' => $label, 'disabled' => isset($disabledKeys[$key]), 'selected' => $selected,
                'tone' => $coded ? OptionValue::tone($value) : null,
                'style' => implode('; ', array_map(static fn (string $property, string $css): string => "{$property}: {$css}", array_keys($variables), array_values($variables))),
                'icon' => $icons[$key] ?? null,
                'image' => $variant === 'emojis' ? ($images[$key] ?? $this->defaultNpsEmojiImage($key)) : null,
            ];
        }

        return [
            'options' => $rows, 'variant' => $variant, 'size' => $this->getSize(), 'rounding' => $this->getRounding(),
            'colorCoded' => $coded, 'disabled' => $disabled, 'required' => $this->isRequired(),
            'minLabel' => $this->getMinLabel(), 'maxLabel' => $this->getMaxLabel(), 'focus' => $focus,
        ];
    }
}
