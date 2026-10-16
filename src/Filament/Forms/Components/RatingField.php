<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\CalculatesRatingFill;
use Bjanczak\FilamentFlexFields\Concerns\DisplaysRating;
use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\RatingField\PresentsRating;
use Bjanczak\FilamentFlexFields\StateCasts\RatingStateCast;
use Bjanczak\FilamentFlexFields\Support\Rating\Validation\RatingRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

class RatingField extends Field
{
    use CalculatesRatingFill;
    use CanBeReadOnly;
    use DisplaysRating;
    use HasControlSize;
    use HasExtraAlpineAttributes;
    use PresentsRating;

    protected string $view = 'filament-flex-fields::forms.components.rating-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(null);

        $this->rules([
            'numeric',
            fn (RatingField $component): RatingRule => new RatingRule($component->getMax(), $component->isReadOnly()),
            fn (RatingField $component): string => 'min:0',
            fn (RatingField $component): string => 'max:'.$component->getMax(),
            fn (RatingField $component): ?string => $component->isReadOnly() ? null : 'integer',
        ]);
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new RatingStateCast($this->isReadOnly())];
    }
}
