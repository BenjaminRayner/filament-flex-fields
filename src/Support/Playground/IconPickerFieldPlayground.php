<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class IconPickerFieldPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'icon_picker__empty' => null,
            'icon_picker__heroicon' => 'hugeicons-star',
            'icon_picker__gravity' => 'hugeicons-star',
            'icon_picker__multi_set' => 'hugeicons-star',
            'icon_picker__grid' => 'hugeicons-flash',
            'icon_picker__list' => 'hugeicons-star',
            'icon_picker__label_grid' => 'hugeicons-favourite',
            'icon_picker__preload' => 'hugeicons-flash',
            'icon_picker__whitelist' => 'hugeicons-favourite',
            'icon_picker__sm' => 'hugeicons-star',
            'icon_picker__md' => 'hugeicons-flash',
            'icon_picker__lg' => 'hugeicons-favourite',
            'icon_picker__flat' => 'hugeicons-star',
            'icon_picker__soft' => 'hugeicons-flash',
            'icon_picker__faded' => 'hugeicons-favourite',
            'icon_picker__underlined' => 'hugeicons-star',
            'icon_picker__secondary' => 'hugeicons-flash',
            'icon_picker__not_clearable' => 'hugeicons-star',
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Icon picker')
                ->description('Searchable blade-icons picker with lazy SVG rendering, set filters, and paginated server search.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    IconPickerField::make('icon_picker__empty')
                        ->label('Empty (no selection)')
                        ->sets(['hugeicons'])
                        ->helperText('Starts blank — open the panel to load the default catalog. Also useful to verify clear → reopen.')
                        ->columnSpanFull(),
                    IconPickerField::make('icon_picker__heroicon')
                        ->label('Hugeicons · search')
                        ->sets(['hugeicons'])
                        ->helperText('Limits the catalog to the Hugeicons set.')
                        ->columnSpanFull(),
                    IconPickerField::make('icon_picker__gravity')
                        ->label('Hugeicons · filtered catalog')
                        ->sets(['hugeicons'])
                        ->helperText('Limits the catalog to the hugeicons set.')
                        ->columnSpanFull(),
                    IconPickerField::make('icon_picker__multi_set')
                        ->label('Multiple sets (set filter pills)')
                        ->sets(['hugeicons'])
                        ->helperText('Open the dropdown — All sets, Hugeicons filter pills appear above the search field.')
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            IconPickerField::make('icon_picker__grid')
                                ->label('Icon grid')
                                ->searchResultsLayout('icons')
                                ->gridColumns(8)
                                ->sets(['hugeicons'])
                                ->helperText('Dropdown with icon-only grid layout.'),
                            IconPickerField::make('icon_picker__list')
                                ->label('Virtual list · pagination')
                                ->list()
                                ->sets(['hugeicons'])
                                ->perPage(32)
                                ->helperText('List layout shares keyboard and active identity with the grid; SVG batches load on demand.'),
                            IconPickerField::make('icon_picker__label_grid')
                                ->label('Label grid · pagination')
                                ->grid()
                                ->gridColumns(4)
                                ->sets(['hugeicons'])
                                ->perPage(24)
                                ->closeOnSelect(false),
                            IconPickerField::make('icon_picker__preload')
                                ->label('Preloaded icon catalog')
                                ->preload()
                                ->sets(['hugeicons'])
                                ->perPage(32),
                            IconPickerField::make('icon_picker__whitelist')
                                ->label('Whitelisted icons')
                                ->sets(['hugeicons'])
                                ->icons([
                                    'hugeicons-favourite',
                                    'hugeicons-star',
                                    'hugeicons-flash',
                                    'hugeicons-fire',
                                ])
                                ->excludeIcons(['hugeicons-fire'])
                                ->gridColumns(4),
                        ]),
                    Grid::make(['default' => 1, 'sm' => 3])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            IconPickerField::make('icon_picker__sm')
                                ->label('Small')
                                ->sets(['hugeicons'])
                                ->size('sm'),
                            IconPickerField::make('icon_picker__md')
                                ->label('Medium')
                                ->sets(['hugeicons']),
                            IconPickerField::make('icon_picker__lg')
                                ->label('Large')
                                ->sets(['hugeicons'])
                                ->size('lg'),
                        ]),
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            IconPickerField::make('icon_picker__flat')
                                ->label('Flat')
                                ->sets(['hugeicons'])
                                ->variant('flat'),
                            IconPickerField::make('icon_picker__soft')
                                ->label('Soft')
                                ->sets(['hugeicons'])
                                ->variant('soft'),
                            IconPickerField::make('icon_picker__faded')
                                ->label('Faded')
                                ->sets(['hugeicons'])
                                ->variant('faded'),
                            IconPickerField::make('icon_picker__underlined')
                                ->label('Underlined')
                                ->sets(['hugeicons'])
                                ->variant('underlined'),
                        ]),
                    Grid::make(['default' => 1, 'sm' => 2])
                        ->extraAttributes(['class' => 'fff-playground-variants'])
                        ->schema([
                            IconPickerField::make('icon_picker__secondary')
                                ->label('Secondary')
                                ->sets(['hugeicons'])
                                ->variant('secondary'),
                            IconPickerField::make('icon_picker__not_clearable')
                                ->label('Not clearable')
                                ->sets(['hugeicons'])
                                ->clearable(false),
                        ]),
                ]),
        ];
    }
}
