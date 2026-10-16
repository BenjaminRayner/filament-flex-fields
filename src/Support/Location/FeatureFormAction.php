<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SwitchField;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Native schema owns its fields, validation and assets; the map owns the resulting history transaction. */
final class FeatureFormAction
{
    public const NAME = 'editMapFeature';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(fn () => Translations::get('filament-flex-fields::location.edit_details'))
            ->modalHeading(fn () => Translations::get('filament-flex-fields::location.edit_details'))
            ->modalWidth(Width::Medium)
            ->modalContent(fn (): View => view('filament-flex-fields::forms.components.partials.location.feature-form-error'))
            ->fillForm(function (array $arguments, MapPickerField $component): array {
                $feature = $component->getEditableFeature((string) ($arguments['id'] ?? ''));

                return [...$feature['properties'], 'color' => $feature['properties']['color'] ?? $component->getMapConfiguration()['color'], 'clickable' => $feature['properties']['clickable'] ?? true];
            })
            ->schema(fn (array $arguments, MapPickerField $component): array => $component->getFeatureFormSchema($component->getEditableFeature((string) ($arguments['id'] ?? ''))))
            ->action(function (array $arguments, array $data, MapPickerField $component): void {
                $feature = $component->getEditableFeature((string) ($arguments['id'] ?? ''));
                // A feature can be moved/deleted by another owner while a modal is open.
                if (! is_array($arguments['before'] ?? null) || $feature != $arguments['before']) {
                    throw ValidationException::withMessages(['feature' => __('filament-flex-fields::location.feature_changed')]);
                }
                try {
                    $patch = $component->normalizeFeatureFormData($data);
                    $collection = $component->normalizeMapGeometry($component->getState());
                    foreach ($collection['features'] as &$candidate) {
                        if ($candidate['id'] === $feature['id']) {
                            $candidate['properties'] = [...$candidate['properties'], ...$patch];
                        }
                    }
                    unset($candidate);
                    $error = $component->getMapValidationMessage($collection, enforceMinimum: false);
                    if ($error !== null) {
                        throw new InvalidArgumentException($error);
                    }
                } catch (InvalidArgumentException $error) {
                    throw ValidationException::withMessages(['feature' => $error->getMessage()]);
                }
                // No server replacement of the collection: the scoped client applies one undoable patch.
                $component->getLivewire()->dispatch('fff-map-feature-form-saved',
                    owner: $component->getKey(), id: $feature['id'], before: $arguments['before'], properties: $patch);
            })
            ->visible(fn (MapPickerField $component): bool => ! $component->isDisabled() && ! $component->isReadOnly() && $component->getMapSelectionMode() !== 'point');
    }

    /** @return list<Component> */
    public static function defaultSchema(): array
    {
        $fields = [FlexTextInput::make('label')->label(fn () => Translations::get('filament-flex-fields::location.label'))->maxLength(2048)];
        foreach (['description', 'detail', 'value', 'tooltip', 'metric'] as $key) {
            $field = $key === 'description' ? FlexTextareaField::make($key)->rows(3) : FlexTextInput::make($key);
            $fields[] = $field->label(fn () => Translations::get('filament-flex-fields::location.'.$key))->maxLength(2048);
        }
        $fields[] = FlexColorPickerField::make('color')->label(fn () => Translations::get('filament-flex-fields::location.color'))->alpha(false)->format('hex')->allowedFormats(['hex'])->required();
        $fields[] = SwitchField::make('clickable')->label(fn () => Translations::get('filament-flex-fields::location.clickable'));

        return $fields;
    }
}
