<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontPage;
use Bjanczak\FilamentFlexFields\Support\Fonts\Providers\GoogleFonts;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\FontSelectFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\Schema\Configuration\BuiltinConfigurationContract;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;

it('ships a bounded unique catalog including Noto and supported variants', function () {
    $fonts = GoogleFonts::families();
    expect(count($fonts))->toBeGreaterThan(1800)->toBeLessThan(10000)
        ->and($fonts)->toHaveKeys(['google:inter', 'google:roboto', 'google:noto-sans-arabic'])
        ->and(GoogleFonts::revision())->toMatch('/^[a-f0-9]{64}$/');
    foreach ($fonts as $id => $font) {
        expect($id)->toBe($font->id)->toMatch('/^google:[a-z0-9-]+$/')
            ->and($font->category)->toBeIn(FontCatalog::CATEGORIES)
            ->and($font->variants)->not->toBeEmpty();
        foreach ($font->variants as $variant) {
            expect((int) $variant)->toBeGreaterThan(0)->toBeLessThanOrEqual(1000);
        }
    }
});

it('paginates without duplicates and binds cursors to query and field policy', function () {
    $field = FontSelect::make('font')->perPage(32);
    $first = $field->getFontSelectPage();
    $second = $field->getFontSelectPage(cursor: $first['cursor']);
    expect($first['items'])->toHaveCount(32)->and($first['hasMore'])->toBeTrue()
        ->and(array_intersect(array_column($first['items'], 'value'), array_column($second['items'], 'value')))->toBe([]);
    expect(fn () => $field->getFontSelectPage(query: 'Lora', cursor: $first['cursor']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $field->fonts(['google:inter'])->getFontSelectPage(cursor: $first['cursor']))->toThrow(InvalidArgumentException::class);
});

it('supports literal filters and actual available weight fallback', function () {
    $field = FontSelect::make('font')->fonts(['google:inter', 'google:lora'])->categories(['serif'])->previewWeight(550);
    $page = $field->getFontSelectPage();
    expect(array_column($page['items'], 'value'))->toBe(['google:lora'])
        ->and($page['items'][0]['font']['weight'])->toBe(550)
        ->and($field->getFontSelectionPreview('google:inter'))->toBeNull();
});

it('rejects forged stored state instead of persisting metadata or a resource URL', function () {
    $field = FontSelect::make('font')->label('Font')->fonts(['google:inter'])->required();
    foreach ([null, 'google:roboto', 'Inter', 'https://fonts.google.com', ['value' => 'google:inter']] as $value) {
        expect(Validator::make(['font' => $value], ['font' => $field->getValidationRules()])->fails())->toBeTrue();
    }
    expect(Validator::make(['font' => 'google:inter'], ['font' => $field->getValidationRules()])->passes())->toBeTrue();
});

it('allows optional empty state but still rejects wrong scalar shapes', function () {
    $field = FontSelect::make('font');
    foreach ([null, ''] as $value) {
        expect(Validator::make(['font' => $value], ['font' => $field->getValidationRules()])->passes())->toBeTrue();
    }
    foreach ([123, ['google:inter']] as $value) {
        expect(Validator::make(['font' => $value], ['font' => $field->getValidationRules()])->fails())->toBeTrue();
    }
});

it('restricts local previews to bounded same-origin font files', function () {
    $font = ['family' => 'Brand', 'url' => url('/fonts/brand.woff2')];
    expect(FontCatalog::families(['local'], ['brand' => $font]))->toHaveKey('local:brand');
    foreach (['https://evil.example/font.woff2', '//evil.example/font.woff2', url('/fonts/a.svg'), 'data:font/woff2;base64,abc', url('/fonts/a.woff2').'#fragment'] as $url) {
        expect(fn () => FontCatalog::families(['local'], ['brand' => [...$font, 'url' => $url]]))->toThrow(InvalidArgumentException::class);
    }
});

it('admits stored font configuration through the same bounded policy', function () {
    BuiltinConfigurationContract::assert(FieldType::FontSelect, ['fonts' => ['google:inter'], 'category_tabs' => ['sans-serif'], 'preview_text' => 'Hello']);
    foreach ([['per_page' => 2000], ['providers' => ['other']], ['preview_weight' => 1001], ['categories' => ['random']], ['preview_text' => str_repeat('a', 129)]] as $config) {
        expect(fn () => BuiltinConfigurationContract::assert(FieldType::FontSelect, $config))->toThrow(InvalidArgumentException::class);
    }
});

it('rejects forged continuations and invalid preview configuration', function () {
    expect(fn () => FontPage::offset('40:'.str_repeat('a', 64), 'context'))->toThrow(InvalidArgumentException::class);
    expect(fn () => FontSelect::make('font')->previewText("bad\ntext")->getFontPreviewPolicy())->toThrow(InvalidArgumentException::class);
    expect(fn () => FontSelect::make('font')->categoryLabels(['serif' => []])->getFontCategoryTabs())->toThrow(InvalidArgumentException::class);
});

it('rejects unsupported native or multiple modes instead of rendering a broken adapter', function () {
    expect(fn () => FontSelect::make('font')->multiple()->getAllowedFontFamilies())->toThrow(InvalidArgumentException::class);
    expect(fn () => FontSelect::make('font')->native()->getAllowedFontFamilies())->toThrow(InvalidArgumentException::class);
});

it('renders primary category button expressions as JavaScript rather than literal Blade directives', function () {
    $html = Blade::render(<<<'BLADE'
        <x-filament::button color="primary" size="xs" class="fff-font-select__category"
            :x-bind:aria-pressed="'fontCategory === '.\Illuminate\Support\Js::from($value)"
            :x-on:click="'selectFontCategory('.\Illuminate\Support\Js::from($value).')'">Serif</x-filament::button>
        BLADE, ['value' => 'serif']);
    expect(html_entity_decode($html))->toContain("fontCategory === 'serif'", "selectFontCategory('serif')", 'fi-color-primary')->not->toContain('@js');
});

it('resolves persisted family ids into reusable metadata without preview aliases', function () {
    $font = FontCatalog::find('google:inter');
    expect($font)->not->toBeNull()->and($font->family)->toBe('Inter')->and($font->cssFamily())->toBe('"Inter"');
    foreach ([null, 'Inter', 'google:missing-family', ['value' => 'google:inter'], '<script>'] as $value) {
        expect(FontCatalog::find($value))->toBeNull();
    }
});

it('generates full font stylesheets using supported variants without text subset parameters', function () {
    $font = FontCatalog::find('google:inter');
    $url = $font->stylesheetUrl(weights: [700, 400, 700], styles: ['normal', 'italic']);
    expect(urldecode($url))->toContain('Inter:ital,wght@0,400;0,700;1,400;1,700')->not->toContain('text=', 'fff-ext-');
    expect(fn () => $font->stylesheetUrl(weights: [0]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $font->stylesheetUrl(styles: ['random']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $font->stylesheetUrl(display: 'random'))->toThrow(InvalidArgumentException::class);
});

it('can use a persisted google font through the native Filament panel font provider', function () {
    $font = FontCatalog::find('google:lora');
    $panel = Panel::make()->id('font-settings')->font(
        $font->family, url: $font->stylesheetUrl(), provider: GoogleFontProvider::class,
    );
    expect($panel->getFontFamily())->toBe('Lora')
        ->and($panel->getFontUrl())->toBe($font->stylesheetUrl())
        ->and($panel->getFontHtml()->toHtml())->toContain('fonts.googleapis.com')->not->toContain('text=');
});

it('generates safe local font CSS without allowing a configured name to end a style element', function () {
    $font = FontCatalog::find('local:brand', ['brand' => ['family' => 'Brand </style><script>alert(1)</script>', 'url' => url('/fonts/brand.woff2')]]);
    expect($font->stylesheetUrl())->toBeNull()
        ->and($font->fontFaceCss())->toContain('@font-face', 'format("woff2")', 'font-display:swap')->not->toContain('</style>', '<script>')
        ->and($font->cssFamily())->not->toContain('<');
});

it('constructs valid shared Select appearance from default stored configuration', function () {
    $field = (new FontSelectFieldConfigurator)->configure(FontSelect::make('font'), FieldType::FontSelect->defaultConfig());
    expect($field->getVariant())->toBe('bordered')->and($field->getWrapperClasses())->toContain('fff-select-field');
    expect(fn () => BuiltinConfigurationContract::assert(FieldType::FontSelect, ['variant' => 'primary']))->toThrow(InvalidArgumentException::class);
    expect(fn () => BuiltinConfigurationContract::assert(FieldType::FontSelect, ['rounding' => 'invalid']))->toThrow(InvalidArgumentException::class);
});

it('preserves exact variable weights and exposes supported axis ranges for application fonts', function () {
    $font = FontCatalog::find('google:inter');
    expect($font->axes['wght'])->toBe(['min' => 100.0, 'max' => 900.0, 'default' => 400.0])
        ->and($font->variant(550, 'italic'))->toBe(['weight' => 550, 'style' => 'italic'])
        ->and(rawurldecode($font->stylesheetUrl(weights: [550], styles: ['italic'])))->toContain(':ital,wght@1,550')
        ->and(rawurldecode($font->variableStylesheetUrl(styles: ['italic', 'normal'])))->toContain(':ital,wght@0,100..900;1,100..900')
        ->and($font->variableStylesheetUrl())->not->toContain('text=');
    foreach ([[99, 900], [100, 901], [500, 400], [400, 400], ['100', 900], [100]] as $range) {
        expect(fn () => $font->variableStylesheetUrl($range))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => $font->variableStylesheetUrl(styles: ['random']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FontCatalog::find('google:anton')->variableStylesheetUrl())->toThrow(InvalidArgumentException::class);
});
