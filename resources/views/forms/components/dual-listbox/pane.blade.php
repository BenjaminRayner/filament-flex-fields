<section class="fff-dual-listbox__panel" data-dual-pane="{{ $pane }}" aria-labelledby="{{ $ownerId }}-{{ $pane }}-heading">
    <div class="fff-dual-listbox__panel-header">
        <div class="fff-dual-listbox__heading"><span class="fff-dual-listbox__pane-icon" aria-hidden="true"><x-filament::icon :icon="$pane === 'available' ? 'hugeicons-list-view' : 'hugeicons-check-list'" stroke-width="2" /></span>
            <h3 id="{{ $ownerId }}-{{ $pane }}-heading">{{ $pane === 'available' ? $getAvailableLabel() : $getSelectedLabel() }}</h3></div>
        <span class="fff-dual-listbox__panel-count" data-dual-count>{{ count($items) }}</span>
    </div>
    @if ($config['searchable'])
        <div class="fff-dual-listbox__search fff-flex-text-input fff-flex-text-input--sm fff-flex-text-input--primary has-focus-outline">
            <x-filament-flex-fields::text-input.shell :prefix-icon="$field->getSearchIcon()">
                <x-filament::input.wrapper class="fff-flex-text-input__wrapper">
                    <x-filament-flex-fields::text-input.control type="search" data-dual-search="{{ $pane }}" :disabled="$locked" :placeholder="__('filament-flex-fields::default.dual_listbox.search_'.$pane)"
                        :aria-label="__('filament-flex-fields::default.dual_listbox.search_'.$pane)" autocomplete="off" maxlength="256" />
                </x-filament::input.wrapper>
            </x-filament-flex-fields::text-input.shell>
        </div>
    @endif
    <div class="fff-dual-listbox__viewport">
        <div class="fff-dual-listbox__list" data-dual-list="{{ $pane }}" role="listbox" aria-multiselectable="true" aria-labelledby="{{ $ownerId }}-{{ $pane }}-heading" aria-describedby="{{ $ownerId }}-hint" tabindex="{{ $items === [] ? 0 : -1 }}">
            <div data-dual-spacer="top" aria-hidden="true"></div>
            @foreach (array_slice($items, 0, $config['virtualThreshold'] < count($items) ? 14 : count($items)) as $index => $option)
                @include('filament-flex-fields::forms.components.dual-listbox.row')
            @endforeach
            <div data-dual-spacer="bottom" aria-hidden="true" style="height:{{ $config['virtualThreshold'] < count($items) ? max(0, count($items) - 14) * $rowHeight : 0 }}px"></div>
            <p class="fff-dual-listbox__empty" data-dual-empty @if ($items !== []) hidden @endif>{{ $config['labels']['empty_'.$pane] }}</p>
        </div>
    </div>
    <div class="fff-dual-listbox__panel-footer">
        <span data-dual-highlight-count>{{ __('filament-flex-fields::default.dual_listbox.selection_count', ['count' => 0]) }}</span>
        <button type="button" data-dual-action="select-all" @disabled($locked || $items === [])>{{ __('filament-flex-fields::default.dual_listbox.select_all') }}</button>
        <button type="button" data-dual-action="clear" disabled>{{ __('filament-flex-fields::default.dual_listbox.clear') }}</button>
    </div>
    <template data-dual-template>
        @include('filament-flex-fields::forms.components.dual-listbox.row', ['option' => ['value' => '', 'label' => ''], 'index' => 0])
    </template>
</section>
