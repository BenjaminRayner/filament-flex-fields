<template x-if="! active">
    <div class="fff-video-field__youtube-facade">
        <img class="fff-video-field__video" src="{{ $options['youtubeThumbnail'] }}" alt="" decoding="async" />
        <div class="fff-video-field__scrim" aria-hidden="true"></div>
        <div class="fff-video-field__ui">
            <div class="fff-video-field__dock">
                @include('filament-flex-fields::forms.components.video.metadata')
                <div class="fff-video-field__toolbar">
                    <div class="fff-video-field__toolbar-start">
                        <button type="button" class="fff-video-field__glass-btn" x-on:click="activate()" aria-label="{{ __('filament-flex-fields::default.video.play') }}" @disabled($options['readOnly'])>
                            {{ \Filament\Support\generate_icon_html($field->getPlayIcon(), size: \Filament\Support\Enums\IconSize::Small) }}
                        </button>
                    </div>
                    @if ($options['fullscreenable'])
                        <div class="fff-video-field__toolbar-end">
                            @include('filament-flex-fields::forms.components.video.fullscreen', ['toolbarIconSize' => \Filament\Support\Enums\IconSize::Small])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</template>
<iframe class="fff-video-field__youtube-iframe" x-show="active" x-bind:src="active ? embedUrl : null" title="{{ $title ?? $getLabel() }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
