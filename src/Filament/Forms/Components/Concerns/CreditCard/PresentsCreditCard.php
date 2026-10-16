<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CreditCard;

trait PresentsCreditCard
{
    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-credit-card-field', 'fff-credit-card-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(), 'fff-credit-card-field--'.$this->getVariant(),
            'fff-credit-card-field--input-'.$this->getInputVariant()];
    }
}
