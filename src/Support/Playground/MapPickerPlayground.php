<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Enums\MapboxSearchType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapCoordinatesPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapDisplayPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapExampleData;
use Bjanczak\FilamentFlexFields\Support\Playground\Location\MapExperiencesPlayground;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class MapPickerPlayground
{
    private const string SPLIT_PREFIX = 'map_picker__split';

    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        $split = [
            'lat' => 52.2297,
            'lng' => 21.0122,
            'street' => '1 plac Defilad',
            'city' => 'Warszawa',
            'region' => 'mazowieckie',
            'postcode' => '00-901',
            'country' => 'PL',
            'country_name' => 'Polska',
            'place_name' => '1 plac Defilad, 00-901 Warszawa, Polska',
        ];

        return [
            ...app(MapCoordinatesPlayground::class)->defaultState(),
            ...app(MapDisplayPlayground::class)->defaultState(),
            'map_picker__full' => [
                'lat' => 52.2297,
                'lng' => 21.0122,
                'street' => '1 plac Defilad',
                'city' => 'Warszawa',
                'postcode' => '00-901',
                'country' => 'PL',
                'country_name' => 'Polska',
                'place_name' => '1 plac Defilad, 00-901 Warszawa, Polska',
            ],
            'map_picker__split' => $split,
            'map_picker__split_street' => $split['street'],
            'map_picker__split_city' => $split['city'],
            'map_picker__split_region' => $split['region'],
            'map_picker__split_postcode' => $split['postcode'],
            'map_picker__split_country' => $split['country'],
            'map_picker__split_country_name' => $split['country_name'],
            'map_picker__split_lat' => (string) $split['lat'],
            'map_picker__split_lng' => (string) $split['lng'],
            'map_picker__city_only' => 'Kraków, Polska',
            'map_picker__venue' => 'Marszałkowska 1, Warszawa (PL)',
            'map_picker__poi' => [
                'lat' => 50.0547,
                'lng' => 19.9354,
                'place_name' => 'Wawel Royal Castle, Kraków, Poland',
            ],
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            ...app(MapExperiencesPlayground::class)->components(),
            ...$this->pointComponents(),
            Section::make('Coordinates')->schema(app(MapCoordinatesPlayground::class)->components()),
            Section::make('Map display')->schema(app(MapDisplayPlayground::class)->components()),
        ];
    }

    /** @return list<Component> */
    private function pointComponents(): array
    {
        return [
            Section::make('Structured pickup')
                ->description('Full street address with coordinates. Search, click the map, or drag the point — state is one structured array.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    MapPickerField::make('map_picker__full')
                        ->label('Pickup location')
                        ->helperText('fields([lat, lng, street, city, postcode, country, country_name, place_name]) · requireStreetNumber() · countries([PL]).')
                        ->fields(['lat', 'lng', 'street', 'city', 'postcode', 'country', 'country_name', 'place_name'])
                        ->storeFormat(MapPickerField::STORE_STRUCTURED)
                        ->requiredFields(['city', 'lat', 'lng'])
                        ->requireStreetNumber()
                        ->defaultCenter([52.2297, 21.0122])
                        ->scrollZoom()->cooperativeGestures(false)
                        ->defaultZoom(12)
                        ->countries(['PL'])
                        ->columnSpanFull(),
                ]),

            Section::make('Split address form')
                ->description('Map picker drives separate inputs (street, city, postcode, country, coordinates). Typical for checkout, CRM, or legacy schemas that expect flat columns.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    MapPickerField::make('map_picker__split')
                        ->label('Find on map')
                        ->helperText('Select on the map or via search — mirrored fields below update instantly via ->live()->afterStateUpdated().')
                        ->fields(['lat', 'lng', 'street', 'city', 'region', 'postcode', 'country', 'country_name', 'place_name'])
                        ->storeFormat(MapPickerField::STORE_STRUCTURED)
                        ->streetAddressesOnly()
                        ->defaultCenter([52.2297, 21.0122])
                        ->scrollZoom()->cooperativeGestures(false)
                        ->defaultZoom(13)
                        ->countries(['PL'])
                        ->live()
                        ->afterStateUpdated($this->syncSplitAddressFields(...))
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->schema($this->splitAddressMirrorFields()),
                ]),

            Section::make('String output formats')
                ->description('Dehydrate as a single formatted string instead of a structured array.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            MapPickerField::make('map_picker__city_only')
                                ->label('City only')
                                ->helperText('searchTypes([place]) · stringFormat("{city}, {country_name}").')
                                ->fields(['city', 'country', 'country_name', 'place_name'])
                                ->searchTypes([MapboxSearchType::Place])
                                ->storeFormat(MapPickerField::STORE_STRING)
                                ->stringFormat('{city}, {country_name}')
                                ->requiredFields(['city'])
                                ->defaultCenter([50.0647, 19.9450])
                                ->scrollZoom()->cooperativeGestures(false)
                                ->defaultZoom(11),
                            MapPickerField::make('map_picker__venue')
                                ->label('Venue line')
                                ->helperText('Full address as one string: "{street}, {city} ({country})".')
                                ->fields(['street', 'city', 'country', 'place_name', 'lat', 'lng'])
                                ->storeFormat(MapPickerField::STORE_STRING)
                                ->stringFormat('{street}, {city} ({country})')
                                ->streetAddressesOnly()
                                ->defaultCenter([52.2297, 21.0122])
                                ->scrollZoom()->cooperativeGestures(false)
                                ->defaultZoom(14)
                                ->countries(['PL']),
                        ]),
                ]),

            Section::make('Search scopes')
                ->description('Limit geocoding results to POIs or broader place types.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    MapPickerField::make('map_picker__poi')
                        ->label('Pickup point (POI)')
                        ->helperText('searchTypes([poi]) · stores place_name + coordinates only.')
                        ->searchTypes([MapboxSearchType::Poi])
                        ->fields(['place_name', 'lat', 'lng'])
                        ->requiredFields(['lat', 'lng', 'place_name'])
                        ->defaultCenter([50.0647, 19.9450])
                        ->scrollZoom()->cooperativeGestures(false)
                        ->defaultZoom(14)
                        ->countries(['PL'])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function zones(): array
    {
        return MapExampleData::zones();
    }

    /** @return array<string, mixed> */
    public static function markers(): array
    {
        return MapExampleData::markers();
    }

    /** @param array<string, mixed>|null $state */
    public function syncSplitAddressFields(?array $state, Set $set): void
    {
        $fields = [
            'street' => '',
            'city' => '',
            'region' => '',
            'postcode' => '',
            'country' => '',
            'country_name' => '',
            'lat' => '',
            'lng' => '',
        ];

        foreach ($fields as $key => $fallback) {
            $value = data_get($state, $key, $fallback);

            if (is_float($value) || is_int($value)) {
                $value = (string) $value;
            }

            $set(self::SPLIT_PREFIX.'_'.$key, is_string($value) ? $value : (string) ($value ?? ''));
        }
    }

    /**
     * @return list<Component>
     */
    private function splitAddressMirrorFields(): array
    {
        $prefix = self::SPLIT_PREFIX;

        return [
            FlexTextInput::make("{$prefix}_street")
                ->label('Street')
                ->helperText('Filled from geocoding · street')
                ->disabled()
                ->dehydrated(false)
                ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 3]),
            FlexTextInput::make("{$prefix}_city")
                ->label('City')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_postcode")
                ->label('Postcode')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_region")
                ->label('Region')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_country")
                ->label('Country code')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_country_name")
                ->label('Country')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_lat")
                ->label('Latitude')
                ->disabled()
                ->dehydrated(false),
            FlexTextInput::make("{$prefix}_lng")
                ->label('Longitude')
                ->disabled()
                ->dehydrated(false),
        ];
    }
}
