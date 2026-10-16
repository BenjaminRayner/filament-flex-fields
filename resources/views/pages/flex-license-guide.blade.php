<x-filament-panels::page>
    <div class="fff-flex-detail fff-flex-license-guide">
        <x-filament-flex-fields::assets component="flex-license-guide" consumer="flex.license-guide" />
        <nav class="fff-flex-detail__breadcrumb" aria-label="{{ __('filament-flex-fields::catalog.breadcrumb') }}">
            <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundIndexPage::getUrl() }}" wire:navigate>FLEX</a>
            <x-filament::icon icon="hugeicons-arrow-right-01" />
            <span>{{ __('filament-flex-fields::licensing.title') }}</span>
        </nav>
        <header class="fff-flex-license-guide__header">
            <div><span class="fff-flex-license-guide__eyebrow">LICENSE {{ \Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment::VERSION }}</span><h1>{{ __('filament-flex-fields::licensing.title') }}</h1><p>{{ __('filament-flex-fields::licensing.intro') }}</p></div>
            @include('filament-flex-fields::catalog.theme-switch')
        </header>
        <form wire:submit="assess" class="fff-flex-license-guide__form">
            {{ $this->form }}
            <p class="fff-flex-license-guide__hint">{{ __('filament-flex-fields::licensing.selection_hint') }}</p>
            <button type="submit" class="fff-flex-license-guide__action" wire:loading.attr="disabled" wire:target="assess"><span wire:loading.remove wire:target="assess">{{ __('filament-flex-fields::licensing.assess') }}</span><span wire:loading wire:target="assess">{{ __('filament-flex-fields::licensing.assessing') }}</span><x-filament::icon icon="hugeicons-arrow-right-01" /></button>
        </form>
        @if ($this->assessment)
            @php($result = $this->assessment)
            <section class="fff-flex-license-result" data-outcome="{{ $result['outcome'] }}" role="status" aria-live="polite" aria-atomic="true" wire:key="license-result">
                <span class="fff-flex-license-guide__eyebrow">{{ __('filament-flex-fields::licensing.result') }}</span>
                <h2>{{ __('filament-flex-fields::licensing.outcomes.'.$result['outcome'].'.title') }}</h2>
                <p>{{ __('filament-flex-fields::licensing.reasons.'.$result['reason']) }}</p>
                <p>{{ __('filament-flex-fields::licensing.outcomes.'.$result['outcome'].'.detail') }}</p>
                @if (count($result['findings']) > 1)
                    <div class="fff-flex-license-result__breakdown">
                        <h3>{{ __('filament-flex-fields::licensing.breakdown') }}</h3>
                        <p>{{ __('filament-flex-fields::licensing.mixed_note') }}</p>
                        <ul>
                            @foreach ($result['findings'] as $finding)
                                <li data-license-use="{{ $finding['use'] }}" data-license-outcome="{{ $finding['outcome'] }}" wire:key="license-finding-{{ $finding['use'] }}">
                                    <h4>{{ __('filament-flex-fields::licensing.uses.'.$finding['use'].'.label') }}</h4>
                                    <strong>{{ __('filament-flex-fields::licensing.outcomes.'.$finding['outcome'].'.title') }}</strong>
                                    <p>{{ __('filament-flex-fields::licensing.reasons.'.$finding['reason']) }}</p>
                                    <p>{{ __('filament-flex-fields::licensing.outcomes.'.$finding['outcome'].'.detail') }}</p>
                                    <span class="fff-flex-license-guide__hint">LICENSE §{{ implode(', §', $finding['references']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($result['client'])<p class="fff-flex-license-result__client">{{ __('filament-flex-fields::licensing.client_note') }}</p>@endif
                @if ($result['references'])<p class="fff-flex-license-guide__hint">LICENSE §{{ implode(', §', $result['references']) }}</p>@endif
                @if (!in_array($result['outcome'], ['free', 'incomplete']))<a class="fff-flex-license-guide__action" href="{{ \Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment::CONTACT_URL }}">{{ __('filament-flex-fields::licensing.contact') }}</a>@endif
            </section>
        @endif
        <aside class="fff-flex-license-guide__terms">
            <p>{{ __('filament-flex-fields::licensing.terms_note') }}</p>
            <p>{{ __('filament-flex-fields::licensing.existing_note') }}</p>
            <div><a href="{{ \Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment::LICENSE_URL }}" target="_blank" rel="noopener noreferrer">LICENSE ↗</a><a href="{{ \Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment::COMMERCIAL_URL }}" target="_blank" rel="noopener noreferrer">{{ __('filament-flex-fields::licensing.plans') }} ↗</a><a href="{{ \Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment::CONTACT_URL }}">{{ __('filament-flex-fields::licensing.contact') }}</a></div>
        </aside>
        @include('filament-flex-fields::catalog.footer')
    </div>
</x-filament-panels::page>
