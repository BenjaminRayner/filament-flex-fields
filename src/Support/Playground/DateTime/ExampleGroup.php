<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\DateTime;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Section;

final readonly class ExampleGroup
{
    /** @param list<Field|ValidationExample> $examples */
    public function __construct(
        public string $title,
        public string $description,
        public array $examples,
        public int $columns = 3,
    ) {}

    public function component(): Section
    {
        return Section::make($this->title)
            ->description($this->description)
            ->columns(['default' => 1, 'lg' => min(2, $this->columns), '2xl' => $this->columns])
            ->extraAttributes(['class' => 'fff-playground-section fff-date-gallery-section'])
            ->schema(array_map(static fn (Field|ValidationExample $example) => $example instanceof ValidationExample ? $example->component() : $example, $this->examples));
    }
}
