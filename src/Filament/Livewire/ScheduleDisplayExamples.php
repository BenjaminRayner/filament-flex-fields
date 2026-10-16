<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Livewire;

use Bjanczak\FilamentFlexFields\Filament\Infolists\Components\ScheduleEntry;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Catalog-only table and infolist; does not query or persist application records. */
final class ScheduleDisplayExamples extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @return array<string,mixed> */
    public static function sampleSchedule(): array
    {
        return ['timezone' => 'Europe/Warsaw', 'days' => [
            'mon' => ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '12:00']]],
            'wed' => ['enabled' => true, 'slots' => [['from' => '10:00', 'to' => '12:00'], ['from' => '14:00', 'to' => '18:00']]],
            'fri' => ['enabled' => true, 'slots' => [['from' => '07:00', 'to' => '10:00']]],
        ]];
    }

    public function table(Table $table): Table
    {
        return $table->records(fn (): array => [
            ['id' => 1, 'name' => 'Business hours', 'opening_hours' => self::sampleSchedule()],
            ['id' => 2, 'name' => 'Night service', 'opening_hours' => ['timezone' => 'UTC', 'days' => ['sat' => ['enabled' => true, 'slots' => [['from' => '22:00', 'to' => '06:00', 'overnight' => true]]]]]],
            ['id' => 3, 'name' => 'Closed', 'opening_hours' => null],
        ])->paginated(false)->columns([
            TextColumn::make('name')->label('Location'),
            ScheduleColumn::make('opening_hours')->label('Business hours')->criticalStyles(),
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->record(['id' => 1, 'opening_hours' => self::sampleSchedule()])->columns(['default' => 1, 'xl' => 2])->components([
            ScheduleEntry::make('opening_hours')->key('full-week')->label('Full week')->heading('Business hours')
                ->description('Opening hours in the selected timezone.')->criticalStyles(),
            ScheduleEntry::make('opening_hours')->key('active-days')->label('Active days · 12-hour clock')->heading('Opening hours')
                ->hourCycle(12)->showClosedDays(false)->showTimezone(false)->criticalStyles(),
            ScheduleEntry::make('opening_hours')->key('polish-week')->label('Polski · niedziela jako pierwszy dzień')->heading('Godziny otwarcia')
                ->locale('pl')->weekStartsOn(0)->criticalStyles(),
            ScheduleEntry::make('opening_hours')->key('arabic-week')->label('Arabic · RTL')->heading('ساعات العمل')
                ->locale('ar')->weekStartsOn(0)->rtl()->hourCycle(12)->criticalStyles(),
        ]);
    }

    public function render(): View
    {
        abort_unless(FlexFieldsPlaygroundRegistry::isEnabled() && Auth::check(), 403);

        return view('filament-flex-fields::partials.playground.schedule-displays');
    }
}
