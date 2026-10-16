<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\HasNumberLocale;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\PriceRange\ConfiguresPriceRange;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\PriceRange\PresentsPriceRange;
use Bjanczak\FilamentFlexFields\StateCasts\PriceRangeFieldStateCast;
use Bjanczak\FilamentFlexFields\Support\PriceRange\Validation\RangeRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class PriceRangeField extends Field
{
    use CanBeReadOnly;
    use ConfiguresPriceRange;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasNumberLocale;
    use PresentsPriceRange;

    protected string $view = 'filament-flex-fields::forms.components.price-range-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (PriceRangeField $component): array => [
            'min' => $component->getMin(), 'max' => $component->getMax(),
        ]);
        $this->rule(fn (PriceRangeField $component): RangeRule => new RangeRule($component->getRangeConstraints()));
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new PriceRangeFieldStateCast];
    }
}
