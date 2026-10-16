<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\LinkPreviewField;
use Bjanczak\FilamentFlexFields\Support\UrlMeta\UrlMetaScraper;

it('inherits the shared whole-input outline by default and permits explicit overrides', function () {
    expect(LinkPreviewField::make('website')->shouldShowFocusOutline())->toBeTrue()
        ->and(LinkPreviewField::make('website')->focusOutline(false)->shouldShowFocusOutline())->toBeFalse();
});

it('configures preview debounce and visit link visibility', function () {
    $field = LinkPreviewField::make('article_url')
        ->previewDebounce(750)
        ->previewLayout('vertical')
        ->showVisitLink(false)
        ->prefix('https://');

    expect($field->getPreviewDebounce())->toBe(750)
        ->and($field->getPreviewLayout())->toBe('vertical')
        ->and($field->shouldShowVisitLink())->toBeFalse()
        ->and($field->getPrefix())->toBe('https://');
});

it('defaults preview min skeleton duration to 500ms', function () {
    expect(LinkPreviewField::make('article_url')->getPreviewMinSkeletonMs())->toBe(500);
});

it('configures preview min skeleton duration', function () {
    expect(LinkPreviewField::make('article_url')->previewMinSkeletonMs(300)->getPreviewMinSkeletonMs())->toBe(300);
});

it('defaults to resolving initial preview on the server', function () {
    expect(LinkPreviewField::make('article_url')->shouldResolveInitialPreviewOnServer())->toBeTrue();
});

it('can skip server-side initial preview resolution', function () {
    expect(LinkPreviewField::make('article_url')->resolveInitialPreviewOnServer(false)->shouldResolveInitialPreviewOnServer())->toBeFalse();
});

it('defaults to horizontal preview layout', function () {
    expect(LinkPreviewField::make('article_url')->getPreviewLayout())->toBe('horizontal');
});

it('accepts card preview layout', function () {
    expect(LinkPreviewField::make('article_url')->previewLayout('card')->getPreviewLayout())->toBe('card');
});

it('exposes wrapper classes and alpine configuration', function () {
    $field = LinkPreviewField::make('article_url')->previewLayout('horizontal');

    expect($field->getWrapperClasses())
        ->toHaveKey('fff-link-preview-field')
        ->toHaveKey('fff-link-preview-field--layout-horizontal')
        ->not->toHaveKey('fff-flex-text-input-field')
        ->and($field->getAlpineConfiguration())
        ->toHaveKeys(['scrapeUrl', 'previewEnabled', 'previewDebounce', 'previewMinUrlLength', 'previewMinSkeletonMs', 'previewLayout', 'showVisitLink', 'prefix', 'labels'])
        ->and($field->getAlpineConfiguration()['previewMinSkeletonMs'])->toBe(500)
        ->and($field->getAlpineConfiguration()['labels'])->toHaveKeys(['error', 'visit'])
        ->and($field->getAlpineConfiguration()['labels'])->not->toHaveKeys(['loading', 'emptyTitle', 'emptyDescription']);
});

it('resolves initial preview metadata for prefilled urls', function () {
    $this->mock(UrlMetaScraper::class, function ($mock): void {
        $mock->shouldNotReceive('scrape');
        $mock->shouldNotReceive('isScrapableUrl');
        $mock->shouldReceive('cached')->once()->with('https://laravel.com')
            ->andReturn([
                'title' => 'Laravel',
                'description' => 'PHP framework',
                'image' => 'https://laravel.com/og.png',
            ]);
    });

    $field = LinkPreviewField::make('article_url')->default('https://laravel.com');

    $metadata = $field->resolveInitialPreview('https://laravel.com');
    expect($metadata)->toMatchArray(['title' => 'Laravel', 'description' => 'PHP framework']);
    parse_str(parse_url($metadata['image'], PHP_URL_QUERY), $parameters);
    expect($parameters['url'])->toBe('https://laravel.com/og.png')
        ->and($parameters)->toHaveKeys(['signature', 'expires']);
});

it('rejects invalid variants', function () {
    $field = LinkPreviewField::make('article_url');
    $field->variant('invalid');

    $field->getVariant();
})->throws(InvalidArgumentException::class);

it('rejects invalid preview layouts', function () {
    $field = LinkPreviewField::make('article_url');
    $field->previewLayout('grid');

    $field->getPreviewLayout();
})->throws(InvalidArgumentException::class);
