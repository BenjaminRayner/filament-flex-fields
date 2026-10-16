<div class="fff-flex-detail__navigation">
<nav class="fff-flex-detail__breadcrumb" aria-label="{{ __('filament-flex-fields::catalog.breadcrumb') }}">
    <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage::getUrl() }}" wire:navigate>FLEX</a>
    <x-filament::icon icon="hugeicons-arrow-right-01" />
    <span class="fff-flex-detail__breadcrumb-current" aria-current="page">{{ $entry['label'] }}</span>
</nav>
@include('filament-flex-fields::catalog.theme-switch')
</div>
{{-- Entry metadata and action descriptors are immutable for this mounted page. --}}
<header class="fff-flex-detail__header" wire:ignore>
        <div class="fff-flex-detail__art" aria-hidden="true">
            @if ($entry['image']) <img src="{{ $entry['image'] }}" alt="" decoding="async" />
            @else <x-filament::icon :icon="$entry['icon']" /> @endif
        </div>
    <div class="fff-flex-detail__identity">
        <h1 class="fff-flex-detail__title">{{ $entry['label'] }}</h1>
        <div class="fff-flex-detail__author">{{ $entry['author'] }}
            @if ($entry['official']) @include('filament-flex-fields::catalog.verified') @endif
        </div>
    </div>
    <div class="fff-flex-detail__actions">
    <button type="button" class="fff-recording-launch" x-data="{}" x-on:click="$el.closest('.fff-flex-detail').setAttribute('data-fff-recording-requested', ''); $dispatch('fff-recording-choose')"><x-filament::icon icon="hugeicons-video-01" />{{ __('filament-flex-fields::catalog.recording.title') }}</button>
    @if (!empty($demoActions)) @include('filament-flex-fields::catalog.detail-actions') @endif
    @if ($entry['repository'])
        <a class="fff-flex-detail__action" href="{{ $entry['repository'] }}" target="_blank" rel="noopener noreferrer">
            <x-filament::icon :icon="$entry['installed'] ? 'hugeicons-arrow-up-right-01' : 'hugeicons-add-01'" />
            {{ __('filament-flex-fields::catalog.'.($entry['installed'] ? 'github' : 'install')) }}
        </a>
    @elseif ($entry['installed'])
        <span class="fff-flex-detail__action fff-flex-detail__action--installed" role="status"><x-filament::icon icon="hugeicons-tick-02" />{{ __('filament-flex-fields::catalog.'.($entry['official'] ? 'included' : 'installed')) }}</span>
    @endif
    </div>
</header>
@if ($entry['heroImage']) <img class="fff-flex-detail__hero" src="{{ $entry['heroImage'] }}" alt="{{ $entry['label'] }}" width="720" height="336" decoding="async" /> @endif
<p class="fff-flex-detail__description">{{ $entry['description'] }}</p>
