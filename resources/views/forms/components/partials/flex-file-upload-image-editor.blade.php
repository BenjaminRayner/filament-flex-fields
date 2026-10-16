@php
    $editorTitleId = $id.'-image-editor-title';
    $editorDescriptionId = $id.'-image-editor-description';
    $editorActions = $getImageEditorActions();
    $editorRatios = $getImageEditorAspectRatioOptionsForJs();
    $editorAdjustments = ['brightness' => 'sun-03', 'warmth' => 'temperature', 'contrast' => 'contrast', 'exposure' => 'plus-minus-01', 'highlights' => 'sun-02', 'shadows' => 'moon-02', 'fade' => 'circle', 'saturation' => 'rainbow', 'vibrance' => 'droplet', 'clarity' => 'blur', 'sharpness' => 'triangle', 'vignette' => 'square'];
    $editorAdjustmentLabels = array_combine(array_keys($editorAdjustments), array_map(fn ($name) => __('filament-flex-fields::default.file_upload.editor.'.$name), array_keys($editorAdjustments)));
@endphp
<template x-teleport="body">
    <dialog x-ref="editorDialog" class="fff-image-editor {{ $hasCircleCropper ? 'fff-image-editor--circle' : '' }}" x-bind:data-tool="editorTab"
        aria-labelledby="{{ $editorTitleId }}" aria-describedby="{{ $editorDescriptionId }}"
        x-on:cancel.prevent.stop="closeEditor()" x-on:click.stop="">
        <h2 id="{{ $editorTitleId }}" class="fff-image-editor__accessible">{{ __('filament-flex-fields::default.file_upload.editor.title') }}</h2>
        <p id="{{ $editorDescriptionId }}" class="fff-image-editor__accessible">{{ __('filament-flex-fields::default.file_upload.editor.instruction') }}</p>
        <button type="button" class="fff-image-editor__action fff-image-editor__reset" x-on:click="editorCommand('reset')" x-bind:disabled="!editorReady || editorBusy">
            <x-filament::icon icon="hugeicons-reload" /><span>{{ __('filament-forms::components.file_upload.editor.actions.reset.label') }}</span>
        </button>
        <button type="button" class="fff-image-editor__close" x-on:click="closeEditor()" x-bind:disabled="editorBusy"
            aria-label="{{ __('filament-flex-fields::default.file_upload.editor.close') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
        <button type="button" class="fff-image-editor__action fff-image-editor__save" x-on:click="saveEditor()" x-bind:disabled="!editorReady || editorBusy || editorAdjusting">
            <span class="fff-image-editor__spinner" x-show="editorBusy" aria-hidden="true"></span>
            <x-filament::icon icon="hugeicons-tick-02" x-show="!editorBusy" />
            <span x-text="editorBusy ? @js(__('filament-flex-fields::default.file_upload.editor.saving')) : @js(__('filament-flex-fields::default.file_upload.editor.apply'))"></span>
        </button>
        <div class="fff-image-editor__workspace" x-bind:aria-busy="editorBusy || !editorReady">
            <div class="fff-image-editor__canvas">
                <div class="fff-image-editor__image-stage" x-bind:class="{ 'is-ready': editorReady }">
                    <img x-ref="editor" class="fff-image-editor__image" alt="{{ __('filament-flex-fields::default.file_upload.editor.preview') }}" />
                </div>
                <div class="fff-image-editor__loading" x-show="!editorReady && !editorError">
                    <span class="fff-image-editor__spinner" aria-hidden="true"></span><span>{{ __('filament-flex-fields::default.file_upload.editor.loading') }}</span>
                </div>
                @if (! $hasCircleCropper && $isImageEditorExplicitlyEnabled)
                    <div class="fff-image-editor__ratios" x-show="editorTab === 'crop'" role="group" aria-label="{{ __('filament-forms::components.file_upload.editor.aspect_ratios.label') }}">
                        <button type="button" class="fff-image-editor__ratio" x-on:click="editorCommand('ratio', { label: '', ratio: null })" x-bind:aria-pressed="currentRatio === ''" x-bind:disabled="!editorReady || editorBusy">{{ __('filament-flex-fields::default.file_upload.editor.free') }}</button>
                        @foreach ($editorRatios as $label => $ratio)
                            @if (is_numeric($ratio))
                                <button type="button" class="fff-image-editor__ratio" x-on:click="editorCommand('ratio', { label: @js($label), ratio: @js($ratio) })" x-bind:aria-pressed="currentRatio === @js($label)" x-bind:disabled="!editorReady || editorBusy">{{ $label }}</button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="fff-image-editor__controls">
                <div class="fff-image-editor__control-slot">
                    <div class="fff-image-editor__transforms" x-show="editorTab === 'crop'" role="group" aria-label="{{ __('filament-flex-fields::default.file_upload.editor.transform') }}">
                        @foreach ([['rotate', 'rotate-clockwise', $editorActions['transform'][1]['label']], ['flipX', 'flip-horizontal', $editorActions['transform'][2]['label']], ['flipY', 'flip-vertical', $editorActions['transform'][3]['label']]] as [$command, $icon, $label])
                            <button type="button" class="fff-image-editor__transform" x-on:click="editorCommand(@js($command), 90)" x-bind:disabled="!editorReady || editorBusy" aria-label="{{ $label }}" title="{{ $label }}">
                                <x-filament::icon :icon="'hugeicons-'.$icon" /><span>{{ $command === 'rotate' ? '90°' : ($command === 'flipX' ? 'X' : 'Y') }}</span>
                            </button>
                        @endforeach
                    </div>
                    @if ($isImageEditorExplicitlyEnabled)
                        <label class="fff-image-editor__ruler" x-show="editorTab !== 'crop'">
                            <span class="fff-image-editor__ruler-label" x-text="@js($editorAdjustmentLabels)[editorAdjustment]"></span>
                            <span class="fff-image-editor__ruler-scale" aria-hidden="true">
                                <span class="fff-image-editor__ruler-ticks"></span>
                            </span>
                            <output x-text="Math.round(editorAdjustments[editorAdjustment] || 0) + '%'"></output>
                            <input type="range" x-bind:min="['fade', 'sharpness', 'vignette'].includes(editorAdjustment) ? 0 : -100" max="100" step="5"
                                x-bind:aria-label="@js($editorAdjustmentLabels)[editorAdjustment]" x-bind:value="editorAdjustments[editorAdjustment] || 0" x-bind:disabled="!editorReady || editorBusy"
                                />
                        </label>
                    @endif
                </div>
                <div class="fff-image-editor__tools" role="group" aria-label="{{ __('filament-flex-fields::default.file_upload.editor.tools') }}">
                    <button type="button" class="fff-image-editor__tool" x-on:click="editorCommand('tab', 'crop')" x-bind:aria-pressed="editorTab === 'crop'" x-bind:disabled="!editorReady || editorBusy">
                        <span class="fff-image-editor__tool-icon"><x-filament::icon icon="hugeicons-crop" /></span><span>{{ __('filament-flex-fields::default.file_upload.editor.crop') }}</span>
                    </button>
                    @if ($isImageEditorExplicitlyEnabled)
                        @foreach ($editorAdjustments as $adjustment => $icon)
                            <button type="button" class="fff-image-editor__tool" x-on:click="editorCommand('selectAdjustment', @js($adjustment)); $el.scrollIntoView({ block: 'nearest', inline: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
                                x-bind:aria-pressed="editorTab !== 'crop' && editorAdjustment === @js($adjustment)" x-bind:disabled="!editorReady || editorBusy"
                                x-bind:style="{ '--fff-adjustment-value': Math.abs(editorAdjustments.{{ $adjustment }} || 0) + '%' }">
                                <span class="fff-image-editor__tool-icon"><x-filament::icon :icon="'hugeicons-'.$icon" /></span><span>{{ $editorAdjustmentLabels[$adjustment] }}</span>
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="fff-image-editor__error" role="alert" x-show="editorError">
            <span x-text="editorError"></span><button type="button" x-on:click="loadEditor(editingFile, editorFileId)" x-bind:disabled="editorBusy">{{ __('filament-flex-fields::default.file_upload.editor.retry') }}</button>
        </div>
    </dialog>
</template>
