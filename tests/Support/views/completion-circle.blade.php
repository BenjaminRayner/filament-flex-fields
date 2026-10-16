@php
    $circle = \Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ProgressCircle::make()
        ->key('completion-circle-'.data_get($getRecord(), 'id'))
        ->value((float) ($getState() ?? 0))
        ->max(100)
        ->size('xs')
        ->color('success')
        ->extraAttributes(['aria-label' => 'Completion']);
@endphp

{{ \Filament\Schemas\Schema::make($getLivewire())->components([$circle]) }}
