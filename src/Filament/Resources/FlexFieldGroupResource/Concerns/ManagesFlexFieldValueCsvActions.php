<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Resources\FlexFieldGroupResource\Concerns;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldValueCsvExchange;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

trait ManagesFlexFieldValueCsvActions
{
    protected function flexFieldValueCsvActions(?FlexFieldGroup $record = null): array
    {
        return [
            Action::make('exportValuesCsvTemplate')
                ->label(fn () => __('filament-flex-fields::default.schema.export_csv_values'))
                ->icon('hugeicons-table-01')
                ->visible($record !== null)
                ->action(function () use ($record) {
                    $definitions = $this->definitionsFromGroup($record);
                    $csv = app(FlexFieldValueCsvExchange::class)->export($definitions, []);
                    $filename = Str::slug($record->slug).'-values-template.csv';

                    return Response::streamDownload(
                        static fn (): string => print ($csv),
                        $filename,
                        ['Content-Type' => 'text/csv'],
                    );
                }),
            Action::make('importValuesCsvDryRun')
                ->label(fn () => __('filament-flex-fields::default.schema.import_csv_values'))
                ->icon('hugeicons-upload-01')
                ->visible($record !== null)
                ->schema([
                    Textarea::make('csv')
                        ->label(fn () => __('filament-flex-fields::default.schema.import_csv_values'))
                        ->helperText(fn () => __('filament-flex-fields::default.schema.import_csv_values_help'))
                        ->required()
                        ->rows(10),
                ])
                ->action(function (array $data) use ($record): void {
                    $definitions = $this->definitionsFromGroup($record);
                    $imported = app(FlexFieldValueCsvExchange::class)->import((string) $data['csv'], $definitions);

                    Notification::make()
                        ->title(fn () => __('filament-flex-fields::default.schema.import_csv_values'))
                        ->body(sprintf('Parsed %d record row(s).', count($imported)))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return list<FlexFieldDefinition>
     */
    private function definitionsFromGroup(FlexFieldGroup $group): array
    {
        return collect($group->fields ?? [])
            ->filter(fn ($field): bool => is_array($field) && filled($field['slug'] ?? null))
            ->map(fn (array $field): FlexFieldDefinition => FlexFieldDefinition::fromArray($field))
            ->values()
            ->all();
    }
}
