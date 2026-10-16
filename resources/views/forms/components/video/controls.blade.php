@php
    $toolbarIconSize = $usesCompactControls ? \Filament\Support\Enums\IconSize::ExtraSmall : \Filament\Support\Enums\IconSize::Small;
@endphp
<div class="fff-video-field__scrim is-panel-visible" x-bind:class="{ 'is-panel-visible': panelVisible }"></div>
<div class="fff-video-field__ui is-panel-visible" x-bind:class="{ 'is-panel-visible': panelVisible }" x-bind:inert="! panelVisible">
    <div @class(['fff-video-field__dock', 'fff-video-field__dock--compact' => $usesCompactControls, 'fff-video-field__dock--default' => ! $usesCompactControls])>
        @if ($usesCompactControls)
            @include('filament-flex-fields::forms.components.video.compact')
        @else
            @include('filament-flex-fields::forms.components.video.metadata')
            <div class="fff-video-field__progress-block fff-video-field__progress-block--default">
                @include('filament-flex-fields::forms.components.partials.video-field-progress')
            </div>
            <div class="fff-video-field__toolbar fff-video-field__toolbar--default">
                <div class="fff-video-field__toolbar-start">
                    @include('filament-flex-fields::forms.components.video.play')
                    <div class="fff-video-field__duration-pill" aria-live="off"><span x-text="durationRangeLabel">0:00 / 0:00</span></div>
                </div>
                <div class="fff-video-field__toolbar-end">
                    @if ($options['volumeControl'])
                        @include('filament-flex-fields::forms.components.partials.video-field-volume-control', ['iconSize' => $toolbarIconSize])
                    @endif
                    @include('filament-flex-fields::forms.components.partials.video-field-toolbar-end')
                </div>
            </div>
        @endif
    </div>
</div>
