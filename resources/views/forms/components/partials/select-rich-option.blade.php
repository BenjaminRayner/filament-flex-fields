@php
    use Filament\Support\Enums\IconSize;

    $layout = $layout ?? 'list';
    $mediaLabel = ($labelHtml ?? null) !== null ? strip_tags($labelHtml) : $label;
@endphp

@if ($layout === 'grid')
    <span class="fff-select-option fff-select-option--grid">
        @include('filament-flex-fields::forms.components.partials.select-option-media', [
            'label' => $mediaLabel,
            'icon' => $icon,
            'image' => $image ?? null,
            'badgeColor' => $badgeColor ?? 'primary',
            'iconSize' => $iconSize ?? IconSize::Medium,
            'mediaClass' => 'fff-select-option__grid-icon',
        ])

        <span class="fff-select-option__grid-label">@if (($labelHtml ?? null) !== null){!! $labelHtml !!}@else{{ $label }}@endif</span>
    </span>
@elseif ($layout === 'trigger')
    <span class="fff-select-option fff-select-option--trigger">
        @if (filled($icon) || filled($image ?? null))
            @include('filament-flex-fields::forms.components.partials.select-option-media', [
                'label' => $mediaLabel,
                'icon' => $icon,
                'image' => $image ?? null,
                'badgeColor' => $badgeColor ?? 'primary',
                'iconSize' => match ($iconSize ?? null) {
                    IconSize::Small => IconSize::ExtraSmall,
                    IconSize::Large => IconSize::Small,
                    default => IconSize::Small,
                },
                'mediaClass' => 'fff-select-option__trigger-icon',
            ])
        @endif

        <span class="fff-select-option__trigger-label">@if (($labelHtml ?? null) !== null){!! $labelHtml !!}@else{{ $label }}@endif</span>
    </span>
@else
    <span @class([
        'fff-select-option fff-select-option--list',
        'fff-select-option--description-trailing' => ($descriptionPosition ?? 'below') === 'trailing',
    ])>
        @if (filled($icon) || filled($image ?? null))
            @include('filament-flex-fields::forms.components.partials.select-option-media', [
                'label' => $mediaLabel,
                'icon' => $icon,
                'image' => $image ?? null,
                'badgeColor' => $badgeColor ?? 'primary',
                'iconSize' => $iconSize ?? IconSize::Medium,
                'mediaClass' => 'fff-select-option__icon',
            ])
        @endif

        <span class="fff-select-option__content">
            <span class="fff-select-option__label">@if (($labelHtml ?? null) !== null){!! $labelHtml !!}@else{{ $label }}@endif</span>

            @if (filled($description) && ($descriptionPosition ?? 'below') === 'below')
                <span class="fff-select-option__description">{{ $description }}</span>
            @endif
        </span>

        @if (filled($description) && ($descriptionPosition ?? 'below') === 'trailing')
            <span class="fff-select-option__trailing-description" dir="auto">{{ $description }}</span>
        @endif

        @if (filled($badge))
            <span @class([
                'fff-select-option__badge',
                'fff-select-option__badge--'.($badgeColor ?? 'primary'),
            ])>{{ $badge }}</span>
        @endif
    </span>
@endif
