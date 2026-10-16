@php
    use Filament\Support\Enums\IconSize;
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Filament\Support\Facades\FilamentAsset;
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $livewireKey = $getLivewireKey();
    $hasError = filled($statePath) && $errors->has($statePath);
    $alpineConfig = $field->getAlpineConfiguration($isRequired());
    $signatureAssetSrc = FilamentAsset::getAlpineComponentSrc('signature-field', FilamentFlexFieldsPlugin::PACKAGE_NAME);
    $initialSvg = $field->getStoredSvgContent() ?? $getState();
    $initialSvg = is_string($initialSvg) && str_contains($initialSvg, '<svg') ? \Bjanczak\FilamentFlexFields\Support\SignatureSvg::normalize($initialSvg) : null;
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($field->getWrapperClasses())">
    <div wire:ignore wire:key="{{ $livewireKey }}.{{ md5(serialize([$isDisabled, $isReadOnly, $field->getPenColors(), $field->getPenWidths(), $field->getSigner(), $field->isFullscreenEnabled(), $field->isReplayEnabled(), $field->isTrackpadGlideEnabled(), $field->isGuidelinesEnabled()])) }}"
        class="fff-signature" role="group" aria-label="{{ $getLabel() }}">
        <x-filament-flex-fields::assets component="signature-field" :consumer="$livewireKey" />
        @if ($field->requiresLegalInk())<div class="fff-signature__legal-banner" role="note">{{ __('filament-flex-fields::default.signature.legal_acknowledgment') }}</div>@endif
        <div x-fff-load x-fff-load-src="{{ $signatureAssetSrc }}"
            x-data="signatureFieldFormComponent({
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                penColors: @js($field->getPenColors()),
                penWidths: @js($field->getPenWidths()),
                signer: @js($field->getSigner()),
                replayEnabled: @js($field->isReplayEnabled()),
                penColor: @js($field->getPenColor()),
                penWidth: @js($field->getPenWidth()),
                backgroundColor: @js($field->getBackgroundColor()),
                viewBoxWidth: @js($field->getViewBoxWidth()),
                viewBoxHeight: @js($field->getViewBoxHeight()),
                readOnly: @js($isDisabled || $isReadOnly),
                fullscreenEnabled: @js($field->isFullscreenEnabled()),
                undoable: @js($field->isUndoable()),
                smoothingEnabled: @js($field->isSmoothingEnabled()),
                trackpadGlideEnabled: @js($field->isTrackpadGlideEnabled()),
                trackpadGlideKey: @js($field->isTrackpadGlideEnabled() ? $field->getTrackpadGlideKey() : 'd'),
                guidelinesEnabled: @js($field->isGuidelinesEnabled()),
                downloadFormat: @js($field->getDownloadFormat()),
                downloadFilename: @js($field->getDownloadFilename()),
                webpQuality: @js($field->getWebpQuality()),
                storedSvg: @js($field->getStoredSvgContent()),
                minStrokes: @js($alpineConfig['minStrokes']),
                maxSizeKb: @js($alpineConfig['maxSizeKb']),
                required: @js($alpineConfig['required']),
                validationMessages: @js($alpineConfig['validationMessages']),
                inkTrailEnabled: @js($alpineConfig['inkTrailEnabled']),
                pdfPreviewEnabled: @js($alpineConfig['pdfPreviewEnabled']),
                legalAcknowledgment: @js($alpineConfig['legalAcknowledgment']),
                labels: {
                    placeholder: @js($field->isTrackpadGlideEnabled()
                        ? __('filament-flex-fields::default.signature.trackpad_placeholder')
                        : __('filament-flex-fields::default.signature.placeholder')),
                    clear: @js(__('filament-flex-fields::default.signature.clear')),
                    redo: @js(__('filament-flex-fields::default.signature.redo')),
                    replay: @js(__('filament-flex-fields::default.signature.replay')),
                    replay_unavailable: @js(__('filament-flex-fields::default.signature.replay_unavailable')),
                    undo: @js(__('filament-flex-fields::default.signature.undo')),
                    fullscreen: @js(__('filament-flex-fields::default.signature.fullscreen')),
                    download: @js(__('filament-flex-fields::default.signature.download')),
                    done: @js(__('filament-flex-fields::default.signature.done')),
                    close: @js(__('filament-flex-fields::default.signature.close')),
                    trackpad_pill_paused: @js(__('filament-flex-fields::default.signature.trackpad_pill_paused')),
                    trackpad_pill_active: @js(__('filament-flex-fields::default.signature.trackpad_pill_active')),
                    pdf_preview: @js(__('filament-flex-fields::default.signature.pdf_preview')),
                    pdf_preview_title: @js(__('filament-flex-fields::default.signature.pdf_preview_title')),
                    pdf_preview_body: @js(__('filament-flex-fields::default.signature.pdf_preview_body')),
                },
            })"
            x-ref="root" tabindex="0" class="fff-signature__root"
            style="--fff-signature-aspect-ratio: {{ $field->getViewBoxWidth() }} / {{ $field->getViewBoxHeight() }};"
            x-bind:class="{ 'is-ready': displayReady }" x-on:keydown="handleShortcut($event)"
            x-on:keydown.window.capture="handleGlideKeydown($event)">
            @include('filament-flex-fields::forms.components.signature.pad', ['fullscreenPad' => false])
            @include('filament-flex-fields::forms.components.signature.toolbar')
            <p class="fff-signature__validation" x-show="clientValidationError" x-cloak x-text="clientValidationError" role="alert"></p>
            <template x-teleport="body">
                <dialog x-ref="fullscreenDialog" class="fff-signature__dialog" aria-label="{{ $getLabel() }}"
                    x-on:cancel.prevent="closeFullscreen()" x-on:close="isFullscreen = false" x-on:keydown="handleShortcut($event)">
                    <div class="fff-signature__dialog-top"><button type="button" x-on:click="closeFullscreen()" aria-label="{{ __('filament-flex-fields::default.signature.close') }}"><x-filament::icon :icon="$field->getCloseIcon()" /></button><button type="button" class="fff-signature__done" x-on:click="confirmFullscreen()">{{ __('filament-flex-fields::default.signature.done') }}</button></div>
                    @include('filament-flex-fields::forms.components.signature.pad', ['fullscreenPad' => true])
                    @include('filament-flex-fields::forms.components.signature.toolbar', ['inFullscreen' => true])
                </dialog>
            </template>
            @if ($field->isPdfPreviewEnabled())
            <template x-teleport="body">
                <dialog x-ref="pdfDialog" class="fff-signature__dialog fff-signature__dialog--document"
                    aria-labelledby="{{ $livewireKey }}-document-title" aria-describedby="{{ $livewireKey }}-document-description"
                    x-on:cancel.prevent="closePdfPreview()" x-on:close="closePdfPreview()">
                    <div class="fff-signature__dialog-top fff-signature__document-header">
                        <h2 class="fff-signature__document-title" id="{{ $livewireKey }}-document-title">{{ __('filament-flex-fields::default.signature.pdf_preview_title') }}</h2>
                        <button type="button" x-on:click="closePdfPreview()" aria-label="{{ __('filament-flex-fields::default.signature.close') }}"><x-filament::icon :icon="$field->getCloseIcon()" /></button>
                    </div>
                    <div class="fff-signature__document-body">
                        <p class="fff-signature__document-description" id="{{ $livewireKey }}-document-description">{{ __('filament-flex-fields::default.signature.pdf_preview_body') }}</p>
                        <div class="fff-signature__pdf-document" x-bind:class="{ 'is-empty': !signaturePreviewUrl }">
                            <template x-if="signaturePreviewUrl">
                                <img x-bind:src="signaturePreviewUrl" alt="{{ $getLabel() }}" />
                            </template>
                            <div class="fff-signature__document-empty" x-show="!signaturePreviewUrl">
                                <x-filament::icon icon="hugeicons-signature" class="fff-signature__document-empty-icon" />
                                <span>{{ __('filament-flex-fields::default.signature.preview_empty') }}</span>
                                <p>{{ __('filament-flex-fields::default.signature.preview_empty_description') }}</p>
                            </div>
                        </div>
                    </div>
                </dialog>
            </template>
            @endif
        </div>
    </div>
</x-dynamic-component>
