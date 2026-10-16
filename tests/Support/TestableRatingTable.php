<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\RatingColumn;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class TestableRatingTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table->records(fn (): array => [
            'one' => ['score' => 3.7, 'other' => 4],
            'two' => ['score' => 3.7, 'other' => 2.3],
        ])->columns([
            RatingColumn::make('score')->label('Score'),
            RatingColumn::make('other')->label('Other')->ratingSize('sm'),
        ])->paginated(false);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}
