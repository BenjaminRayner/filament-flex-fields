<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeSegmentsField;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Contracts\FieldConfigurator;
use Filament\Schemas\Components\Component;

final class FlexTimeSegmentsFieldConfigurator implements FieldConfigurator
{
    public function configure(Component $field, array $config): Component
    {
        if (! $field instanceof FlexTimeSegmentsField) {
            throw new \InvalidArgumentException('Time configuration requires a FLEX time field.');
        }

        return (new DateTimeFieldConfigurator)->configureDateTimeField($field, $config);
    }
}
