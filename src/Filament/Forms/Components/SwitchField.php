<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SwitchField\ConfiguresSwitch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SwitchField\PresentsSwitch;
use Bjanczak\FilamentFlexFields\StateCasts\SwitchStateCast;
use Bjanczak\FilamentFlexFields\Support\SwitchField\BooleanRule;
use Filament\Forms\Components\Concerns\CanBeAccepted;
use Filament\Forms\Components\Concerns\CanFixIndistinctState;
use Filament\Forms\Components\Concerns\HasToggleColors;
use Filament\Forms\Components\Concerns\HasToggleIcons;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

class SwitchField extends Field
{
    use CanBeAccepted;
    use CanFixIndistinctState;
    use ConfiguresSwitch;
    use HasControlSize;
    use HasExtraAlpineAttributes;
    use HasToggleColors;
    use HasToggleIcons;
    use PresentsSwitch;

    protected string $view = 'filament-flex-fields::forms.components.switch-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->hiddenLabel();
        $this->default(false);
        $this->rule(new BooleanRule);
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new SwitchStateCast];
    }
}
