<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Calculator\ConfiguresCalculator;
use Bjanczak\FilamentFlexFields\Support\Calculator\CalculatorPrecision;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use Closure;
use Filament\Actions\Action;

/** Numeric storage and every input affordance belong to the actual FlexTextInput. */
class CalculatorField extends FlexTextInput
{
    use ConfiguresCalculator;

    protected string $view = 'filament-flex-fields::forms.components.calculator-field';

    protected bool|Closure $calculatorInteger = false;

    public function integer(bool|Closure $condition = true): static
    {
        $this->calculatorInteger = $condition;

        return parent::integer($condition);
    }

    public function isInteger(): bool
    {
        return (bool) $this->evaluate($this->calculatorInteger);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->numeric()->placeholder(fn () => OptionText::required(__('filament-flex-fields::default.calculator.placeholder'), 'calculator placeholder'));
        $this->dehydrateStateUsing(static fn (CalculatorField $component, mixed $state): mixed => is_int($state) || is_float($state)
                ? CalculatorPrecision::apply($state, $component->getDecimalPlaces(), $component->isInteger(), $component->getRoundingMode())
                : $state);
    }

    /** @return array<array-key, Action> */
    public function getSuffixActions(): array
    {
        $actions = parent::getSuffixActions();
        $actions['fffCalculator'] = $this->prepareAction(Action::make('fffCalculator')
            ->iconButton()
            ->icon($this->getCalculatorIcon())
            ->label(fn (): string => OptionText::required(__('filament-flex-fields::default.calculator.open'), 'calculator action'))
            ->disabled($this->isDisabled() || $this->isReadOnly())
            ->extraAttributes(['data-fff-calculator-trigger' => true])
            ->alpineClickHandler("\$dispatch('fff-calculator-open', { anchor: \$event.currentTarget })"));

        return $actions;
    }

    public function getCalculatorFieldId(): string
    {
        return isset($this->container) ? $this->getLivewire()->getId().':'.$this->getStatePath() : $this->getName();
    }
}
