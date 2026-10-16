<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;

/**
 * Select wrapper classes derived from current appearance and state.
 *
 * @mixin SelectField
 */
trait PresentsSelectWrapper
{
    /**
     * @return array<int|string, bool|string>
     */
    public function getWrapperClasses(): array
    {
        $classes = [
            'fff-select-field',
            'fff-select-field--plain-trigger' => $this->hasPlainTrigger(),
            'fff-select-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(),
            'fff-select-field--'.$this->getVariant(),
            'fff-select-field--layout-'.$this->getOptionLayout(),
            'fff-select-field--chips-'.$this->getChipColor(),
        ];

        if ($color = $this->getColor()) {
            $classes['fi-color-'.$color] = $color;
        }

        if ($this->hasInlineFieldLabel() && filled($this->getLabel()) && ! $this->isLabelHidden()) {
            $classes['fff-select-field--inline-field-label'] = true;
        }

        if ($this->hasInlineSearch()) {
            $classes['fff-select-field--inline-search'] = true;
        }

        if ($this->isMultiple()) {
            $classes['fff-select-field--multiple'] = true;
        }

        if (! $this->isClearableInUi()) {
            $classes['fff-select-field--not-clearable'] = true;
        } elseif ($this->hasSelectedValueForClearButton() && ! $this->isLocallyDisabled()) {
            $classes['fff-select-field--clearable-has-value'] = true;
        }

        if ($this->shouldUseRichListTriggerDisplay()) {
            $classes['fff-select-field--rich-list-trigger'] = true;
        }

        if ($this->shouldShowFocusOutline()) {
            $classes['has-focus-outline'] = true;
        }

        return $classes;
    }
}
