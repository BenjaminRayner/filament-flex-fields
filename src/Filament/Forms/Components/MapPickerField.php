<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\InteractsWithGeocodedAddress;
use Bjanczak\FilamentFlexFields\Concerns\Location\ComposesLocationSearch;
use Bjanczak\FilamentFlexFields\Concerns\Location\ConfiguresFeatureEditor;
use Bjanczak\FilamentFlexFields\Concerns\Location\ConfiguresLocationMap;
use Bjanczak\FilamentFlexFields\Concerns\Location\ConfiguresMapExperiences;
use Bjanczak\FilamentFlexFields\Concerns\Location\InteractsWithFeatureEvents;
use Bjanczak\FilamentFlexFields\Concerns\Location\InteractsWithMapGeometry;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker\ConfiguresMapPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker\NormalizesMapPickerState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\MapPicker\PresentsMapPicker;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

class MapPickerField extends Field
{
    use CanBeReadOnly;
    use ComposesLocationSearch;
    use ConfiguresFeatureEditor;
    use ConfiguresLocationMap;
    use ConfiguresMapExperiences;
    use ConfiguresMapPicker;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use InteractsWithFeatureEvents;
    use InteractsWithGeocodedAddress;
    use InteractsWithMapGeometry;
    use NormalizesMapPickerState;
    use PresentsMapPicker {
        PresentsMapPicker::defaultFocusOutline insteadof HasFieldFocusOutline;
    }

    /**
     * @var list<string>
     */
    public const array ALL_FIELDS = ['lat', 'lng', 'street', 'city', 'region', 'postcode', 'country', 'country_name', 'place_name'];

    protected string $view = 'filament-flex-fields::forms.components.map-picker-field';

    public function getLocationComponentId(): string
    {
        return 'map-picker';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->geocodedAddressFields = ['lat', 'lng', 'street', 'city', 'postcode', 'country', 'place_name'];
        $this->setUpMapGeometry();
        $this->setUpFeatureEvents();
        $this->rule(function (self $component): \Closure {
            return function (string $attribute, mixed $value, \Closure $fail) use ($component): void {
                try {
                    $point = $component->getMapSelectionMode() === 'point';
                    if (! $point) {
                        if ($value !== null && ! is_array($value)) {
                            throw new \InvalidArgumentException('Expected a feature collection.');
                        }
                        $message = $component->getMapValidationMessage($value ?? []);
                    } else {
                        $state = $component->hydrateToCanonical($value);
                        $hasValue = $component->hasAnyStoredValue($state);
                        if ($component->isRequired() && ! $hasValue) {
                            $fail(__('validation.required', ['attribute' => $component->getLabel()]));

                            return;
                        }
                        if (! $hasValue) {
                            return;
                        }
                        $message = $component->getValidationMessage($state) ?? $component->getMapValidationMessage($state);
                    }
                    if ($message !== null) {
                        $fail($message);
                    }
                } catch (\InvalidArgumentException) {
                    $fail(__('filament-flex-fields::location.validation.invalid_geometry'));
                }
            };
        });
    }
}
