<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Calculator;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresCalculator
{
    protected int|Closure|null $calculatorPrecision = null;

    protected string|Closure $calculatorRounding = 'truncate';

    protected string|BackedEnum|Htmlable|Closure|null $calculatorIcon = null;

    public function decimalPlaces(int|Closure|null $places): static
    {
        $this->calculatorPrecision = $places;

        return $this;
    }

    public function roundingMode(string|Closure $mode): static
    {
        $this->calculatorRounding = $mode;

        return $this;
    }

    public function calculatorIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->calculatorIcon = $icon;

        return $this;
    }

    public function getDecimalPlaces(): ?int
    {
        $precision = $this->evaluate($this->calculatorPrecision);
        if ($precision === null) {
            return null;
        }
        $precision = PickerConfiguration::integer($precision, 'decimalPlaces');
        if ($precision < 0 || $precision > 12) {
            throw new InvalidArgumentException('Calculator precision must be between 0 and 12.');
        }

        return $precision;
    }

    public function getRoundingMode(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->calculatorRounding), ['round', 'ceil', 'floor', 'truncate'], 'roundingMode');
    }

    public function getCalculatorIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->calculatorIcon) ?? FlexIcon::make('calculator');
    }

    /** @return array{integer: bool, decimalPlaces: ?int, roundingMode: string, min: mixed, max: mixed, disabled: bool, label: string} */
    public function getCalculatorConfiguration(): array
    {
        $label = $this->getLabel();

        return ['integer' => $this->isInteger(), 'decimalPlaces' => $this->getDecimalPlaces(),
            'roundingMode' => $this->getRoundingMode(), 'min' => $this->getMinValue(), 'max' => $this->getMaxValue(),
            'disabled' => $this->isDisabled() || $this->isReadOnly(),
            'label' => $label instanceof Htmlable ? strip_tags($label->toHtml()) : ($label ?? '')];
    }
}
