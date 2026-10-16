<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SwitchField;

use Bjanczak\FilamentFlexFields\Support\SwitchField\BooleanValue;
use Filament\Support\View\Components\ToggleComponent;

trait PresentsSwitch
{
    /** @return array<string, mixed> */
    public function getSwitchPresentation(): array
    {
        $onClasses = \Filament\Support\get_component_color_classes(ToggleComponent::class, $this->getEffectiveOnColor());
        $offClasses = \Filament\Support\get_component_color_classes(ToggleComponent::class, $this->getEffectiveOffColor());
        $onIcon = $this->getOnIcon();
        $offIcon = $this->getOffIcon();

        return [
            'variant' => $this->getVariant(), 'layout' => $this->getLayout(),
            'labelPosition' => $this->getLabelPosition(), 'size' => $this->getSize(),
            'label' => $this->getLabel(), 'description' => $this->getDescription(),
            'badge' => $this->getBadge(), 'badgeColor' => $this->getBadgeColor(),
            'checked' => BooleanValue::checked($this->getState()), 'disabled' => $this->isDisabled(),
            'inline' => $this->isInlineToggle(), 'inlineLabel' => $this->showsInlineFieldLabel(),
            'compact' => $this->isCompact(), 'ripple' => $this->isRippleEnabled(),
            'onIcon' => $onIcon, 'offIcon' => $offIcon,
            'hasIcons' => filled($onIcon) || filled($offIcon),
            'onClasses' => array_values($onClasses), 'offClasses' => array_values($offClasses),
        ];
    }
}
