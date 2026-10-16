<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasChoiceCardOptions;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\Validation\CardSelection;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\RadioStateCast;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation\SelectionLimits;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class ChoiceCards extends Field
{
    use HasChoiceCardOptions;

    protected string $view = 'filament-flex-fields::forms.components.choice-cards';

    public function getIndicator(): string
    {
        $indicator = $this->evaluate($this->indicator);
        if (filled($indicator)) {
            return (string) $indicator;
        }

        return match ($this->getLayout()) {
            'media' => 'none', default => 'check'
        };
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new RadioStateCast];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(static fn (self $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            CardSelection::validate($value, false, $component->getEnabledOptionKeys(), new SelectionLimits(null, null, null), $component->isRequired(), 'choice_checkbox_cards', $fail);
        });
    }
}
