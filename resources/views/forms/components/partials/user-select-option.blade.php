@php
    $layout = in_array($layout ?? 'list', ['list', 'trigger', 'tag'], true) ? ($layout ?? 'list') : 'list';
@endphp
<span class="fff-user-select-option fff-user-select-option--{{ $layout }}">
    @include('filament-flex-fields::forms.components.partials.user-select-avatar', compact('image', 'initials', 'verified', 'layout'))
    <span class="fff-user-select-option__content">
        <span class="fff-user-select-option__name">{{ $label }}</span>
        @if ($layout !== 'tag' && filled($description))
            <span class="fff-user-select-option__email">{{ $description }}</span>
        @endif
    </span>
</span>
