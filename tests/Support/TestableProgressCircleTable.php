<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class TestableProgressCircleTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table->records(fn (): array => [
            'one' => ['id' => 1, 'completion' => 34],
            'two' => ['id' => 2, 'completion' => 100],
        ])->columns([
            ViewColumn::make('completion')->view('progress-circle-test::completion-circle'),
        ])->paginated(false);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}
