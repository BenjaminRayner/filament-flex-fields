<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationOption as Option;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationType as Type;

/** Reusable parameter contracts only. Views, querying, validation and runtime remain component-owned. */
final class BuiltInChildContracts
{
    public static function select(): ChildComponentContract
    {
        $options = [
            'label' => new Option(Type::String, max: 1024),
            'placeholder' => new Option(Type::String, nullable: true, max: 1024),
            'size' => new Option(Type::String, choices: ['sm', 'md', 'lg']),
            'options' => new Option(Type::Map, max: 1000),
            'searchDebounce' => new Option(Type::Integer, min: 0, max: 60000),
            'searchResultsPageSize' => new Option(Type::Integer, min: 1, max: 1000),
            'optionsLimit' => new Option(Type::Integer, min: 1, max: 100000),
            'virtualizeThreshold' => new Option(Type::Integer, min: 1, max: 100000),
            'virtualRowHeight' => new Option(Type::Integer, min: 1, max: 512),
            'virtualWindowSize' => new Option(Type::Integer, min: 1, max: 500),
            'dropdownMinWidth' => new Option(Type::Integer, min: 120, max: 600),
            'menuMaxHeight' => new Option(Type::Integer, min: 80, max: 1200),
            'minItems' => new Option(Type::Integer, min: 0, max: 100000),
            'maxItems' => new Option(Type::Integer, min: 0, max: 100000),
        ];
        foreach (['multiple', 'searchable', 'preload', 'clearable', 'required', 'disabled',
            'paginatedSearchResults', 'optionGroupSeparators', 'selectionIndicator', 'plainTrigger',
            'richListTriggerDisplay', 'keepSelectedOptionsInDropdown', 'richOptions', 'allowHtml'] as $option) {
            $options[$option] = new Option(Type::Boolean);
        }

        return new ChildComponentContract(ComponentKind::Field, SelectField::class, new ConfigurationSchema($options));
    }
}
