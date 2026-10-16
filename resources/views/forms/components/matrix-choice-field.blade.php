@php
    use Illuminate\Support\Js;
    $statePath = $getStatePath();
    $policy = $getChoicePolicy();
    $rows = $policy->rows;
    $columns = $policy->columns;
    $multiple = $policy->multiple;
    $disabled = $isDisabled();
    $initial = $getInitialState();
    $config = ['mode' => $multiple ? 'checkbox' : 'radio', 'disabled' => $disabled, 'rows' => [], 'columns' => [], 'disabledCells' => $policy->disabledCells, 'conditionalDisableRules' => $policy->rules];
    foreach ($rows as $key => $row) $config['rows'][] = ['value' => (string) $key, ...$row];
    foreach ($columns as $key => $column) $config['columns'][] = ['value' => (string) $key, ...$column];
    $styles = [...$getMatrixSizeStyles(), '--fff-matrix-choice-columns' => count($columns)];
    $focusCell = null;
    foreach ($rows as $focusRow => $focusRowOption) {
        foreach ($columns as $focusColumn => $focusColumnOption) {
            $selection = $initial[$focusRow] ?? ($multiple ? [] : null);
            $selected = $multiple ? in_array((string) $focusColumn, $selection, true) : $selection === (string) $focusColumn;
            $atLimit = $multiple && ! $selected && $focusRowOption['max_selections'] !== null && count($selection) >= $focusRowOption['max_selections'];
            if (! $disabled && ! $atLimit && ! $policy->cellDisabled((string) $focusRow, (string) $focusColumn, $initial)) {
                $focusCell = [(string) $focusRow, (string) $focusColumn];
                break 2;
            }
        }
    }
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'matrix-choice-field', 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('matrix-choice-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="matrixChoiceFieldFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }}, disabled: @js($disabled) })"
        data-matrix-config="{{ Js::encode($config) }}"
        {{ \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class([...$getWrapperClasses(), 'is-disabled' => $disabled])->style(array_map(fn ($value, $key) => $key.': '.$value, $styles, array_keys($styles))) }}
        role="grid"
        aria-label="{{ $getLabel() }}"
        aria-multiselectable="{{ $multiple ? 'true' : 'false' }}"
    >
        <div class="fff-matrix-island" x-ignore>
            <x-filament-flex-fields::matrix.frame :grid="true">
                <x-slot:header>
                    @foreach ($columns as $columnKey => $column)
                        <div class="fff-matrix-choice__column-header" role="columnheader" wire:key="{{ $getLivewireKey() }}-header-{{ hash('xxh128', (string) $columnKey) }}">
                            @if (filled($column['icon']))
                                <span class="fff-matrix-choice__column-icon" aria-hidden="true"><x-filament::icon :icon="$column['icon']" class="fff-matrix-choice__column-icon-svg" /></span>
                            @endif
                            <span class="fff-matrix-choice__column-label">{{ $column['label'] }}</span>
                        </div>
                    @endforeach
                </x-slot:header>
                @foreach ($rows as $rowKey => $row)
                    @php $rowIndex = $loop->index; @endphp
                    <div @class(['fff-matrix-choice__row', 'is-disabled' => $disabled || $policy->rowDisabled((string) $rowKey, $initial)]) role="row" aria-label="{{ $row['label'] }}" data-matrix-row-key="{{ $rowKey }}" wire:key="{{ $getLivewireKey() }}-row-{{ hash('xxh128', (string) $rowKey) }}">
                        @include('filament-flex-fields::forms.components.matrix.row-label', ['isChoiceGrid' => true])
                        @foreach ($columns as $columnKey => $column)
                            @include('filament-flex-fields::forms.components.matrix.choice-cell', ['columnIndex' => $loop->index])
                        @endforeach
                    </div>
                @endforeach
            </x-filament-flex-fields::matrix.frame>
        </div>
    </div>
</x-dynamic-component>
