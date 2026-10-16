<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Facades\FilamentAsset;

it('registers core and playground css asset ids', function () {
    expect(FlexFieldAssets::CORE_STYLESHEET_ID)->toBe('flex-fields-core')
        ->and(FlexFieldAssets::PLAYGROUND_STYLESHEET_ID)->toBe('flex-fields-playground')
        ->and(FlexFieldAssets::stylesheetId('phone-field'))->toBe('flex-fields-phone-field');
});

it('builds core and playground css bundles', function () {
    $core = __DIR__.'/../../resources/dist/css/core.css';
    $playground = __DIR__.'/../../resources/dist/css/playground.css';

    expect(is_file($core))->toBeTrue()
        ->and(is_file($playground))->toBeTrue()
        ->and(filesize($core))->toBeGreaterThan(1000)
        ->and(filesize($playground))->toBeGreaterThan(100);
});

it('keeps the core bundle under the gzip budget', function () {
    $corePath = __DIR__.'/../../resources/dist/css/core.css';
    $gzipBytes = strlen((string) gzencode((string) file_get_contents($corePath), 9));
    $gzipKb = $gzipBytes / 1024;

    expect($gzipKb)->toBeLessThan(50);
});

it('uses root css tokens for icon column label colors in dark mode', function () {
    $iconColumnCss = file_get_contents(__DIR__.'/../../resources/dist/css/icon-column.css');
    $coreCss = file_get_contents(__DIR__.'/../../resources/dist/css/core.css');

    expect($iconColumnCss)
        ->toContain('color:var(--fff-icon-column-text)')
        ->toContain('color:var(--fff-icon-column-muted)')
        ->not->toContain('dark:text-white');

    expect($coreCss)
        ->toContain('--fff-icon-column-text:#fafafa')
        ->toContain('--fff-icon-column-muted:#a1a1aa');
});

it('declares filament\'s class-based dark variant on every tailwind css entry point', function () {
    // Regression: without `@variant dark (&:where(.dark, .dark *));`, Tailwind
    // v4 defaults `dark:*` utilities to `@media (prefers-color-scheme: dark)`,
    // which fires from the OS setting instead of Filament's `.dark` class and
    // leaks dark-mode styles (e.g. `dark:text-white`) into light mode.
    $entryPoints = [
        'core.css' => __DIR__.'/../../resources/css/core.css',
        'playground.css' => __DIR__.'/../../resources/css/playground.css',
        'utilities-baseline.css' => __DIR__.'/../../resources/css/utilities-baseline.css',
    ];

    foreach ($entryPoints as $label => $path) {
        expect(file_get_contents($path))
            ->toContain('@variant dark (&:where(.dark, .dark *));');
    }
});

it('keeps video field glass controls on a single border with circular buttons', function () {
    $sourceCss = file_get_contents(__DIR__.'/../../resources/css/components/video-field.css');

    expect($sourceCss)
        ->toContain('--fff-video-field-control-btn-radius: 100%')
        ->toContain('--fff-video-field-control-pill-radius: 9999px')
        ->toContain('--fff-video-field-glass-blur: blur(16px)')
        ->toContain('--fff-video-field-glass-saturate: saturate(1.5)')
        ->toContain('oklch(100% 0 0 / 0.1)')
        ->toContain('.fff-video-field__glass-btn {')
        ->toContain('border-radius: var(--fff-video-field-control-btn-radius)')
        ->toContain('--fff-video-field-glass-inset')
        ->toContain('.fff-video-field__glass-btn::after')
        ->toContain('box-shadow: var(--fff-video-field-glass-inset)')
        ->toContain('--fff-video-slider-pointer')
        ->toContain('.fff-video-field__progress-wrap:is(.is-pointing, :focus-visible) .fff-video-field__progress-thumb')
        ->toContain('.fff-video-field__progress-wrap.is-pointing:not(.is-disabled) .fff-video-field__progress-thumbnail')
        ->toContain('.fff-video-field__settings-item')
        ->toContain('.fff-video-field__settings-separator')
        ->toContain('.fff-video-field__settings-viewport')
        ->toContain('.fff-video-field__control-tip.has-tooltips.is-tooltip-visible')
        ->toContain('.fff-video-field__tooltip-label')
        ->toContain('--fff-video-field-radius: 1.5rem')
        ->toContain('--fff-video-field-controls-inset: 0.75rem')
        ->toContain('--fff-video-slider-track-h: 0.25rem')
        ->toContain('height: var(--fff-video-slider-track-h)')
        ->toContain('transform-origin: bottom')
        ->not->toContain('.fff-video-field__volume--expand')
        ->not->toContain('.dark .fff-video-field__frame')
        ->toContain('.fff-video-field__settings-panel:not([data-submenu])')
        ->toContain('.fff-video-field__settings-panel[data-submenu]')
        ->toContain('contain: layout style paint')
        ->toContain('.fff-video-field__tooltip')
        ->not->toContain('border: 1px solid var(--fff-video-field-glass-border)');
});

