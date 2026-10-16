<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\RatingField;

use Bjanczak\FilamentFlexFields\Support\Rating\Presentation;

trait PresentsRating
{
    /** @return array<string, mixed> */
    public function getRatingPresentation(): array
    {
        return [...Presentation::make($this->getState(), $this->getMax(), $this->getSize(), $this->getColor(), $this->getIcon(), $this->shouldShowValue()),
            'disabled' => $this->isDisabled(), 'readOnly' => $this->isReadOnly(), 'label' => $this->getLabel(),
        ];
    }
}
