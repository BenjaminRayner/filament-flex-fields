<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BubbleChoice\ConfiguresBubbleChoice;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\BubbleChoice\PresentsBubbleChoice;
use Bjanczak\FilamentFlexFields\StateCasts\BubbleChoiceStateCast;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\SelectionValue;
use Bjanczak\FilamentFlexFields\Support\BubbleChoice\Validation\SelectionRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class BubbleChoiceField extends Field
{
    use CanBeReadOnly;
    use ConfiguresBubbleChoice;
    use HasControlSize;
    use HasFieldRounding;
    use PresentsBubbleChoice;

    protected string $view = 'filament-flex-fields::forms.components.bubble-choice-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
        $this->rule(fn (BubbleChoiceField $component): SelectionRule => new SelectionRule(
            $component->getNormalizedOptions(), $component->getMinItems(), $component->getMaxItems(),
            $component->getExactItems(), $component->isRequired(),
        ));
        $this->dehydrateStateUsing(fn (mixed $state): mixed => SelectionValue::canonicalize($state));
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new BubbleChoiceStateCast];
    }
}
