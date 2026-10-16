<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Support\Schedule\SchedulePolicy;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

// Reuse the real Laravel/Livewire/Filament bootstrap rather than a mock field lifecycle.
require __DIR__.'/schema-render-contract.php';
class RebuiltScheduleProbe extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            ScheduleField::make('schedule')->days(['mon', 'tue'])->timezone('Europe/Warsaw')->maxSlots(3),
            CountryMapField::make('map')->countries(['PL', 'US'])->countryGroups(['Team' => ['PL', 'US']])->default(['PL']),
        ]);
    }
}
function verify(bool $condition, string $name): void
{
    if (! $condition) {
        throw new RuntimeException($name);
    }
}
function invalid(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Invalid schedule configuration accepted.');
}
$host = new RebuiltScheduleProbe;
$host->setId('schedule-country-probe');
$host->form->fill();
verify($host->data['schedule']['timezone'] === 'Europe/Warsaw', 'Configured timezone default');
verify(array_keys($host->data['schedule']['days']) === ['mon', 'tue'], 'Configured days default');
$markup = $host->form->toHtml();
verify(str_contains($markup, 'fff-select-control') && str_contains($markup, 'countryMapFormComponent'), 'Real composed render');
verify(! str_contains($markup, '<x-filament-flex-fields::'), 'Every embedded Blade component compiles');
verify(substr_count($markup, 'fff-select-control__trigger') >= 9, 'Timezone and static/dynamic time controls render');
verify(! str_contains($markup, 'flexTimeSegmentsSrc') && ! str_contains($markup, 'flexTimeSegmentsComponent'), 'No legacy time renderer');
verify(str_contains($markup, 'country-map__group-count'), 'Map shared leading group render');
$state = $host->form->getState();
verify($state['map'] === ['PL'], 'Map validated state');
$host->data['schedule']['days']['mon']['slots'] = [];
try {
    $host->form->getState();
    throw new RuntimeException('Enabled day with zero slots bypassed optional validation');
} catch (ValidationException $error) {
    verify(isset($error->errors()['data.schedule']), 'Enabled empty day field error');
}
$hidden = (new ScheduleField('hidden'))->timezone(null);
verify(! isset($hidden->normalizeState(['timezone' => 'UTC'])['timezone']), 'Hidden timezone excluded from state');
$normalized = $hidden->normalizeState(null);
verify($hidden->stateMatches($normalized, $normalized) && ! $hidden->stateMatches($normalized, []), 'Hydration compares actual state');
invalid(fn () => (new ScheduleField('bad'))->minSlots(3)->maxSlots(2)->getMaxSlots());
invalid(fn () => SchedulePolicy::slots(101, 'maxSlots', 1));
invalid(fn () => SchedulePolicy::times(0));
invalid(fn () => (new ScheduleField('bad'))->days(fn () => ['mon', []])->getDays());
echo "PASS: actual composed Laravel render/defaults, hidden timezone removal, hydration, strict limits and optional enabled-empty-day server validation.\n";
