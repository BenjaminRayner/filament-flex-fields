<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics;

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ProgressColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\StatusChipColumn;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

final class AssetTablesPage extends AssetDiagnosticsPage implements HasTable
{
    use InteractsWithTable;

    public const SCENARIO = 'tables';

    protected static ?string $slug = 'asset-diagnostics-tables';

    public bool $showTable = true;

    public int $rowCount = 50;

    public function scenario(): string
    {
        return self::SCENARIO;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([$this->sharedSelect(), Actions::make([
            Action::make('toggleTable')->label(fn () => Translations::get('filament-flex-fields::diagnostics.toggle_table'))->action(function (): void {
                $this->showTable = ! $this->showTable;
            }),
            Action::make('toggleRows')->label(fn () => Translations::get('filament-flex-fields::diagnostics.toggle_rows'))->action(function (): void {
                $this->rowCount = $this->rowCount === 50 ? 1 : 50;
                $this->resetTable();
            }),
        ])]);
    }

    public function table(Table $table): Table
    {
        return $table->records(fn (): array => array_map(fn (int $id): array => [
            'id' => $id, 'name' => 'FLEX '.$id, 'status' => $id % 2 ? 'Active' : 'Draft', 'progress' => ($id * 7) % 100, 'rating' => 1 + ($id % 5),
        ], range(1, $this->rowCount)))->paginated(false)->columns([
            TextColumn::make('id')->label('#'), TextColumn::make('name')->label('Name'),
            StatusChipColumn::make('status')->chipColor('success')->criticalStyles(),
            ProgressColumn::make('progress')->criticalStyles()->toggleable(),
            RatingColumn::make('rating')->ratingIcon('hugeicons-star')->criticalStyles()->toggleable(),
        ])->recordActions([$this->nestedModal()]);
    }
}
