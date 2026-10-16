@props(['presentation', 'id' => null])
@php $checkedAttribute = $presentation['checked'] ? 'true' : 'false'; @endphp
<button
    type="button"
    @if ($id !== null) id="{{ $id }}" @endif
    {{ $attributes->class([
        'fff-switch__control' => $presentation['inline'],
        'fff-switch__track' => ! $presentation['inline'],
        implode(' ', $presentation['checked'] ? $presentation['onClasses'] : $presentation['offClasses']) => $presentation['inline'],
    ]) }}
    data-switch-button
    role="switch"
    aria-label="{{ $presentation['label'] }}"
    aria-checked="{{ $checkedAttribute }}"
    x-bind:aria-checked="checked ? 'true' : 'false'"
    @if ($presentation['disabled']) disabled @endif
    x-bind:disabled="isDisabled"
    @if ($presentation['inline'])
        data-checked="{{ $checkedAttribute }}"
        x-bind:data-checked="checked ? 'true' : 'false'"
        x-bind:class="colors"
    @endif
>
    @unless ($presentation['inline'])
        <span class="fff-switch__content">
            <span class="fff-switch__title">
                <span class="fff-switch__label">{{ $presentation['label'] }}</span>
                @if (filled($presentation['badge']))
                    <span class="fff-switch__badge fff-switch__badge--{{ $presentation['badgeColor'] }}">{{ $presentation['badge'] }}</span>
                @endif
            </span>
            @if (filled($presentation['description']))
                <span class="fff-switch__description">{{ $presentation['description'] }}</span>
            @endif
        </span>
        <span
            @class(['fff-switch__control', implode(' ', $presentation['checked'] ? $presentation['onClasses'] : $presentation['offClasses'])])
            data-checked="{{ $checkedAttribute }}"
            x-bind:data-checked="checked ? 'true' : 'false'"
            x-bind:class="colors"
            aria-hidden="true"
        >
            @include('filament-flex-fields::forms.components.partials.switch.thumb')
        </span>
    @else
        @include('filament-flex-fields::forms.components.partials.switch.thumb')
    @endunless
</button>
