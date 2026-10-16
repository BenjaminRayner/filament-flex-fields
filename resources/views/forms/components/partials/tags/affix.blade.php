@php
    $prefix = $position === 'prefix';
    $actions = array_filter($prefix ? $getPrefixActions() : $getSuffixActions(), static fn ($action) => $action->isVisible());
    $icon = $prefix ? $getPrefixIcon() : $getSuffixIcon();
    $label = $prefix ? $getPrefixLabel() : $getSuffixLabel();
    $color = $prefix ? $getPrefixIconColor() : $getSuffixIconColor();
@endphp
@if ($actions || $icon || filled($label))
    <div class="fff-flex-text-input__{{ $position }}-wrap">
        @if ($prefix && filled($label)) <span class="fff-flex-text-input__prefix-label">{{ $label }}</span> @endif
        @if ($prefix && $icon) <span class="fff-flex-text-input__prefix-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($icon, color: $color) }}</span> @endif
        @foreach ($actions as $action) {{ $action }} @endforeach
        @if (! $prefix && $icon) <span class="fff-flex-text-input__suffix-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($icon, color: $color) }}</span> @endif
        @if (! $prefix && filled($label)) <span class="fff-flex-text-input__suffix-label">{{ $label }}</span> @endif
    </div>
@endif
