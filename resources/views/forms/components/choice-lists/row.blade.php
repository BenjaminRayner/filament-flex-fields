<div
    wire:key="{{ $getLivewireKey() }}-{{ $listComponent }}-{{ hash('xxh128', $key) }}"
    data-choice-option
    data-choice-value="{{ $key }}"
    @class([$prefix.'__item', 'is-selected' => $checked, 'is-disabled' => $blocked])
    role="{{ $multiple ? 'checkbox' : 'radio' }}"
    aria-label="{{ $option['label'] }}"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
    aria-disabled="{{ $blocked ? 'true' : 'false' }}"
    tabindex="{{ $tabKey === $key ? 0 : -1 }}"
>
    <div class="{{ $prefix }}__cell">
        <div class="{{ $prefix }}__selection-cell">
            <div class="{{ $prefix }}__{{ $multiple ? 'checkbox' : 'radio' }}">
                <input type="{{ $multiple ? 'checkbox' : 'radio' }}" value="{{ $key }}"
                    class="{{ $prefix }}__input" tabindex="-1" hidden aria-hidden="true"
                    @checked($checked) @disabled($blocked) />
                @if ($multiple)
                    <x-filament-flex-fields::checklist-indicator :checked="$checked" />
                @else
                    <span class="fff-flex-radiolist__control" aria-hidden="true"><span class="fff-flex-radiolist__indicator"><span class="fff-flex-radiolist__indicator-dot"></span></span></span>
                @endif
            </div>
        </div>
        @include('filament-flex-fields::forms.components.choice-lists.copy')
        @if ($option['disabled'])
            <div class="{{ $prefix }}__action" aria-hidden="true"><x-filament::icon :icon="$getLockIcon()" class="{{ $prefix }}__action-icon" /></div>
        @endif
    </div>
</div>
