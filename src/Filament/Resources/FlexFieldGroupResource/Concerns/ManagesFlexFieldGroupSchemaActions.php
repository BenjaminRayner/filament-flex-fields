<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Resources\FlexFieldGroupResource\Concerns;

use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\Enterprise\SchemaRegistry;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldGroupRegistrySync;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaBlueprintPacks;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaImportExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use JsonException;

trait ManagesFlexFieldGroupSchemaActions
{
    use PreparesFlexFieldGroupFormData;

    /**
     * @return list<Action|ActionGroup>
     */
    protected function flexFieldGroupSchemaActions(?FlexFieldGroup $record = null): array
    {
        return [
            ActionGroup::make([
                $this->makePublishSchemaAction(SchemaRegistry::STATE_DRAFT, 'draft')->authorize('publish'),
                $this->makePublishSchemaAction(SchemaRegistry::STATE_REVIEW, 'review')->authorize('publish'),
                $this->makePublishSchemaAction(SchemaRegistry::STATE_LIVE, 'live')->authorize('publish'),
            ])
                ->label(fn () => __('filament-flex-fields::default.schema.publish_group'))
                ->icon('hugeicons-upload-01')
                ->button()
                ->visible($record !== null),
            Action::make('importSchemaJson')
                ->label(fn () => __('filament-flex-fields::default.schema.import_json'))
                ->icon('hugeicons-download-01')
                ->schema([
                    Textarea::make('json')
                        ->label(fn () => __('filament-flex-fields::default.schema.import_json_payload'))
                        ->required()
                        ->rows(12),
                ])
                ->action(function (array $data) use ($record): void {
                    $this->applyImportedSchema($data['json'], $record);
                }),
            Action::make('exportSchemaJson')
                ->label(fn () => __('filament-flex-fields::default.schema.export_json'))
                ->icon('hugeicons-upload-01')
                ->visible($record !== null)
                ->action(function () use ($record) {
                    $payload = app(SchemaImportExport::class)->export($record->toRegistrySchema());
                    $filename = Str::slug($record->slug).'-schema.json';

                    return Response::streamDownload(
                        static function () use ($payload): void {
                            echo $payload;
                        },
                        $filename,
                        ['Content-Type' => 'application/json'],
                    );
                }),
            Action::make('applyBlueprintPack')
                ->label(fn () => __('filament-flex-fields::default.schema.apply_blueprint'))
                ->icon('hugeicons-sparkles')
                ->schema([
                    Select::make('pack')
                        ->label(fn () => __('filament-flex-fields::default.schema.blueprint_select'))
                        ->options(collect(SchemaBlueprintPacks::names())
                            ->mapWithKeys(fn (string $name): array => [$name => Str::headline($name)])
                            ->all())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data) use ($record): void {
                    $pack = SchemaBlueprintPacks::pack((string) $data['pack']);

                    if ($pack === null) {
                        Notification::make()
                            ->title(fn () => __('filament-flex-fields::default.schema.blueprint_not_found'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->applyBlueprintPack($pack, $record);
                }),
        ];
    }

    protected function makePublishSchemaAction(string $state, string $actionKey): Action
    {
        return Action::make("publishSchema{$actionKey}")
            ->label(fn () => __('filament-flex-fields::default.schema.publish_'.$actionKey))
            ->requiresConfirmation()
            ->modalHeading(fn () => __('filament-flex-fields::default.schema.publish_'.$actionKey))
            ->modalDescription(fn () => __('filament-flex-fields::default.schema.publish_'.$actionKey.'_help'))
            ->action(function () use ($state): void {
                /** @var FlexFieldGroup $record */
                $record = $this->getRecord();

                $user = auth()->user();
                $actor = is_object($user) && isset($user->email) ? (string) $user->email : (string) auth()->id();

                $version = $record->publishToRegistry($actor, $state);

                if ($state === SchemaRegistry::STATE_LIVE) {
                    app(FlexFieldGroupRegistrySync::class)->syncGroup($record->fresh());
                }

                Notification::make()
                    ->title(fn () => __('filament-flex-fields::default.schema.publish_version_success', ['version' => $version]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $pack
     */
    protected function applyBlueprintPack(array $pack, ?FlexFieldGroup $record = null): void
    {
        $fields = $pack['fields'] ?? [];

        $formData = [
            'name' => $pack['label'] ?? ($record?->name ?? 'Blueprint group'),
            'slug' => $record?->slug ?? Str::slug((string) ($pack['key'] ?? 'blueprint')),
            'target_type' => $pack['target'] ?? ($record?->target_type ?? config('filament-flex-fields.schema.default_target_type')),
            'sections' => is_array($pack['sections'] ?? null) ? array_values($pack['sections']) : [],
            'fields' => is_array($fields) ? array_values($fields) : [],
        ];

        if ($record !== null) {
            $record->fill($formData)->save();
            $this->fillForm($this->prepareFlexFieldGroupFormData($record->fresh()->toArray()));

            Notification::make()
                ->title(fn () => __('filament-flex-fields::default.schema.blueprint_applied'))
                ->success()
                ->send();

            return;
        }

        $this->form->fill($this->prepareFlexFieldGroupFormData(array_merge($this->form->getState(), $formData)));

        Notification::make()
            ->title(fn () => __('filament-flex-fields::default.schema.blueprint_applied'))
            ->success()
            ->send();
    }

    protected function applyImportedSchema(string $json, ?FlexFieldGroup $record = null): void
    {
        try {
            $schema = app(SchemaImportExport::class)->import($json);
        } catch (JsonException $exception) {
            Notification::make()
                ->title(fn () => __('filament-flex-fields::default.schema.import_json_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $validation = app(SchemaImportExport::class)->dryRunValidate($schema);

        if (! $validation['ok']) {
            Notification::make()
                ->title(fn () => __('filament-flex-fields::default.schema.import_json_failed'))
                ->body(implode("\n", $validation['errors']))
                ->danger()
                ->send();

            return;
        }

        $schemaKey = (string) ($schema['key'] ?? ($record?->slug ?? 'imported-group'));
        $slug = $schemaKey;

        if (str_contains($schemaKey, ':')) {
            [, $slug] = explode(':', $schemaKey, 2);
        }

        $formData = [
            'name' => $schema['label'] ?? ($record?->name ?? 'Imported group'),
            'slug' => $slug,
            'target_type' => $schema['target'] ?? ($record?->target_type ?? config('filament-flex-fields.schema.default_target_type')),
            'sections' => array_values($schema['sections'] ?? []),
            'fields' => array_values($schema['fields'] ?? []),
        ];

        if ($record !== null) {
            $record->fill($formData)->save();
            $this->fillForm($this->prepareFlexFieldGroupFormData($record->fresh()->toArray()));
        } else {
            $this->form->fill($this->prepareFlexFieldGroupFormData(array_merge($this->form->getState(), $formData)));
        }

        Notification::make()
            ->title(fn () => __('filament-flex-fields::default.schema.import_json_success'))
            ->success()
            ->send();
    }
}
