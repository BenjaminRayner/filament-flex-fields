<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;

final readonly class ValidationExample
{
    public function __construct(
        public Field $field,
        public mixed $invalid,
        public mixed $valid,
        public string $description,
    ) {}

    public function component(): Group
    {
        return Group::make([
            $this->field,
            View::make('filament-flex-fields::playground.date-time.validation-example')
                ->viewData(['example' => $this]),
        ]);
    }
}
