@php
    $slides = $catalog['bannerMode'] === 'slider' ? $catalog['banners'] : array_slice($catalog['banners'], 0, 1);
    $options = $catalog['bannerOptions'] ?? ['arrows' => true, 'dots' => true, 'autoplay' => false, 'interval' => 7000];
    $options['pending'] = $catalog['promotionsPending'] ?? false;
    $deckKey = hash('sha256', json_encode([$slides, $options], JSON_THROW_ON_ERROR));
@endphp
@if ($slides === [] && $options['pending'])
    <section class="fff-flex-banner" wire:key="flex-promotions-pending" role="status" aria-busy="true">
        <span class="fff-flex-sr-only">{{ __('filament-flex-fields::catalog.loading_banner') }}</span>
        <div class="fff-flex-banner__track" aria-hidden="true">
            <div class="fff-flex-banner__slide is-active">
                <div class="fff-flex-banner__skeleton">
                    <span class="fff-flex-banner__skeleton-eyebrow"></span>
                    <span class="fff-flex-banner__skeleton-title"></span>
                    <span class="fff-flex-banner__skeleton-text"></span>
                    <span class="fff-flex-banner__skeleton-text fff-flex-banner__skeleton-text--short"></span>
                    <span class="fff-flex-banner__skeleton-action"></span>
                </div>
            </div>
        </div>
    </section>
