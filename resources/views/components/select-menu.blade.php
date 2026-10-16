@props(['field' => null, 'dynamicId' => false, 'controlId' => null, 'label' => '', 'searchable' => false, 'inlineSearch' => false, 'grid' => false, 'optionView' => null, 'loadingView' => null, 'loadMoreView' => null, 'rendererData' => [], 'panelClasses' => [], 'panelStyle' => null, 'recycleRows' => false])
@php
    $headlessComponentKey = $field?->getKey() ?? $controlId;
    $statePath = $field?->getStatePath() ?? $controlId;
    $headlessMenuDomId = ($field?->getId() ?? $controlId).'-fff-headless-menu';
    $headlessListboxId = ($field?->getId() ?? $controlId).'-listbox';
    $headlessSearchId = ($field?->getId() ?? $controlId).'-search';
    $fieldLabel = $field?->getLabel() ?? $label;
    $isSearchable = $field?->isSearchable() ?? $searchable;
    $isInlineSearch = $field?->hasInlineSearch() ?? $inlineSearch;
    $isUserSelectField = $field instanceof \Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
    $useRichListDropdownLayout = $field?->shouldUseRichListDropdownLayout() ?? false;
    $isGridLayout = $field ? $field->getOptionLayout() === 'grid' : $grid;
    $headlessOptionView = $optionView;
    $headlessRecycleRows = $recycleRows;
    $headlessLoadingView = $loadingView;
    $headlessLoadMoreView = $loadMoreView;
    $headlessRendererData = $rendererData;
    $headlessEmptyIconHtml = \Filament\Support\generate_icon_html('hugeicons-search-remove')?->toHtml() ?? '';
    $canOptionLabelsWrap = $field?->canOptionLabelsWrap() ?? false;
    $headlessSearchClearIconHtml = \Filament\Support\generate_icon_html($field?->getClearIcon() ?? 'hugeicons-multiplication-sign')?->toHtml() ?? '';
    $headlessSmartCreateIconHtml = \Filament\Support\generate_icon_html('hugeicons-plus-sign')?->toHtml() ?? '';
@endphp
@include('filament-flex-fields::forms.components.partials.select-field.menu-shell')
