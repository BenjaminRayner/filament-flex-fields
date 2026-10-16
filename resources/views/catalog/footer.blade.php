<footer class="fff-flex-footer">
    <a href="{{ \Bjanczak\FilamentFlexFields\Filament\Pages\FlexLicenseGuidePage::getUrl() }}" wire:navigate>{{ __('filament-flex-fields::licensing.title') }}</a>
    <div><span class="fff-flex-footer__brand">FLEX</span><span>{{ __('filament-flex-fields::catalog.footer_tagline') }}</span></div>
    <a href="{{ \Bjanczak\FilamentFlexFields\Support\Catalog\Promotions\PromotionFeed::WEBSITE }}" target="_blank" rel="noopener noreferrer">{{ __('filament-flex-fields::catalog.footer_project') }}<x-filament::icon icon="hugeicons-arrow-up-right-01" /></a>
</footer>
