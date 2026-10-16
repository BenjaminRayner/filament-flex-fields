<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasImageChoiceCardOptions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\ChoiceLists\HasChecklistLimits;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\Validation\CardSelection;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\ChecklistStateCast;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\State\RadioStateCast;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class ImageChoiceCards extends Field
{
    use HasChecklistLimits;
    use HasImageChoiceCardOptions;

    protected string $view = 'filament-flex-fields::forms.components.image-choice-cards';

    protected bool|Closure $isMultiple = false;

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function pruneDisabledSelection(): void
    {
        $this->state($this->pruneState($this->getState()));
    }

    public function pruneState(mixed $state): mixed
    {
        if ($this->isMultiple()) {
            if (! is_array($state) || ! array_is_list($state)) {
                return $state;
            }
            foreach ($state as $value) {
                if (! is_string($value) && ! is_int($value)) {
                    return $state;
                }
            }

            return array_values(array_filter($state, fn (string|int $key): bool => ! $this->isOptionDisabled($key)));
        }
        if (! is_string($state) && ! is_int($state)) {
            return $state;
        }

        return $this->isOptionDisabled($state) ? null : $state;
    }

    public function getIndicator(): string
    {
        $indicator = $this->evaluate($this->indicator);
        if (filled($indicator)) {
            return (string) $indicator;
        }

        return $this->isMultiple() ? 'checkbox' : 'check';
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), $this->isMultiple() ? new ChecklistStateCast : new RadioStateCast];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->default(fn (self $component): mixed => $component->isMultiple() ? [] : null);
        $this->afterStateHydrated(static fn (self $component) => $component->pruneDisabledSelection());
        $this->rule(static fn (self $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            CardSelection::validate($value, $component->isMultiple(), $component->getEnabledOptionKeys(), $component->getSelectionLimits(), $component->isRequired(), 'image_choice_cards', $fail);
        });
    }
}
