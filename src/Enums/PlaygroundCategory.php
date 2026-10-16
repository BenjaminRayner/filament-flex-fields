<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Enums;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryDefinition;
use Bjanczak\FilamentFlexFields\Support\Catalog\CategoryRegistry;

enum PlaygroundCategory: string
{
    case Guides = 'guides';
    case Navigation = 'navigation';
    case Buttons = 'buttons';
    case Pickers = 'pickers';
    case DateAndTime = 'date_and_time';
    case Colors = 'colors';
    case Controls = 'controls';
    case Collections = 'collections';
    case TextInput = 'text_input';
    case DataDisplay = 'data_display';
    case Media = 'media';
    case Location = 'location';

    /** Legacy names delegate to the official v4 taxonomy. */
    public function label(): string
    {
        return CatalogValue::localized($this->definition()->label);
    }

    public function sort(): int
    {
        return $this->definition()->sort;
    }

    private function definition(): CategoryDefinition
    {
        return app(CategoryRegistry::class)->all()[str_replace('_', '-', $this->value)];
    }
}
