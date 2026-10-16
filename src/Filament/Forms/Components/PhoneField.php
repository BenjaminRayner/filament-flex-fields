<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone\ConfiguresPhone;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone\NormalizesPhoneState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone\PresentsPhone;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone\ResolvesPhoneCatalog;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone\PhoneCountrySelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone\PhoneValidation;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

class PhoneField extends Field
{
    use CanBeReadOnly;
    use ConfiguresPhone;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasPlaceholder;
    use NormalizesPhoneState;
    use PresentsPhone;
    use ResolvesPhoneCatalog;

    protected string $view = 'filament-flex-fields::forms.components.phone-field';

    public function getCountrySelect(): PhoneCountrySelect
    {
        return PhoneCountrySelect::forPhone($this);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (PhoneField $component) => PhoneValidation::forField($component));
    }
}
