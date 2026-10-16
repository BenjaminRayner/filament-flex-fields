---
title: "Map feature forms & actions"
description: Add custom metadata, native Filament actions and PHP callbacks to markers, zones and mixed map features.
icon: "list-plus"
---

The collection components [MapMarkersField](/docs/map-markers), [MapZonesField](/docs/map-zones) and [MapEditorField](/docs/map-editor) share these APIs. They customize each feature inside a map field; saving geometry in the parent model still follows the normal form lifecycle.

## Native feature forms, actions and events

Markers, zones and mixed-editor fields accept actual Filament schemas. **Edit
details** opens a native modal with normal validation and on-demand field assets.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\{
    MapMarkersField, FlexTextInput, FlexTextareaField, SelectField,
};
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;

MapMarkersField::make('stops')
    ->featurePropertyKeys(['status', 'contacts'])
    ->quickFeatureEditing(false)
    ->featureSchema(fn (array $feature): array => [
        Section::make('Details')->schema([
            FlexTextInput::make('label')->required()->maxLength(120),
            FlexTextareaField::make('description')->rows(3),
            SelectField::make('status')->options([
                'planned' => 'Planned', 'active' => 'Active',
            ]),
            Repeater::make('contacts')->schema([
                FlexTextInput::make('email')->email()->required(),
            ])->maxItems(10),
        ]),
    ]);
```

The closure receives `feature`, `properties`, `geometry` and standard Filament
dependencies. Different geometry can use different schemas. Extend the supplied
form with `FeatureFormAction::defaultSchema()`. Omitted fields keep their current
properties. Declared custom values support bounded JSON, including multiple
selection/repeater data.
Built-in popup text remains plain text. The arbitrary schema is a modal form;
the optional inline quick editor is intentionally small.

```php
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

MapMarkersField::make('stops')
    ->modifyFeatureActionUsing(fn (Action $action) => $action
        ->modalWidth(Width::Large)->slideOver())
    ->featureActions([
        Action::make('inspectStop')->label('Inspect')->requiresConfirmation()
            ->action(function (array $arguments, MapMarkersField $component): void {
                $feature = $component->getEditableFeature((string) $arguments['id']);
                app(\App\Services\DeliveryStops::class)->inspect($feature);
            }),
    ])
    ->afterFeatureAdded(function (array $feature, $record): void {
        app(\App\Services\DeliveryStops::class)->added($record, $feature);
    })
    ->afterFeatureUpdated(function (array $feature, array $previousFeature, $record): void {
        app(\App\Services\DeliveryStops::class)->updated($record, $feature, $previousFeature);
    })
    ->afterFeatureDeleted(function (array $feature, $record): void {
        app(\App\Services\DeliveryStops::class)->deleted($record, $feature);
    });
```

`DeliveryStops` is an illustrative application service, not a package class. It
owns authorization, transactions and idempotency. In Create, `record` may be null;
defer parent-dependent persistence or maintain pending data. Native actions expose
authorization, confirmation and form configuration. Resolve/authorize their target
on the server; browser snapshots are not authority.

Registering a PHP callback enables live, renderless state synchronization. Identical
state and drag previews do not invoke callbacks. Batched requests observe accepted
server differences; undo/redo/clear can invoke callbacks. Exactly-once database
execution is not promised. No database writes happen without your callback/action.

Client UI integrations can listen for bubbling `fff-map-feature-added`,
`fff-map-feature-updated`, `fff-map-feature-deleted`. Detail includes `feature`,
`previousFeature`, `statePath`, and `origin` (`edit` or `history`). Database side
effects belong to validated PHP code.
