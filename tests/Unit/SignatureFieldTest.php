<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Playground\SignatureFieldPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\SignaturePlaygroundFixture;
use Bjanczak\FilamentFlexFields\Support\SignatureSvg;

it('validates compact svg signatures', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 320"><path d="M10,20 L30,40" fill="none" stroke="#18181b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    expect(SignatureSvg::isValid($svg))->toBeTrue()
        ->and(SignatureSvg::countPaths($svg))->toBe(1)
        ->and(SignatureSvg::isEmpty($svg))->toBeFalse();
});

it('accepts exported signatures with a background rect', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 320"><rect width="100%" height="100%" fill="#ffffff"/><path d="M10 10 L20 20" fill="none" stroke="#18181b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    expect(SignatureSvg::isValid($svg))->toBeTrue()
        ->and(SignatureSvg::normalize($svg))->toBeString()
        ->and(SignatureSvg::countPaths($svg))->toBe(1);
});

it('rejects unsafe svg payloads', function () {
    expect(SignatureSvg::isValid('<svg><script>alert(1)</script></svg>'))->toBeFalse()
        ->and(SignatureSvg::isValid('<div></div>'))->toBeFalse()
        ->and(SignatureSvg::isValid('<svg><rect onclick="alert(1)"/><path d="M1 1"/></svg>'))->toBeFalse();
});

it('normalizes svg by removing whitespace between tags', function () {
    $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1000 320\">\n  <path d=\"M1,2 L3,4\"/>\n</svg>";

    expect(SignatureSvg::normalize($svg))
        ->toBe('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 320"><path d="M1,2 L3,4"/></svg>');
});

it('exposes configurable max svg size', function () {
    $field = SignatureField::make('signature')->maxSizeKb(24);
    $largePath = str_repeat('M10,20 L30,40 L50,60 L70,80 ', 2000);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 320"><path d="'.$largePath.'" fill="none" stroke="#000" stroke-width="2"/></svg>';

    expect($field->getMaxSizeKb())->toBe(24)
        ->and(SignatureSvg::byteSize($svg))->toBeGreaterThan(24 * 1024);
});

it('registers signature playground defaults', function () {
    $state = app(SignatureFieldPlayground::class)->defaultState();

    expect($state)->toHaveKeys([
        'signature__contract',
        'signature__enterprise',
        'signature__validation',
        'signature__webp',
        'signature__readonly',
    ]);
    $fixture = SignaturePlaygroundFixture::svg();
    expect(SignatureSvg::isValid($fixture))->toBeTrue()
        ->and(SignatureSvg::isEmpty($fixture))->toBeFalse()
        ->and(strlen($fixture))->toBeLessThan(48 * 1024)
        ->and($fixture)->toContain('fill-rule="evenodd"')->not->toContain('<?xml', '<g', '<metadata', 'transform=')
        ->and($state['signature__readonly'])->toBe($fixture)
        ->and($state['signature__webp'])->toBe(SignaturePlaygroundFixture::replaySvg())
        ->and($state['signature__webp'])->toContain('data-fff-ink=')
        ->and(SignatureSvg::isValid($state['signature__webp']))->toBeTrue()
        ->and(strlen($state['signature__webp']))->toBeLessThan(48 * 1024);
});

it('exposes smoothing and download configuration', function () {
    $field = SignatureField::make('signature')
        ->smoothing(false)
        ->downloadable(SignatureField::DOWNLOAD_WEBP)
        ->downloadFilename('lease-sign')
        ->webpQuality(0.85);

    expect($field->isSmoothingEnabled())->toBeFalse()
        ->and($field->getDownloadFormat())->toBe(SignatureField::DOWNLOAD_WEBP)
        ->and($field->getDownloadFilename())->toBe('lease-sign')
        ->and($field->getWebpQuality())->toBe(0.85);
});

it('exposes trackpad glide configuration', function () {
    $field = SignatureField::make('signature')->trackpadGlide();

    expect($field->isTrackpadGlideEnabled())->toBeTrue()
        ->and($field->getTrackpadGlideKey())->toBe('d');

    $field->trackpadGlide(false);

    expect($field->isTrackpadGlideEnabled())->toBeFalse();
});

it('rejects invalid trackpad glide shortcut keys', function () {
    SignatureField::make('signature')->trackpadGlideKey('draw')->getTrackpadGlideKey();
})->throws(InvalidArgumentException::class);

it('exposes Hugeicons defaults for signature controls', function () {
    $field = SignatureField::make('signature');

    expect($field->getUndoIcon())->toBe(FlexIcon::ArrowRotateLeft)
        ->and($field->getClearIcon())->toBe(FlexIcon::ArrowsRotateRight)
        ->and($field->getDownloadIcon())->toBe(FlexIcon::ArrowDownToSquare)
        ->and($field->getFullscreenIcon())->toBe(FlexIcon::ChevronsExpandUpRight)
        ->and($field->getPdfPreviewIcon())->toBe(FlexIcon::FileText)
        ->and($field->getCloseIcon())->toBe(FlexIcon::Xmark);
});

it('exposes notebook guidelines configuration', function () {
    $field = SignatureField::make('signature')->guidelines();

    expect($field->isGuidelinesEnabled())->toBeTrue();

    $field->guidelines(false);

    expect($field->isGuidelinesEnabled())->toBeFalse();
});
