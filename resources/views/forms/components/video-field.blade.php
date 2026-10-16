@php
    $video = $getVideoRenderState($getState());
    $options = $video->options;
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $showCustomControls = $video->customControls;
    $usesCompactControls = $video->compact;
    $title = $options['title'];
    $subtitle = $options['subtitle'];
    $poster = $options['poster'];
    $hasMetadata = filled($title) || filled($subtitle);
    $showSettingsMenu = $options['settingsMenu'];
    $showTooltips = $options['tooltips'];
    $hasPlaybackRateControl = $video->speedControl;
    $hasQualityControl = $video->qualityControl;
    $showPictureInPictureControl = $options['pictureInPictureable'];
    $showRemotePlaybackControl = $options['castable'] || $options['airPlayable'];
@endphp
<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class(['fff-video-field-field', 'fff-video-field-field--'.$video->size, 'fff-rounding-'.$video->rounding])"
>
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component' => 'video-field',
        'assetSchemaComponent' => $field,
        'livewireKey' => $livewireKey,
    ])
    <div
        wire:ignore
        wire:key="{{ $livewireKey }}.{{ $video->fingerprint() }}"
        @class([
            'fff-video-field', 'fff-video-field--'.$video->size,
            'is-full-width' => $video->fullWidth,
            'is-disabled' => $video->disabled, 'is-read-only' => $video->readOnly,
            'is-youtube' => $options['provider'] === 'youtube',
            'is-youtube-custom' => $options['provider'] === 'youtube' && ! $options['facadeOnly'],
            'is-vimeo' => $options['provider'] === 'vimeo',
            'has-native-controls' => $options['nativeControls'] || $options['facadeOnly'] || $options['provider'] === 'vimeo',
            'has-custom-controls' => $showCustomControls,
            'has-metadata' => $hasMetadata, 'is-controls-compact' => $usesCompactControls,
        ])
        role="group"
        aria-label="{{ $getLabel() }}"
    >
        @if ($options['provider'] === 'vimeo')
            @include('filament-flex-fields::forms.components.video.vimeo')
        @else
            <div
                class="fff-video-field__player"
                x-bind:class="{ 'is-hydrated': hydrated }"
                x-fff-load
                x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('video-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
                x-data="videoFieldFormComponent({
                    ...{{ \Illuminate\Support\Js::from($options) }},
                    state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                })"
            >
                <div
                    class="fff-video-field__frame"
                    x-ref="frame"
                    x-bind:class="{ 'is-ui-transition-ready': uiTransitionsEnabled, 'is-picture-in-picture': isPictureInPicture }"
                    x-on:keydown="onFrameKeydown($event)"
                    x-on:pointermove="onFrameMove()"
                    x-on:pointerleave="onFrameLeave($event)"
                    x-on:pointerdown="onFramePointerDown($event)"
                    x-on:pointerup="onFramePointerUp($event)"
                    tabindex="0"
                    @if ($video->ratio) style="aspect-ratio: {{ $video->ratio }};" @endif
                >
                    @if ($options['provider'] === 'youtube')
                        @include($options['facadeOnly'] ? 'filament-flex-fields::forms.components.video.facade' : 'filament-flex-fields::forms.components.video.youtube')
                    @else
                        @include('filament-flex-fields::forms.components.video.native')
                    @endif
                    @if ($showCustomControls)
                        @include('filament-flex-fields::forms.components.video.controls')
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
