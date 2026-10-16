<span class="fff-switch__thumb" aria-hidden="true">
    <span class="fff-switch__thumb-surface"></span>
    @if ($presentation['hasIcons'])
        <span class="fff-switch__thumb-icons">
            <span class="fff-switch__thumb-icon fff-switch__thumb-icon--off">
                {{ \Filament\Support\generate_icon_html($presentation['offIcon'], size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
            </span>
            <span class="fff-switch__thumb-icon fff-switch__thumb-icon--on">
                {{ \Filament\Support\generate_icon_html($presentation['onIcon'], size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
            </span>
        </span>
    @endif
</span>
