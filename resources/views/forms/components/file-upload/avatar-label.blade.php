<span class="fff-flex-file-upload__avatar-placeholder">
    @if (isset($avatarPlaceholder) && $avatarPlaceholder)
        <img class="fff-flex-file-upload__default-avatar" src="{{ $avatarPlaceholder->url }}" alt="{{ $avatarPlaceholder->alt }}"
            style="object-fit: {{ $avatarPlaceholder->fit }}" referrerpolicy="{{ $avatarPlaceholder->referrerPolicy }}"
            x-init="if ($el.complete && !$el.naturalWidth) { $el.hidden = true; $el.nextElementSibling.hidden = false }"
            x-on:error="$el.hidden = true; $el.nextElementSibling.hidden = false" />
        <span hidden><x-filament::icon icon="hugeicons-image-02" /></span>
    @else
        <x-filament::icon icon="hugeicons-image-02" />
    @endif
    <span class="fff-flex-file-upload__accessible-label">{{ __('filament-flex-fields::default.file_upload.visual.avatar_add') }}</span>
</span>
