<button type="button" class="fff-flex-theme-switch" role="switch" x-data :aria-checked="$store.theme === 'dark'"
    aria-label="{{ __('filament-flex-fields::catalog.dark_mode') }}" :title="$store.theme === 'dark' ? @js(__('filament-flex-fields::catalog.light_mode')) : @js(__('filament-flex-fields::catalog.dark_mode'))"
    @click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')" :class="{ 'is-dark': $store.theme === 'dark' }">
    <span class="fff-flex-theme-switch__thumb"><span x-show="$store.theme !== 'dark'"><x-filament::icon icon="hugeicons-sun-03" /></span><span x-show="$store.theme === 'dark'" x-cloak><x-filament::icon icon="hugeicons-moon-02" /></span></span>
</button>
