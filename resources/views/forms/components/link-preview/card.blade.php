        <div
            class="fff-link-preview__card fff-link-preview__card--{{ $configuration['previewLayout'] }}"
            x-show="shouldShowCard"
            @if (! $hasInitialPreview) style="display:none" @endif
            aria-live="polite"
            x-bind:class="{
                'fff-link-preview__card--horizontal': previewLayout === 'horizontal',
                'fff-link-preview__card--vertical': previewLayout === 'vertical',
                'fff-link-preview__card--card': previewLayout === 'card',
                'fff-link-preview__card--no-thumb': ! shouldShowThumb,
            }"
            data-revealed="{{ $hasInitialPreview ? 'true' : 'false' }}" x-bind:data-revealed="isRevealed ? 'true' : 'false'"
            data-loading="false" x-bind:data-loading="showSkeleton ? 'true' : 'false'"
            x-bind:aria-busy="showSkeleton ? 'true' : 'false'"
            x-bind:aria-label="isRevealed && preview.title ? preview.title : null"
        >
            <div
                class="fff-link-preview__thumb"
                x-show="shouldShowThumb"
                @if (empty($initialPreview['image'])) style="display:none" @endif
                aria-hidden="true"
            >
                <div class="fff-link-preview__skeleton-thumb" x-show="showSkeleton" @if ($hasInitialPreview) style="display:none" @endif></div>

                <img
                    class="fff-link-preview__image"
                    x-show="isRevealed && preview.image"
                    x-bind:src="preview.image"
                    referrerpolicy="no-referrer"
                    alt=""
                    decoding="async"
                    x-on:error="onImageError()"
                >
            </div>

            <div class="fff-link-preview__content">
                <div class="fff-link-preview__skeleton" x-show="showSkeleton" @if ($hasInitialPreview) style="display:none" @endif aria-hidden="true">
                    <span class="fff-link-preview__skeleton-line fff-link-preview__skeleton-line--title"></span>
                    <span
                        class="fff-link-preview__skeleton-line fff-link-preview__skeleton-line--description"
                        x-show="previewLayout === 'horizontal'"
                    ></span>
                    <span class="fff-link-preview__skeleton-line fff-link-preview__skeleton-line--domain"></span>
                </div>

                <div class="fff-link-preview__meta" x-show="isRevealed" @if (! $hasInitialPreview) style="display:none" @endif>
                    <p
                        class="fff-link-preview__title"
                        x-show="preview.title"
                        x-text="preview.title"
                    >{{ $initialPreview['title'] ?? '' }}</p>

                    <p
                        class="fff-link-preview__description"
                        x-show="previewLayout === 'horizontal' && preview.description"
                        @if ($configuration['previewLayout'] !== 'horizontal' || empty($initialPreview['description'])) style="display:none" @endif
                        x-text="preview.description"
                    >{{ $initialPreview['description'] ?? '' }}</p>

                    <a
                        class="fff-link-preview__domain"
                        x-show="showVisitLink && preview.domain && hasPreview && canVisit"
                        @if (! $configuration['showVisitLink']) style="display:none" @endif
                        x-bind:href="visitUrl" @if ($hasInitialPreview) href="{{ $preview->value }}" @endif
                        target="_blank"
                        rel="noopener noreferrer"
                        x-bind:aria-label="labels.visit"
                    >
                        <span
                            class="fff-link-preview__domain-icon"
                            aria-hidden="true"
                        >
                            {{ \Filament\Support\generate_icon_html($visitIcon, size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
                        </span>
                        <span x-text="preview.domain">{{ parse_url($preview->value, PHP_URL_HOST) ?? '' }}</span>
                    </a>

                    <span
                        class="fff-link-preview__domain fff-link-preview__domain--text"
                        x-show="! showVisitLink && preview.domain && hasPreview"
                        @if ($configuration['showVisitLink']) style="display:none" @endif
                    >
                        <span
                            class="fff-link-preview__domain-icon"
                            aria-hidden="true"
                        >
                            {{ \Filament\Support\generate_icon_html($visitIcon, size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
                        </span>
                        <span x-text="preview.domain">{{ parse_url($preview->value, PHP_URL_HOST) ?? '' }}</span>
                    </span>
                </div>
            </div>
        </div>

