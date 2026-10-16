<x-filament-panels::page>
    <div class="fff-asset-lab">
        <x-filament-flex-fields::assets component="asset-diagnostics" consumer="diagnostics.page" :critical="true" />
        <header class="fff-asset-lab__header">
            <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage::getUrl() }}">← FLEX</a>
            <h1>{{ __('filament-flex-fields::diagnostics.title') }}</h1>
            <p>{{ __('filament-flex-fields::diagnostics.description') }}</p>
        </header>
        <nav class="fff-asset-lab__navigation" aria-label="{{ __('filament-flex-fields::diagnostics.title') }}">
            @foreach ($this->diagnosticsPages() as $page)
                <a href="{{ $page['url'] }}" wire:navigate @if ($page['active']) aria-current="page" @endif>{{ $page['label'] }}</a>
            @endforeach
        </nav>
        <div class="fff-asset-lab__workspace">
            <main class="fff-asset-lab__content">
                {{ $this->form }}
                @if ($this->scenario() === 'tables' && $this->showTable)
                    <div wire:key="diagnostics-table">{{ $this->table }}</div>
                @endif
            </main>
            @include('filament-flex-fields::diagnostics.inspector')
        </div>
    </div>
</x-filament-panels::page>
