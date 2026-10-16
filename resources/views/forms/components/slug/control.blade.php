<div @class(['fff-flex-text-input__shell fff-slug-field__shell', 'is-invalid' => $hasError])
    data-fff-input-shell x-bind:class="{ 'is-invalid': uniqueError }">
    <div class="fff-flex-text-input__row fff-slug-field__row">
        @if ($render->locked)
            <span class="fff-slug-field__secure" aria-hidden="true"><x-filament::icon icon="hugeicons-internet" /></span>
        @endif
        <div class="fff-flex-text-input__control fff-slug-field__control">
            @if ($render->host !== null || $render->path !== null)
                <span class="fff-slug-field__prefix" title="{{ ($render->host ?? '').($render->path ?? '') }}">{{ $render->host }}{{ $render->path }}</span>
            @endif
            @if ($render->showSeparator)<span class="fff-slug-field__slug-separator" aria-hidden="true">/</span>@endif
            <x-filament-flex-fields::text-input.control :value="$render->editable"
                :id="$getId()" :name="$statePath" :placeholder="$options['placeholder']"
                class="fff-slug-field__input" type="text" inputmode="url" dir="ltr" autocomplete="off"
                autocapitalize="none" spellcheck="false" :disabled="$options['disabled']" :readonly="$render->readOnly"
                x-ref="slugInput" x-bind:value="inputValue()" x-bind:readonly="!editableNow"
                x-on:input="onInput($event)" x-on:compositionend="onInput($event)"
                x-on:focus="isFocused = true" x-on:blur="onBlur()"
                x-on:keydown.enter.prevent="if (inlineEditing && mode === 'edit') confirmEditing()"
                x-on:keydown.escape.stop="cancelEditing()"
                x-bind:aria-invalid="uniqueError || initialInvalid ? 'true' : 'false'"
                :aria-describedby="$getId().'-slug-error'" />
            @if ($render->postfix !== null)<span class="fff-slug-field__postfix">{{ $render->postfix }}</span>@endif
        </div>
    </div>
</div>
