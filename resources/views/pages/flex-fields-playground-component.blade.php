<x-filament-panels::page>
    @php($entry = $this->catalogEntry())
    <div class="fff-flex-detail">
        <x-filament-flex-fields::assets component="flex-catalog-layout" consumer="flex.detail" :critical="true" />
        @include('filament-flex-fields::catalog.detail-header', ['demoActions' => $this->getCachedHeaderActions()])
        <form class="fi-form grid gap-y-6" wire:submit.prevent="validateForm">{{ $this->form }}</form>
        @include('filament-flex-fields::catalog.footer')
    </div>
</x-filament-panels::page>
