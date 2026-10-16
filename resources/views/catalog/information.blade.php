<section wire:ignore @class(['fff-flex-information', 'fff-flex-information--tab' => $inTab ?? false]) aria-labelledby="flex-information">
    <h2 id="flex-information" class="fff-flex-information__title">{{ __('filament-flex-fields::catalog.information') }}</h2>
    <dl>
        <dt>{{ __('filament-flex-fields::catalog.developer') }}</dt>
        <dd>{{ $entry['author'] }} @if ($entry['official']) @include('filament-flex-fields::catalog.verified') @endif</dd>
        <dt>{{ __('filament-flex-fields::catalog.category') }}</dt>
        <dd>{{ $entry['categoryLabel'] }}</dd>
        @if ($entry['type'] === 'plugin' && $entry['version'] !== null)
            <dt>{{ __('filament-flex-fields::catalog.version') }}</dt><dd>{{ $entry['version'] }}</dd>
        @endif
        @foreach (['website' => 'website', 'privacyPolicy' => 'privacy_policy', 'termsOfService' => 'terms_of_service'] as $key => $translation)
            @if ($entry[$key] !== null)
                <dt>{{ __('filament-flex-fields::catalog.'.$translation) }}</dt>
                <dd><a href="{{ $entry[$key] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('filament-flex-fields::catalog.'.$translation) }}"><x-filament::icon icon="hugeicons-arrow-up-right-01" /></a></dd>
            @endif
        @endforeach
    </dl>
</section>
