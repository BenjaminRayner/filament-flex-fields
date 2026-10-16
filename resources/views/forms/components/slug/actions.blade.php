@if ($render->primary !== [] || $render->secondary !== [])
    <div class="fff-slug-field__actions" x-bind:aria-busy="busy"
        x-show="(inlineEditing && canEdit()) || hasSecondaryActions()"
        @if ($render->primary === [] && ! collect($render->secondary)->contains(fn ($item) => $item->initiallyVisible === true)) style="display: none;" @endif>
        @if ($render->primary !== [])
            <x-filament-flex-fields::segment-control :items="$render->primary" role="group" :size="$size" :full-width="true" appearance="commands"
                :icon-only="! $options['showActionButtonLabels']" :identity="$identity.'.primary'"
                class="fff-slug-field__commands" x-data="actionGroup()" />
        @endif
        @if ($render->secondary !== [])
            <x-filament-flex-fields::segment-control :items="$render->secondary" role="group" :size="$size" :full-width="true" appearance="commands"
                :icon-only="! $options['showActionButtonLabels']" :identity="$identity.'.secondary'"
                class="fff-slug-field__commands fff-slug-field__commands--secondary" x-data="actionGroup()"
                :style="collect($render->secondary)->contains(fn ($item) => $item->initiallyVisible === true) ? null : 'display: none;'"
                x-show="hasSecondaryActions()" />
        @endif
    </div>
@endif
