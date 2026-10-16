<span
    role="img"
    aria-label="{{ filled($svg) ? __('filament-flex-fields::default.signature.pdf_preview_title') : __('filament-flex-fields::default.signature.preview_empty') }}"
    @class([
        'fff-signature-preview-column',
        'fff-signature-preview-column--' . $size,
        'is-empty' => blank($svg),
    ])
>
    @if (filled($svg))
        <span class="fff-signature-preview-column__frame" aria-hidden="true">
            {!! $svg !!}
        </span>
    @else
        <span class="fff-signature-preview-column__empty" aria-hidden="true">
            <x-filament::icon icon="hugeicons-signature" class="fff-signature-preview-column__empty-icon" />
        </span>
    @endif
</span>
