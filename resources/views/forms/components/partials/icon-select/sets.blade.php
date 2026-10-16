<div class="fff-icon-select__sets" x-cloak x-show="availableIconSets.length > 1" role="group" aria-label="{{ __('filament-flex-fields::default.icon_picker.all_sets') }}">
    <button type="button" x-on:click="selectIconSet(null)" x-bind:aria-pressed="activeIconSet === null">{{ __('filament-flex-fields::default.icon_picker.all_sets') }}</button>
    <template x-for="set in availableIconSets" :key="set.key">
        <button type="button" x-on:click="selectIconSet(set.key)" x-bind:aria-pressed="activeIconSet === set.key">
            <span x-text="set.label"></span><span class="fff-icon-select__count" x-text="set.count"></span>
        </button>
    </template>
</div>
