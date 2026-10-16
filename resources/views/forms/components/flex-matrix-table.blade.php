@php
    $rows = $getNormalizedRows();
    $disabled = $isDisabled();
    $containers = $getItems();
    $first = reset($containers);
    $fields = $first ? $first->getComponents() : [];
    $widths = $getColumnWidths();
    $tracks = [];
    foreach ($fields as $cellField) $tracks[] = $widths[$cellField->getName()] ?? 'minmax(0, 1fr)';
    $styles = [...$getMatrixSizeStyles(), '--fff-matrix-choice-columns' => count($fields)];
    if ($tracks !== []) $styles['--fff-matrix-choice-columns-template'] = 'var(--fff-matrix-choice-row-label-width) '.implode(' ', $tracks);
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-matrix-table', 'livewireKey' => $getLivewireKey()])
    <div {{ \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class([...$getWrapperClasses(), 'is-disabled' => $disabled])->style(array_map(fn ($value, $key) => $key.': '.$value, $styles, array_keys($styles))) }}>
        <x-filament-flex-fields::matrix.frame>
            <x-slot:header>
                @foreach ($fields as $cellField)
                    <div class="fff-matrix-choice__column-header"><span class="fff-matrix-choice__column-label">{{ $cellField->getLabel() }}</span></div>
                @endforeach
            </x-slot:header>
            @foreach ($rows as $rowKey => $row)
                <div @class(['fff-matrix-choice__row', 'is-disabled' => $disabled || $row['disabled']]) wire:key="{{ $getLivewireKey() }}-table-row-{{ hash('xxh128', (string) $rowKey) }}">
                    @include('filament-flex-fields::forms.components.matrix.row-label')
                    @if ($item = $containers[$rowKey] ?? null)
                        @foreach ($item->getComponents() as $cellField)
                            <div @class(['fff-matrix-choice__cell', 'fff-matrix-choice__schema-cell', 'is-disabled' => $disabled || $row['disabled']]) wire:key="{{ $item->getStatePath() }}-cell-{{ $cellField->getName() }}">
                                <div class="w-full">{{ $cellField }}</div>
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        </x-filament-flex-fields::matrix.frame>
    </div>
</x-dynamic-component>
