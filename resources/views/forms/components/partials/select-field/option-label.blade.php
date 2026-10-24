@if (isset($isHtmlAllowed))
    <span class="fff-select-headless-option-label" @if ($isHtmlAllowed) x-html="optionDropdownLabel(row.option)" @else x-text="optionDropdownLabel(row.option)" @endif></span>
@else
    {{-- Embedded adapters may own this flag exclusively in their Alpine scope. --}}
    <template x-if="isHtmlAllowed">
        <span class="fff-select-headless-option-label" x-html="optionDropdownLabel(row.option)"></span>
    </template>
    <template x-if="! isHtmlAllowed">
        <span class="fff-select-headless-option-label" x-text="optionDropdownLabel(row.option)"></span>
    </template>
@endif
