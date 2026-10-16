{{--
    Icon picker option row — shared markup for list/grid/icons layouts.
    List layout reuses SelectField headless option classes for keyboard/highlight parity.
--}}
<button
    type="button"
    class="fff-icon-picker__option fi-select-input-option"
    x-bind:class="iconOptionClasses(entry.index)"
    x-bind:data-icon-index="entry.index"
    x-on:click="selectIcon(entry.item.name)"
    x-on:pointermove="onIconOptionPointerMove($event, entry.index)"
    x-bind:disabled="readOnly"
    x-bind:aria-label="entry.item.name"
    x-bind:title="layout === 'icons' ? entry.item.name : null"
    role="option"
    x-bind:id="iconOptionDomId(entry.index)"
    x-bind:aria-selected="state === entry.item.name"
    x-bind:aria-posinset="entry.index + 1"
    x-bind:aria-setsize="hasMore ? -1 : loadedIconItems.length"
    tabindex="-1"
>
    <span class="fff-icon-picker__option-icon" x-bind:data-icon-name="entry.item.name">
        <span
            x-show="svgFor(entry.item.name)"
            x-html="svgFor(entry.item.name)"
        ></span>
    </span>
    {{-- Sibling of icon so icons layout can fill the whole option box; removed when SVG is ready. --}}
    <span
        class="fff-icon-picker__option-icon-skeleton"
        x-show="! svgFor(entry.item.name)"
        x-cloak
        aria-hidden="true"
    ></span>
    <span
        class="fff-icon-picker__option-label fff-select-headless-option-label"
        x-show="layout !== 'icons'"
        x-html="highlightedLabel(entry.item.label)"
    ></span>
</button>
