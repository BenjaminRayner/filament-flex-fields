<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasChoiceCardOptions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists\HasChecklistLimits;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\Validation\CardSelection;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\ChecklistStateCast;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class ChoiceCheckboxCards extends Field
{
    use HasChecklistLimits;
    use HasChoiceCardOptions;

    protected string $view = 'filament-flex-fields::forms.components.choice-checkbox-cards';

    public function getIndicator(): string
    {
        $indicator = $this->evaluate($this->indicator);
        if (filled($indicator)) {
            return (string) $indicator;
        }

        return match ($this->getLayout()) {
            'media' => 'none', 'featured' => 'check', default => 'checkbox'
        };
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new ChecklistStateCast];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
        $this->rule('array');
        $this->rule(static fn (self $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            CardSelection::validate($value, true, $component->getEnabledOptionKeys(), $component->getSelectionLimits(), $component->isRequired(), 'choice_checkbox_cards', $fail);
        });
    }
}