it('compiles dark utilities to a class selector instead of a media query', function () {
    $coreCss = file_get_contents(__DIR__.'/../../resources/dist/css/core.css');
    $playgroundCss = file_get_contents(__DIR__.'/../../resources/dist/css/playground.css');
    $videoFieldCss = file_get_contents(__DIR__.'/../../resources/dist/css/video-field.css');

    foreach ([$coreCss, $playgroundCss, $videoFieldCss] as $css) {
        expect($css)->not->toContain('@media(prefers-color-scheme:dark)');
    }

    expect($coreCss)->toContain(':where(.dark,.dark *)');
});

it('keeps hold confirm action styles in the lazy bundle', function () {
    $coreCss = file_get_contents(__DIR__.'/../../resources/dist/css/core.css');
    $holdConfirmCss = file_get_contents(__DIR__.'/../../resources/dist/css/hold-confirm-action.css');

    expect($coreCss)
        ->not->toContain('.fff-hold-confirm-action')
        ->not->toContain('.fff-hold-confirm-action__overlay');

    expect($holdConfirmCss)
        ->toContain('.fff-hold-confirm-action')
        ->toContain('.fff-hold-confirm-action__overlay');
});

it('keeps moved component styles out of the core bundle', function () {
    $coreCss = file_get_contents(__DIR__.'/../../resources/dist/css/core.css');

    expect($coreCss)
        ->not->toContain('.fff-switch')
        ->not->toContain('.item-card')
        ->not->toContain('.fff-choice-cards')
        ->not->toContain('.fff-rating-field')
        ->not->toContain('.fff-color-swatch')
        ->not->toContain('.fff-track-slider')
        ->not->toContain('.fff-segment-control');
});

it('builds cover card overlay styles in the lazy bundle', function () {
    $sourceCss = file_get_contents(__DIR__.'/../../resources/css/components/cover-card.css');
    $coverCardCss = file_get_contents(__DIR__.'/../../resources/dist/css/cover-card.css');

    expect($sourceCss)
        ->toContain('-webkit-backdrop-filter: blur(10px) saturate(1.5)')
        ->toContain('backdrop-filter: blur(10px) saturate(1.5)')
        ->toContain('mask-image: linear-gradient(180deg, #000 50%, #0000 100%)')
        ->toContain('mask-image: linear-gradient(0deg, #000 50%, #0000 100%)')
        ->toContain('.fff-cover-card::before')
        ->toContain('aspect-ratio: var(--fff-cover-card-aspect-ratio, auto)');

    expect($coverCardCss)
        ->toContain('.fff-cover-card__overlay--top')
        ->toContain('.fff-cover-card__overlay--bottom')
        ->toContain('.fff-cover-card__overlay')
        ->toContain('backdrop-filter:blur(10px)saturate(1.5)')
        ->toContain('--fff-cover-card-aspect-ratio');
});

it('keeps cover card styles out of the core bundle', function () {
    $coreCss = file_get_contents(__DIR__.'/../../resources/dist/css/core.css');

    expect($coreCss)->not->toContain('.fff-cover-card__overlay--top');
});

it('builds lazy component css bundles for x-load form fields', function () {
    foreach (FlexFieldAssets::LAZY_COMPONENT_STYLESHEETS as $component) {
        $path = __DIR__.'/../../resources/dist/css/'.$component.'.css';

        expect(is_file($path))->toBeTrue("Missing CSS bundle for {$component}");
    }
});

it('registers asset injector script alongside lazy css assets', function () {
    $registered = collect(FilamentAsset::getScripts(['janczakb/filament-flex-fields']))
        ->map(fn ($asset) => $asset->getId())
        ->all();

    expect($registered)->toContain(FlexFieldAssets::ASSET_INJECTOR_SCRIPT_ID)
        ->and($registered)->toContain(FlexFieldAssets::SEGMENT_OVERFLOW_SSR_SCRIPT_ID);
});

it('provides built segment overflow ssr bundle for inline shell boot', function () {
    expect(FlexFieldAssets::segmentOverflowSsrInlineContents())
        ->toContain('data-fff-segment-overflow')
        ->toContain('ssrScrollPositioned');
});

it('provides built timezone browser ssr boot bundle for inline trigger paint', function () {
    expect(FlexFieldAssets::timezoneBrowserSsrInlineContents())
        ->toContain('data-fff-timezone-catalog')
        ->toContain('fffDetectedTimezone');
});

it('registers timezone browser ssr boot script asset', function () {
    $registered = collect(FilamentAsset::getScripts(['janczakb/filament-flex-fields']))
        ->map(fn ($asset) => $asset->getId())
        ->all();

    expect($registered)->toContain(FlexFieldAssets::TIMEZONE_BROWSER_SSR_BOOT_SCRIPT_ID);
});

it('registers lazy stylesheets for every lazy component asset id', function () {
    $registered = collect(FilamentAsset::getStyles(['janczakb/filament-flex-fields']))
        ->map(fn ($asset) => $asset->getId())
        ->all();

    expect($registered)->toContain(FlexFieldAssets::CORE_STYLESHEET_ID);

    foreach (FlexFieldAssets::LAZY_COMPONENT_STYLESHEETS as $component) {
        expect($registered)->toContain(FlexFieldAssets::stylesheetId($component));
    }
});

it('renders lazy stylesheet links in form component blades', function () {
    $blade = file_get_contents(__DIR__.'/../../resources/views/forms/components/phone-field.blade.php');

    expect($blade)->toContain('partials.load-stylesheet')
        ->and($blade)->toContain("'component' => 'phone-field'");
});
