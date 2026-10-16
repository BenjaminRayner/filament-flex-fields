<aside class="fff-flex-license-note" aria-label="{{ __('filament-flex-fields::licensing.title') }}">
    <div class="fff-flex-license-note__art" aria-hidden="true"><x-filament::icon icon="hugeicons-certificate-01" /></div>
    <div class="fff-flex-license-note__copy">
        <span class="fff-flex-license-note__eyebrow">FLEX · {{ __('filament-flex-fields::licensing.license_label') }}</span>
        <h2>{{ __('filament-flex-fields::licensing.notice_title') }}</h2>
        <p>{{ __('filament-flex-fields::licensing.notice') }}</p>
        <div class="fff-flex-license-note__scopes"><span>{{ __('filament-flex-fields::licensing.scope_internal') }}</span><span>{{ __('filament-flex-fields::licensing.scope_hosted') }}</span><span>{{ __('filament-flex-fields::licensing.scope_custom') }}</span></div>
    </div>
    <a class="fff-flex-license-note__action" href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\FlexLicenseGuidePage::getUrl() }}" wire:navigate>{{ __('filament-flex-fields::licensing.check_scope') }}<x-filament::icon icon="hugeicons-arrow-right-01" /></a>
</aside>
