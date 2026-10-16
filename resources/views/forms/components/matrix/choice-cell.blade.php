@php
    $rowSelection = $initial[$rowKey] ?? ($multiple ? [] : null);
    $checked = $multiple ? in_array((string) $columnKey, $rowSelection, true) : $rowSelection === (string) $columnKey;
    $blocked = $disabled || $policy->cellDisabled((string) $rowKey, (string) $columnKey, $initial)
        || ($multiple && ! $checked && $row['max_selections'] !== null && count($rowSelection) >= $row['max_selections']);
    $tab = $focusCell === [(string) $rowKey, (string) $columnKey];
@endphp
<div
    @class(['fff-matrix-choice__cell', 'is-selected' => $checked, 'is-disabled' => $blocked])
    role="gridcell"
    aria-label="{{ $row['label'] }} — {{ $column['label'] }}"
    aria-selected="{{ $checked ? 'true' : 'false' }}"
    aria-disabled="{{ $blocked ? 'true' : 'false' }}"
    data-matrix-row="{{ $rowIndex }}" data-matrix-col="{{ $columnIndex }}"
    data-row-key="{{ $rowKey }}" data-column-key="{{ $columnKey }}"
    wire:key="{{ $getLivewireKey() }}-cell-{{ hash('xxh128', json_encode([(string) $rowKey, (string) $columnKey])) }}"
    tabindex="{{ $tab ? 0 : -1 }}"
>
    <span class="fff-matrix-choice__{{ $multiple ? 'checkbox' : 'radio' }}">
        <input type="{{ $multiple ? 'checkbox' : 'radio' }}" class="fff-matrix-choice__input" hidden aria-hidden="true" tabindex="-1" @checked($checked) @disabled($blocked) />
        <span class="fff-matrix-choice__control" aria-hidden="true">
            <span class="fff-matrix-choice__indicator fff-matrix-choice__indicator--{{ $multiple ? 'checkbox' : 'radio' }}">
                @if ($multiple)
                    <svg class="fff-matrix-choice__indicator-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                @else
                    <span class="fff-matrix-choice__indicator-dot"></span>
                @endif
            </span>
        </span>
    </span>
</div>
