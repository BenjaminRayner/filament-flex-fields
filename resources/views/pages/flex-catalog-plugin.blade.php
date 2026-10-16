<x-filament-panels::page>
    @php($entry = $this->catalogEntry())
    <div class="fff-flex-detail">
        <x-filament-flex-fields::assets component="flex-catalog-layout" consumer="flex.extension" :critical="true" />
        @include('filament-flex-fields::catalog.detail-header')
        <p class="fff-flex-detail__notice">{{ __('filament-flex-fields::catalog.'.($entry['type'] === 'plugin' ? 'extension_notice' : 'component_notice')) }}</p>
        {{ $this->form }}
        @include('filament-flex-fields::catalog.footer')
    </div>
</x-filament-panels::page>
