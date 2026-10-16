{{-- This catalog's action list is static; Alpine owns its teleported panel across action morphs. --}}
<div class="fff-flex-detail__menu-host" wire:ignore wire:key="flex-detail-actions-{{ $entry['id'] }}" x-fff-load x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('flex-catalog-detail') }}" x-data="flexCatalogDetailComponent()" x-id="['flex-demo-menu']">
    <x-filament-flex-fields::assets component="flex-catalog-detail" :consumer="'flex.detail.actions.'.$entry['id']" />
    <button type="button" class="fff-flex-detail__more" x-ref="menuTrigger" aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="menuOpen" x-bind:aria-controls="$id('flex-demo-menu')" aria-label="{{ __('filament-flex-fields::catalog.demo_actions') }}" x-on:click="toggleMenu()" x-on:keydown.arrow-down.prevent="menuOpen = true" x-on:keydown.arrow-up.prevent="menuOpen = true">
        <x-filament::icon icon="hugeicons-more-horizontal" />
    </button>
    <template x-teleport="body">
        <div class="fff-teleported-menu fff-flex-detail-menu" x-ref="menuMenu" x-bind:id="$id('flex-demo-menu')" x-show.important="menuOpen" x-bind:class="{ 'is-positioned': menuReady }" x-bind:inert="!menuOpen" x-bind:aria-hidden="!menuOpen" x-cloak role="menu" aria-label="{{ __('filament-flex-fields::catalog.demo_actions') }}" x-on:keydown="onMenuKeydown($event)">
            @foreach ($demoActions as $action)
                <button type="button" role="menuitem" tabindex="-1" class="fff-flex-detail-menu__item" x-on:click="runAction(@js($action->getName()))" x-bind:disabled="actionPending">
                    <x-filament::icon :icon="$action->getIcon()" />
                    <span>{{ $action->getLabel() }}</span>
                </button>
            @endforeach
        </div>
    </template>
</div>
