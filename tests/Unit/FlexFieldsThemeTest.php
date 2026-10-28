<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\Density;
use Bjanczak\FilamentFlexFields\Facades\FlexFields;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Theme\FlexFieldsTheme;
use Filament\Panel;

it('defaults density to comfortable', function (): void {
    $theme = app(FlexFieldsTheme::class);

    expect($theme->density())->toBe(Density::Comfortable)
        ->and(Density::default())->toBe(Density::Comfortable);
});

it('exposes density enum string values', function (): void {
    expect(Density::Compact->value)->toBe('compact')
        ->and(Density::Comfortable->value)->toBe('comfortable')
        ->and(Density::Spacious->value)->toBe('spacious');
});

it('sets and reads density on the theme runtime', function (): void {
    $theme = app(FlexFieldsTheme::class);

    $theme->setDensity(Density::Compact);

    expect($theme->density())->toBe(Density::Compact);

    $theme->setDensity('spacious');

    expect($theme->density())->toBe(Density::Spacious);
});

it('manages theme overrides via set merge and replace', function (): void {
    $theme = app(FlexFieldsTheme::class);

    $theme->setTheme(['primary' => 'rgb(255 0 0)']);

    expect($theme->theme())->toBe(['primary' => 'rgb(255 0 0)']);

    $theme->mergeTheme(['radius' => '1rem']);

    expect($theme->theme())->toBe([
        'primary' => 'rgb(255 0 0)',
        'radius' => '1rem',
    ]);

    $theme->setTheme([]);

    expect($theme->theme())->toBe([]);
});

it('maps theme keys to html attributes and css variables', function (): void {
    $theme = app(FlexFieldsTheme::class);

    $theme
        ->setDensity(Density::Compact)
        ->setTheme([
            'primary' => 'rgb(34 197 94)',
            'radius' => '0.5rem',
            '--fff-field-bg' => '#fafafa',
        ]);

    expect($theme->toHtmlAttributes())->toBe([
        'data-fff-density' => 'compact',
        'data-fff-theme' => json_encode([
            'primary' => 'rgb(34 197 94)',
            'radius' => '0.5rem',
            '--fff-field-bg' => '#fafafa',
        ], JSON_THROW_ON_ERROR),
    ])->and($theme->toCssVariables())->toBe([
        '--fff-field-focus-border' => 'rgb(34 197 94)',
        '--fff-global-radius' => '0.5rem',
        '--fff-field-bg' => '#fafafa',
    ]);
});

it('omits data-fff-theme when theme is empty', function (): void {
    $theme = app(FlexFieldsTheme::class);

    $theme->setDensity(Density::Comfortable)->setTheme([]);

    expect($theme->toHtmlAttributes())->toBe([
        'data-fff-density' => 'comfortable',
    ]);
});

it('reads density and theme defaults from config helpers', function (): void {
    config()->set('filament-flex-fields.ui.density', 'spacious');
    config()->set('filament-flex-fields.ui.theme', ['primary' => 'rgb(0 0 0)']);

    expect(FlexFieldsConfig::getDensity())->toBe(Density::Spacious)
        ->and(FlexFieldsConfig::getTheme())->toBe(['primary' => 'rgb(0 0 0)']);
});

it('resolves the flex fields facade to the theme singleton', function (): void {
    app(FlexFieldsTheme::class)->setDensity(Density::Compact);

    expect(FlexFields::density())->toBe(Density::Compact)
        ->and(FlexFields::getFacadeRoot())->toBeInstanceOf(FlexFieldsTheme::class);
});

it('bootstraps theme from config in the service provider', function (): void {
    config()->set('filament-flex-fields.ui.density', 'compact');
    config()->set('filament-flex-fields.ui.theme', ['radius' => '1.25rem']);

    app()->forgetInstance(FlexFieldsTheme::class);

    $theme = app(FlexFieldsTheme::class);

    expect($theme->density())->toBe(Density::Compact)
        ->and($theme->theme())->toBe(['radius' => '1.25rem']);
});

it('renders configured tokens with precedence over root defaults after merging panel overrides', function (): void {
    config()->set('filament-flex-fields.ui.theme', [
        'field_bg' => 'rgb(240 241 242)',
        '--fff-field-border-width' => '1px',
    ]);
    app()->forgetInstance(FlexFieldsTheme::class);

    FilamentFlexFieldsPlugin::make()->theme([
        '--fff-field-border-width' => 0,
        '--fff-field-shadow' => '0 0 0 1px rgb(3 7 18 / 0.1)',
    ])->boot(Panel::make());

    $html = view('filament-flex-fields::partials.flex-fields-theme')->render();
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $style = (new DOMXPath($document))->query('//style[@id="fff-theme-overrides"]');

    expect($style->length)->toBe(1)
        ->and($style->item(0)->textContent)->toContain('html:root {',
            '--fff-field-bg: rgb(240 241 242);',
            '--fff-field-border-width: 0;',
            '--fff-field-shadow: 0 0 0 1px rgb(3 7 18 / 0.1);')
        ->not->toContain('!important');
});

it('preserves application CSP nonces on rendered theme styles and scripts', function (): void {
    app(FlexFieldsTheme::class)->setTheme(['field_bg' => '#fafafa']);
    request()->attributes->set('fff.csp_nonce', 'theme-test-nonce-1234');
    app()->forgetInstance(AssetDeliveryContext::class);

    $document = new DOMDocument;
    $document->loadHTML(view('filament-flex-fields::partials.flex-fields-theme')->render(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $nodes = (new DOMXPath($document))->query('//style[@id="fff-theme-overrides"] | //script');

    expect($nodes->length)->toBe(2);
    foreach ($nodes as $node) {
        expect($node->getAttribute('nonce'))->toBe('theme-test-nonce-1234');
    }
});

it('does not emit a theme stylesheet when the application has no token overrides', function (): void {
    app(FlexFieldsTheme::class)->setTheme([]);

    expect(view('filament-flex-fields::partials.flex-fields-theme')->render())
        ->not->toContain('id="fff-theme-overrides"');
});
