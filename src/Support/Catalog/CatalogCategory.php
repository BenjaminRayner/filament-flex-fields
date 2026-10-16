<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

/** Official taxonomy: extensions select one category and cannot invent others. */
enum CatalogCategory: string
{
    case Guides = 'guides';
    case Navigation = 'navigation';
    case Buttons = 'buttons';
    case Pickers = 'pickers';
    case DateAndTime = 'date-and-time';
    case Colors = 'colors';
    case Controls = 'controls';
    case Collections = 'collections';
    case TextInput = 'text-input';
    case DataDisplay = 'data-display';
    case Media = 'media';
    case Location = 'location';
}
