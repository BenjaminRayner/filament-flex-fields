<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableLinkPreviewForm;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    TestableLinkPreviewForm::$formSchema = [LinkPreviewField::make('website')->required()];
    Http::preventStrayRequests();
});
afterEach(function () {
    TestableLinkPreviewForm::$formSchema = [];
});

it('renders shared inputs without network work or duplicate initialization', function () {
    TestableLinkPreviewForm::$formSchema = [
        LinkPreviewField::make('first')->prefix('https://')->size('sm'),
        LinkPreviewField::make('second')->preview(false)->size('lg')->focusOutline(false),
    ];
    $html = Livewire::test(TestableLinkPreviewForm::class, ['initial' => ['first' => 'https://example.com/path']])->html();
    expect(substr_count($html, 'fff-flex-text-input__shell'))->toBe(2)
        ->and(substr_count($html, 'has-focus-outline'))->toBe(1)
        ->and($html)->toContain('value="example.com/path"', 'fff-flex-text-input--sm', 'fff-flex-text-input--lg', 'data-fff-asset')
        ->not->toContain('x-init="init()"');
    Http::assertNothingSent();
});

it('saves only a validated URL and preserves application rules', function () {
    $form = Livewire::test(TestableLinkPreviewForm::class)->set('data.website', '  https://example.com/page  ')->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['website' => 'https://example.com/page']);
    Http::assertNothingSent();
});

it('rejects forged payloads at actual form submission', function (mixed $value) {
    Livewire::test(TestableLinkPreviewForm::class)->set('data.website', $value)->call('save')->assertHasErrors(['data.website']);
})->with([
    ['invalid text'], ['javascript:alert(1)'], [['url' => 'https://example.com']],
    ['https://user:secret@example.com'], ['https://example.com:8080'], [null],
]);

it('renders cached text and visit-link visibility correctly before hydration', function () {
    $this->mock(UrlMetaScraper::class, function ($mock) {
        $mock->shouldReceive('cached')->andReturn(['title' => 'Cached title', 'description' => 'Cached description']);
        $mock->shouldNotReceive('scrape');
    });
    TestableLinkPreviewForm::$formSchema = [LinkPreviewField::make('website')->showVisitLink(false)->previewLayout('vertical')];
    $html = Livewire::test(TestableLinkPreviewForm::class, ['initial' => ['website' => 'https://example.com']])->html();
    expect($html)->toContain('Cached title', 'data-revealed="true"', 'fff-link-preview__domain--text');
    Http::assertNothingSent();
});

it('never emits an eager image source for a cached preview inside an inactive tab', function () {
    $this->mock(UrlMetaScraper::class, function ($mock) {
        $mock->shouldReceive('cached')->andReturn(['title' => 'Cached title', 'image' => 'https://example.com/preview.png']);
    });
    TestableLinkPreviewForm::$formSchema = [Tabs::make('tabs')->tabs([
        Tab::make('First')->schema([]),
        Tab::make('Hidden')->schema([LinkPreviewField::make('website')]),
    ])];
    $html = Livewire::test(TestableLinkPreviewForm::class, ['initial' => ['website' => 'https://example.com']])->html();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $images = $xpath->query('//img[contains(@class, "fff-link-preview__image")]');
    expect($images->length)->toBe(1)->and($images->item(0)->hasAttribute('src'))->toBeFalse();
    Http::assertNothingSent();
});
