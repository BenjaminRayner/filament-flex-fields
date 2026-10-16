@if (app()->environment(['local', 'testing']))
    <section class="fff-flex-developer-tools" aria-labelledby="flex-developer-tools">
        <div class="fff-flex-developer-tools__intro">
            <x-filament::icon icon="hugeicons-laptop-programming" />
            <div><h2 id="flex-developer-tools">{{ __('filament-flex-fields::catalog.developer_tools') }}</h2><p>{{ __('filament-flex-fields::catalog.developer_tools_description') }}</p></div>
        </div>
        <nav aria-label="{{ __('filament-flex-fields::diagnostics.title') }}">
            <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetFormsPage::getUrl() }}" wire:navigate>{{ __('filament-flex-fields::diagnostics.forms') }}</a>
            <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetTablesPage::getUrl() }}" wire:navigate>{{ __('filament-flex-fields::diagnostics.tables') }}</a>
            <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics\AssetModalsPage::getUrl() }}" wire:navigate>{{ __('filament-flex-fields::diagnostics.modals') }}</a>
        </nav>
    </section>
@endif
