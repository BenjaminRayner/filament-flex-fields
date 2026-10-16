<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DualListbox\ConfiguresDualListbox;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DualListbox\PresentsDualListbox;
use Bjanczak\FilamentFlexFields\StateCasts\DualListboxStateCast;
use Bjanczak\FilamentFlexFields\Support\DualListbox\SelectionValue;
use Bjanczak\FilamentFlexFields\Support\DualListbox\Validation\SelectionRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class DualListboxField extends Field
{
    use CanBeReadOnly;
    use ConfiguresDualListbox;
    use HasControlSize;
    use HasFieldRounding;
    use PresentsDualListbox;

    public const int VIRTUAL_SCROLL_THRESHOLD = 100;

    public const int LARGE_OPTIONS_THRESHOLD = 100;

    protected string $view = 'filament-flex-fields::forms.components.dual-listbox-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
        $this->rule(fn (DualListboxField $component): SelectionRule => new SelectionRule(
            $component->getNormalizedOptions(), $component->getMinItems(), $component->getMaxItems(), $component->isRequired(),
        ));
        $this->dehydrateStateUsing(fn (mixed $state): mixed => SelectionValue::canonicalize($state));
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new DualListboxStateCast];
    }
}