@elseif ($slides !== [])
    <section class="fff-flex-banner" wire:key="flex-promotions-{{ $deckKey }}" x-data="promotionDeck(@js(array_column($slides, 'id')), @js($options))"
        aria-roledescription="{{ count($slides) > 1 ? __('filament-flex-fields::catalog.carousel') : __('filament-flex-fields::catalog.promotion') }}" aria-label="{{ __('filament-flex-fields::catalog.promotion') }}"
        x-on:mouseenter="pause()" x-on:mouseleave="resume()" x-on:focusin="pause()" x-on:focusout="if (!$el.contains($event.relatedTarget)) resume()"
        x-on:keydown.left.prevent="move(-1)" x-on:keydown.right.prevent="move(1)"
        x-on:pointerdown="pointerDown($event)" x-on:pointerup="pointerUp($event)" x-on:pointercancel="pointerCancel()">
        <div class="fff-flex-banner__track">
            @foreach ($slides as $index => $banner)
                @php
                    $imageKeys = array_values(array_filter([$banner['backgroundImage'] ? $banner['id'].'-background' : null, $banner['image'] ? $banner['id'].'-image' : null]));
                    $initialLoading = $options['pending'] || $imageKeys !== [];
                @endphp
                <article class="fff-flex-banner__slide fff-flex-banner__slide--{{ $banner['theme'] }} fff-flex-banner__slide--{{ $banner['alignment'] }} {{ $index === 0 ? 'is-active' : '' }}"
                    data-promotion-slide="{{ $index }}" data-slide-images="{{ json_encode($imageKeys) }}" role="group" aria-label="{{ $index + 1 }} / {{ count($slides) }}" x-bind:class="{ 'is-active': active === {{ $index }} }" x-bind:aria-hidden="active !== {{ $index }}" x-bind:inert="active !== {{ $index }} || !slideReady(@js($imageKeys))" x-bind:aria-busy="!slideReady(@js($imageKeys))" @if ($index > 0) inert aria-hidden="true" @endif>
                    @if ($banner['backgroundImage'])
                        <img class="fff-flex-banner__background fff-flex-banner__background--{{ $banner['backgroundPosition'] }}" src="{{ $banner['backgroundImage'] }}" alt="" decoding="async" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" referrerpolicy="no-referrer"
                            data-promotion-image="{{ $banner['id'] }}-background" x-on:load="imageSettled(@js($banner['id'].'-background'))" x-on:error="imageSettled(@js($banner['id'].'-background'), false)" x-bind:class="{ 'is-failed': imageStates[@js($banner['id'].'-background')] === 'failed' }" />
                        <span class="fff-flex-banner__overlay" style="opacity:{{ $banner['overlayOpacity'] }}" aria-hidden="true"></span>
                    @endif
                    <div class="fff-flex-banner__body {{ $initialLoading ? 'is-loading' : '' }}" x-bind:class="{ 'is-loading': !slideReady(@js($imageKeys)) }">
                        <span class="fff-flex-banner__eyebrow">{{ $banner['eyebrow'] ?? __('filament-flex-fields::catalog.promotion') }}</span>
                        <h2 class="fff-flex-banner__title">{{ $banner['title'] }}</h2>
                        <p class="fff-flex-banner__text">{{ $banner['description'] }}</p>
                        <a class="fff-flex-banner__action" href="{{ $banner['url'] }}" target="_blank" rel="noopener noreferrer">{{ $banner['action'] }}<x-filament::icon icon="hugeicons-arrow-up-right-01" /></a>
                    </div>
                    @if ($banner['image'])
                        <div class="fff-flex-banner__artwork">
                            <img class="fff-flex-banner__image" src="{{ $banner['image'] }}" alt="" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async" referrerpolicy="no-referrer"
                                data-promotion-image="{{ $banner['id'] }}-image" x-on:load="imageSettled(@js($banner['id'].'-image'))" x-on:error="imageSettled(@js($banner['id'].'-image'), false)" x-bind:class="{ 'is-failed': imageStates[@js($banner['id'].'-image')] === 'failed' }" />
                        </div>
                    @endif
                    <div class="fff-flex-banner__skeleton" @if (!$initialLoading) style="display:none" @endif x-show="!slideReady(@js($imageKeys))" role="status">
                        <span class="fff-flex-sr-only">{{ __('filament-flex-fields::catalog.loading_banner') }}</span>
                        <span class="fff-flex-banner__skeleton-eyebrow"></span>
                        <span class="fff-flex-banner__skeleton-title"></span>
                        <span class="fff-flex-banner__skeleton-text"></span>
                        <span class="fff-flex-banner__skeleton-text fff-flex-banner__skeleton-text--short"></span>
                        <span class="fff-flex-banner__skeleton-action"></span>
                    </div>
                </article>
            @endforeach
        </div>
            @if (count($slides) > 1 && $options['arrows'])
                <button type="button" class="fff-flex-banner__arrow fff-flex-banner__arrow--previous" x-on:click="move(-1); restart()" aria-label="{{ __('filament-flex-fields::catalog.previous_banner') }}"><x-filament::icon icon="hugeicons-arrow-left-01" preserveAspectRatio="none" /></button>
                <button type="button" class="fff-flex-banner__arrow fff-flex-banner__arrow--next" x-on:click="move(1); restart()" aria-label="{{ __('filament-flex-fields::catalog.next_banner') }}"><x-filament::icon icon="hugeicons-arrow-right-01" preserveAspectRatio="none" /></button>
            @endif
        @if (count($slides) > 1 && $options['autoplay'])
            <button type="button" class="fff-flex-banner__timer" x-on:click="toggleAutoplay()" x-bind:aria-label="userPaused ? @js(__('filament-flex-fields::catalog.resume_banners')) : @js(__('filament-flex-fields::catalog.pause_banners'))" aria-label="{{ __('filament-flex-fields::catalog.pause_banners') }}" x-bind:aria-pressed="userPaused">
                <template x-for="key in [cycle]" x-bind:key="key">
                    <svg class="fff-flex-banner__timer-ring" viewBox="0 0 36 36" aria-hidden="true"><circle class="fff-flex-banner__timer-track" cx="18" cy="18" r="15" /><circle class="fff-flex-banner__timer-progress" cx="18" cy="18" r="15" pathLength="100" x-bind:style="{ animationDuration: `${duration}ms`, animationPlayState: running ? 'running' : 'paused' }" /></svg>
                </template>
                <span x-show="!userPaused"><x-filament::icon icon="hugeicons-pause" /></span>
                <span x-show="userPaused" style="display:none"><x-filament::icon icon="hugeicons-play" /></span>
            </button>
        @endif
        @if (count($slides) > 1)
            <output class="fff-flex-sr-only" aria-live="{{ $options['autoplay'] ? 'off' : 'polite' }}" x-text="`${active + 1} / ${count}`">1 / {{ count($slides) }}</output>
        @endif
        @if (count($slides) > 1 && $options['dots'])
            <div class="fff-flex-banner__dots" role="group" aria-label="{{ __('filament-flex-fields::catalog.banner_slides') }}">
                @foreach ($slides as $index => $banner)
                    <button type="button" class="fff-flex-banner__dot {{ $index === 0 ? 'is-active' : '' }}" x-bind:class="{ 'is-active': active === {{ $index }}, 'is-outside': dotClass({{ $index }}) === 'is-outside', 'is-edge': dotClass({{ $index }}) === 'is-edge' }" x-bind:aria-pressed="active === {{ $index }}" aria-label="{{ __('filament-flex-fields::catalog.banner_slide', ['number' => $index + 1]) }}" x-on:click="choose({{ $index }})"><span></span></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
