@if (($catalog['sponsors'] ?? []) !== [])
    <aside class="fff-flex-sponsors" aria-label="{{ __('filament-flex-fields::catalog.top_sponsors') }}">
        <div class="fff-flex-sponsors__content">
            <div class="fff-flex-sponsors__heading">
                <span class="fff-flex-sponsors__title"><span class="fff-flex-sponsors__title--long">{{ __('filament-flex-fields::catalog.top_sponsors') }}</span><span class="fff-flex-sponsors__title--short">{{ __('filament-flex-fields::catalog.sponsors') }}</span></span>
                @if ($catalog['sponsorsPreview'] ?? false)
                    <small class="fff-flex-sponsors__preview">DEMO</small>
                @endif
            </div>
            <div class="fff-flex-sponsors__people" x-data="sponsorRail()" wire:key="flex-sponsors-{{ sha1(json_encode($catalog['sponsors'])) }}">
                <div class="fff-flex-sponsors__rail" :class="{ 'has-before': before, 'has-after': after }">
                <div class="fff-flex-sponsors__profiles" x-ref="profiles" tabindex="0" role="group" aria-label="{{ __('filament-flex-fields::catalog.sponsors_scroll') }}"
                    @scroll.passive="scheduleMeasure()" @keydown.arrow-left.prevent="move(-1)" @keydown.arrow-right.prevent="move(1)" @keydown.home.prevent="goToEdge(false)" @keydown.end.prevent="goToEdge(true)">
                    @foreach ($catalog['sponsors'] as $sponsor)
                        <a class="fff-flex-sponsor fff-flex-sponsor--{{ $sponsor['rank'] }}" href="{{ $sponsor['profile'] }}" target="_blank" rel="noopener noreferrer"
                            aria-label="{{ $sponsor['name'] }} · {{ $sponsor['rankLabel'] }}" x-tooltip="{ content: @js($sponsor['name']), theme: 'fff', arrow: false, allowHTML: false, placement: 'top' }">
                            <span class="fff-flex-sponsor__initials" aria-hidden="true">{{ $sponsor['initials'] }}</span>
                            @if ($sponsor['avatar'] ?? null)
                                <img src="{{ $sponsor['avatar'] }}" alt="" width="32" height="32" loading="lazy" decoding="async" referrerpolicy="no-referrer" x-on:error="$el.hidden = true" />
                            @endif
                        </a>
                    @endforeach
                </div>
                    <button type="button" class="fff-flex-sponsors__scroll fff-flex-sponsors__scroll--previous" x-show="before" x-cloak @click="move(-1)" aria-label="{{ __('filament-flex-fields::catalog.previous_sponsors') }}"><x-filament::icon icon="hugeicons-arrow-left-01" /></button>
                    <button type="button" class="fff-flex-sponsors__scroll fff-flex-sponsors__scroll--next" x-show="after" x-cloak @click="move(1)" aria-label="{{ __('filament-flex-fields::catalog.next_sponsors') }}"><x-filament::icon icon="hugeicons-arrow-right-01" /></button>
                </div>
                <a class="fff-flex-sponsor fff-flex-sponsor--join" href="{{ \Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionFeed::SPONSORSHIP }}" target="_blank" rel="noopener noreferrer"
                    aria-label="{{ __('filament-flex-fields::catalog.become_sponsor') }}" x-tooltip="{ content: @js(__('filament-flex-fields::catalog.become_sponsor')), theme: 'fff', arrow: false, allowHTML: false, placement: 'top' }"><x-filament::icon icon="hugeicons-add-01" /></a>
            </div>
        </div>
    </aside>
@endif
