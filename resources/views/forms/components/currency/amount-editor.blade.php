<div class="fff-currency-field__control fff-flex-text-input__control" x-on:click="focusInput()" x-on:mousedown="onHiddenPointerDown($event)">
    <div class="fff-currency-field__display" x-bind:class="{ 'is-empty': isEmpty && !isFocused }">
        <div class="fff-currency-field__digits">
            <span class="fff-currency-field__ssr-display" x-bind:class="{ 'is-replaced': displayReady }" aria-hidden="true">
                @if ($initial['isEmpty'])
                    <span class="fff-currency-field__placeholder">{{ $placeholder }}</span>
                @else
                    @if ($initial['negative']) <span class="fff-currency-field__sign">−</span> @endif
                    @foreach ($initial['segments'] as $segment)
                        <span class="{{ $segment['type'] === 'digit' ? 'fff-currency-field__digit' : 'fff-currency-field__separator' }}">
                            @if ($segment['type'] === 'digit') <span class="fff-currency-field__digit-inner">{{ $segment['char'] }}</span>
                            @else {{ $segment['char'] }} @endif
                        </span>
                    @endforeach
                @endif
            </span>
            <span class="fff-currency-field__live-display" x-bind:class="{ 'is-ready': displayReady }">
                <template x-if="isEmpty && !isFocused && !edit.negative"><span class="fff-currency-field__placeholder" x-text="placeholder"></span></template>
                <template x-if="edit.negative"><span class="fff-currency-field__sign">−</span></template>
                <template x-for="item in displayItems" x-bind:key="item.key">
                    <span class="fff-currency-field__display-item">
                        <span x-show="item.type === 'caret' && isFocused && !isLocked" x-cloak class="fff-currency-field__caret"></span>
                        <span x-show="item.type !== 'caret'" x-bind:class="{
                            'fff-currency-field__separator': item.type === 'separator', 'fff-currency-field__digit': item.type === 'digit',
                            'is-animating': item.type === 'digit' && shouldAnimateDigit(item) }"
                            x-bind:data-fff-cursor-before="item.cursorBefore ?? null" x-bind:data-fff-cursor-after="item.cursorAfter ?? null">
                            <span x-bind:class="item.type === 'digit' ? 'fff-currency-field__digit-inner' : ''" x-text="item.char"></span>
                        </span>
                    </span>
                </template>
                <template x-for="segment in ghostSegments" x-bind:key="segment.key">
                    <span class="fff-currency-field__digit is-ghost"><span class="fff-currency-field__digit-inner" x-text="segment.char"></span></span>
                </template>
            </span>
        </div>
        <span class="fff-currency-field__symbol" x-text="activeCurrency.symbol">{{ $initial['symbol'] }}</span>
    </div>
    <input id="{{ $getId() }}" type="text" inputmode="decimal" class="fff-currency-field__hidden-input" x-ref="hiddenInput"
        x-on:focus="onFocus()" x-on:blur="onBlur()" x-on:keydown="onHiddenKeydown($event)" x-on:paste="onPaste($event)"
        x-bind:disabled="disabled" x-bind:readonly="readOnly" autocomplete="off" aria-label="{{ $getLabel() }}"
        @disabled($disabled) @readonly($readOnly) />
</div>
