<article class="fff-flex-card" x-show="visible(@js($entry['id']))">
    <a href="{{ $entry['url'] }}" class="fff-flex-card__link">
        <span class="fff-flex-card__art" aria-hidden="true">
            @if ($entry['image'])
                <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" />
            @else
                <x-filament::icon :icon="$entry['icon'] ?? 'hugeicons-grid-view'" />
            @endif
        </span>
        <span class="fff-flex-card__body">
            <span class="fff-flex-card__title">{{ $entry['label'] }}</span>
            <span class="fff-flex-card__author" title="{{ $entry['author'] }}">
                <span>{{ $entry['author'] }}</span>
                @if ($entry['official']) @include('filament-flex-fields::catalog.verified') @endif
            </span>
            <span class="fff-flex-card__description">{{ $entry['description'] }}</span>
        </span>
    </a>
    @if ($entry['installed'])
        <a href="{{ $entry['url'] }}" class="fff-flex-card__status" aria-label="{{ __('filament-flex-fields::catalog.explore_named', ['name' => $entry['label']]) }}">{{ __('filament-flex-fields::catalog.'.($entry['official'] ? 'included' : 'installed')) }}</a>
    @else
        <a href="{{ $entry['repository'] ?? $entry['url'] }}" @if($entry['repository']) target="_blank" rel="noopener noreferrer" @endif class="fff-flex-card__status fff-flex-card__status--install" aria-label="{{ __('filament-flex-fields::catalog.install_named', ['name' => $entry['label']]) }}">{{ __('filament-flex-fields::catalog.install_short') }}</a>
    @endif
</article>
