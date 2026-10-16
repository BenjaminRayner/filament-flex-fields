<x-filament-panels::page>
    @php
        $catalog = $this->catalogData();
        $searchIndex = array_map(static fn (array $entry): array => [
            'id' => $entry['id'], 'categories' => $entry['categories'], 'kinds' => $entry['kinds'], 'type' => $entry['type'], 'installed' => $entry['installed'], 'official' => $entry['official'],
            'search' => implode(' ', [$entry['label'], $entry['description'], $entry['author'], $entry['categoryLabel'], ...$entry['keywords']]),
        ], $catalog['entries']);
        $kindOptions = array_map(static fn (string $kind): array => [
            'value' => $kind, 'label' => __('filament-flex-fields::catalog.component_types.'.$kind),
        ], ['all', ...$catalog['componentKinds']]);
    @endphp
    <div class="fff-flex-catalog" x-fff-load x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('flex-catalog') }}" x-data="flexCatalogComponent({ entries: @js($searchIndex) })" x-on:fff-catalog-kind="componentKind = $event.detail.kind">
        <x-filament-flex-fields::assets component="flex-catalog" consumer="flex.catalog" />
        <header class="fff-flex-catalog__top">
            <h1 class="fff-flex-catalog__brand">
                @svg('flexbrand-logo', 'fff-flex-catalog__logo', ['aria-hidden' => 'true'])
                <span class="fff-flex-sr-only">FLEX</span>
            </h1>
            <div class="fff-flex-catalog__top-actions"><span class="fff-flex-catalog__summary">{{ __('filament-flex-fields::catalog.count', ['count' => count($catalog['entries'])]) }}</span>@include('filament-flex-fields::catalog.theme-switch')</div>
        </header>
        <div class="fff-flex-promotions" @if ($catalog['promotionsRefreshDue']) wire:init="loadPromotions" @endif>
            @include('filament-flex-fields::catalog.banner')
            @if ($catalog['promotionsRefreshDue'])
                <div class="fff-flex-promotions__loader" role="status" wire:loading.delay wire:target="loadPromotions"><span class="fff-flex-sr-only">{{ __('filament-flex-fields::catalog.loading_promotions') }}</span></div>
            @endif
        </div>
        @include('filament-flex-fields::catalog.sponsors')
        <span data-flex-search-sentinel aria-hidden="true"></span>
        <div class="fff-flex-catalog__search" x-bind:class="{ 'is-stuck': searchStuck }">
            <x-filament-flex-fields::location-input type="search" size="lg" variant="primary" prefix-icon="hugeicons-search-01" maxlength="256" :placeholder="__('filament-flex-fields::catalog.search')" :aria-label="__('filament-flex-fields::catalog.search')" x-model.debounce.80ms="query" />
        </div>
        <div class="fff-flex-catalog__filters">
            <div class="fff-flex-catalog__collections" role="group" aria-label="{{ __('filament-flex-fields::catalog.collection') }}">
                @foreach (['all' => 'all', 'installed' => 'installed', 'community' => 'community'] as $value => $translation)
                    <button type="button" class="fff-flex-filter {{ $value === 'all' ? 'is-active' : '' }}" aria-pressed="{{ $value === 'all' ? 'true' : 'false' }}" x-bind:class="{ 'is-active': collection === @js($value) }" x-bind:aria-pressed="collection === @js($value)" x-on:click="collection = @js($value)">{{ __('filament-flex-fields::catalog.'.$translation) }}</button>
                @endforeach
            </div>
            <span class="fff-flex-catalog__summary fff-flex-catalog__result-count" x-show="isFiltering" style="display:none" aria-live="polite"><span x-text="resultCount"></span> {{ __('filament-flex-fields::catalog.results') }}</span>
            <div class="fff-flex-catalog__type-filter" wire:ignore wire:key="flex-catalog-type-filter.{{ hash('xxh128', serialize($kindOptions)) }}">
                <x-filament-flex-fields::select-control :options="$kindOptions" state-expression="componentKind" control-id="flex-catalog-type-filter"
                    on-change="values => $dispatch('fff-catalog-kind', { kind: values[0] ?? 'all' })"
                    :label="__('filament-flex-fields::catalog.component_type')" :dropdown-min-width="220" :match-trigger-width="false">
                    <x-slot name="trigger">
                        <button type="button" class="fff-flex-filter fff-flex-catalog__type-trigger" role="combobox"
                            x-ref="headlessTrigger" data-fff-open-trigger aria-haspopup="listbox" aria-expanded="false"
                            aria-label="{{ __('filament-flex-fields::catalog.component_type') }}"
                            x-bind:aria-label="@js(__('filament-flex-fields::catalog.component_type')) + ': ' + triggerLabelHtml()"
                            x-bind:aria-controls="menuDomId + '-listbox'" x-bind:aria-expanded="comboboxOpen"
                            x-bind:aria-activedescendant="headlessActiveDescendant()" x-bind:disabled="disabled"
                            x-bind:class="{ 'is-active': componentKind !== 'all' }"
                            x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event)">
                            <x-filament::icon icon="hugeicons-filter-horizontal" />
                            <span x-text="triggerLabelHtml()">{{ __('filament-flex-fields::catalog.component_types.all') }}</span>
                            <x-filament::icon icon="hugeicons-arrow-down-01" class="fff-flex-catalog__type-chevron" />
                        </button>
                    </x-slot>
                </x-filament-flex-fields::select-control>
            </div>
        </div>
        @foreach (['popular' => 'popular', 'noteworthy' => 'noteworthy'] as $section => $translation)
            @if ($catalog[$section] !== [])
                <section class="fff-flex-catalog__section" x-show="!isFiltering" aria-labelledby="flex-{{ $section }}">
                    <h2 id="flex-{{ $section }}" class="fff-flex-catalog__section-heading">{{ __('filament-flex-fields::catalog.'.$translation) }}<x-filament::icon icon="hugeicons-arrow-right-01" /></h2>
                    <div class="fff-flex-catalog__grid">
                        @foreach ($catalog[$section] as $entry) @include('filament-flex-fields::catalog.card') @endforeach
                    </div>
                </section>
            @endif
        @endforeach
        @foreach ($catalog['groups'] as $group)
            <section class="fff-flex-catalog__section fff-flex-catalog__category" aria-labelledby="flex-category-{{ $group['id'] }}" x-show="groupVisible(@js(array_column($group['entries'], 'id')))">
                <h2 id="flex-category-{{ $group['id'] }}" class="fff-flex-catalog__section-heading">{{ $group['label'] }}</h2>
                <div class="fff-flex-catalog__grid">
                    @foreach ($group['entries'] as $entry) @include('filament-flex-fields::catalog.card') @endforeach
                </div>
            </section>
        @endforeach
        <div class="fff-flex-empty" x-show="resultCount === 0" style="display:none"><p>{{ __('filament-flex-fields::catalog.empty') }}</p><button type="button" x-on:click="resetFilters()">{{ __('filament-flex-fields::catalog.clear_filters') }}</button></div>
        @include('filament-flex-fields::catalog.license-notice')
        @include('filament-flex-fields::catalog.developer-tools')
        @include('filament-flex-fields::catalog.footer')
    </div>
</x-filament-panels::page>
