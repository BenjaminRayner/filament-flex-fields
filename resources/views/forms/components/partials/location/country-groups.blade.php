@php
    $countryMapGroups ??= $field->getCountryGroups();
    $countryMapSelectedSet ??= array_fill_keys(array_filter((array) $field->getState(), is_string(...)), true);
@endphp
@if ($field->isMultiple() && count($countryMapGroups))
    <div class="fff-country-map__groups" role="group" aria-label="{{ __('filament-flex-fields::location.country_groups') }}">
        @foreach ($countryMapGroups as $groupLabel => $groupCodes)
            @php
                $selectedCount = count(array_intersect_key($countryMapSelectedSet, array_fill_keys($groupCodes, true)));
                $allSelected = count($groupCodes) > 0 && $selectedCount === count($groupCodes);
                $checked = $allSelected ? 'true' : ($selectedCount > 0 ? 'mixed' : 'false');
            @endphp
            <button type="button" @disabled($countryMapDisabled ?? $isDisabled) aria-pressed="{{ $allSelected ? 'true' : 'false' }}" x-on:click.stop="toggleGroup(@js($groupCodes))" x-bind:aria-pressed="groupSelected(@js($groupCodes))" class="fi-select-input-option fff-country-map__group">
                <span class="fff-country-map-option-row">
                    <x-filament-flex-fields::selection-indicator data-checked="{{ $checked }}" x-bind:data-checked="groupSelected({{ \Illuminate\Support\Js::from($groupCodes) }}) ? 'true' : groupPartiallySelected({{ \Illuminate\Support\Js::from($groupCodes) }}) ? 'mixed' : 'false'" />
                    <span class="fff-country-map__group-label">{{ $groupLabel }}</span>
                    <span class="fff-country-map__group-count" x-text="groupCount(@js($groupCodes))">{{ $selectedCount }} / {{ count($groupCodes) }}</span>
                </span>
            </button>
        @endforeach
    </div>
@endif
