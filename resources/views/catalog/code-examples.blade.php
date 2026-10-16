@if (! $loaded)
    <div wire:key="flex-code-loading" class="fff-flex-code-surface fff-flex-code-loading" role="status" aria-busy="true">
        <div class="fff-flex-code-loading__header"><span></span><span></span></div>
        <div class="fff-flex-code-loading__body" aria-hidden="true">
            @foreach ([60, 42, 75, 54, 38] as $width)
                <span style="--skeleton-line:{{ $width }}%"></span>
            @endforeach
        </div>
        <div class="fff-flex-code-loading__footer"><span></span></div>
        <span class="fff-flex-sr-only">{{ __('filament-flex-fields::catalog.loading_examples') }}</span>
    </div>
@elseif ($examples === [])
    <p class="fff-flex-detail__notice">{{ __('filament-flex-fields::catalog.no_examples') }}</p>
@else
        <div wire:key="flex-code-ready" wire:ignore @class(['fff-flex-code-surface', 'fff-code-preview', 'fff-code-preview--wrap' => in_array($componentId, ['date-time-fields', 'date-picker', 'date-range-picker', 'time-picker', 'date-periods', 'schedule-field', 'timezone-field'], true)]) x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-code-preview', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
         x-data="flexCodePreview(@js(['sources' => array_map(fn ($example) => $example['source'] ?? '', $examples)]))">
        <x-filament-flex-fields::assets component="flex-code-preview" consumer="flex.examples" />
        <div class="fff-code-preview__header">
            <div class="fff-code-preview__files" x-ref="files" role="tablist" aria-label="{{ __('filament-flex-fields::catalog.example_files') }}">
                @foreach ($examples as $example)
                    <button type="button" role="tab" id="flex-example-tab-{{ $example['key'] }}" aria-controls="flex-example-panel-{{ $example['key'] }}"
                        :aria-selected="active === @js($example['key'])" :tabindex="active === @js($example['key']) ? 0 : -1"
                        :class="{ 'is-active': active === @js($example['key']) }" @click="select(@js($example['key']))"
                        @keydown.arrow-right.prevent="step(1, $el)" @keydown.arrow-left.prevent="step(-1, $el)"
                        @keydown.home.prevent="goTo(0, $el)" @keydown.end.prevent="goTo({{ count($examples) - 1 }}, $el)">
                        <x-filament::icon icon="hugeicons-code" /><span>{{ $example['label'] }}</span>
                    </button>
                @endforeach
            </div>
            <span class="fff-code-preview__language">PHP</span>
            <button type="button" class="fff-code-preview__copy" aria-label="{{ __('filament-flex-fields::catalog.copy_code') }}" title="{{ __('filament-flex-fields::catalog.copy_code') }}" @click="copy()" :disabled="{{ \Illuminate\Support\Js::from(array_map(fn ($e) => $e['unavailable'], $examples)) }}[Number(active)]">
                <x-filament::icon icon="hugeicons-copy-01" />
                <span class="fff-code-preview__copy-label" x-show="!copied && !failed">{{ __('filament-flex-fields::catalog.copy_code') }}</span>
                <span class="fff-code-preview__copy-label" x-show="copied" x-cloak>{{ __('filament-flex-fields::catalog.code_copied') }}</span>
                <span class="fff-code-preview__copy-label" x-show="failed" x-cloak>{{ __('filament-flex-fields::catalog.copy_failed') }}</span>
            </button>
        </div>
        @foreach ($examples as $example)
            <div class="fff-code-preview__panel" role="tabpanel" id="flex-example-panel-{{ $example['key'] }}" aria-labelledby="flex-example-tab-{{ $example['key'] }}" tabindex="0" x-show="active === @js($example['key'])" @if (!$loop->first) x-cloak @endif>
                @if ($example['unavailable'])
                    <p class="fff-code-preview__empty">{{ __('filament-flex-fields::catalog.example_unavailable') }}</p>
                @else
                    <pre class="fff-code-preview__source"><code>@foreach ($example['lines'] as $number => $line)<span class="fff-code-preview__line"><span class="fff-code-preview__number" aria-hidden="true">{{ $number + 1 }}</span><span>{!! $line === '' ? ' ' : $line !!}</span></span>@endforeach</code></pre>
                @endif
            </div>
        @endforeach
        <div class="fff-code-preview__footer"><span class="fff-code-preview__hint"><span class="fff-code-preview__status-dot" aria-hidden="true"></span>{{ __('filament-flex-fields::catalog.example_hint') }}</span><span aria-live="polite" x-text="copied ? @js(__('filament-flex-fields::catalog.code_copied')) : ''"></span></div>
    </div>
@endif
