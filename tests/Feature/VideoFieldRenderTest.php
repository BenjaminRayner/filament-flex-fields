<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableVideoForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableVideoForm::$formSchema = [];
});

it('renders one visible media surface and keeps preview decoders hidden without a source', function (): void {
    TestableVideoForm::$formSchema = [
        VideoField::make('first')->src('/videos/first.mp4')->settingsMenu()->playbackRates([.5, 1, 2]),
        VideoField::make('second')->src('/videos/second.mp4')->compactControls(),
        VideoField::make('native')->src('/videos/native.mp4')->nativeControls(),
    ];
    $html = Livewire::test(TestableVideoForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//video[@x-ref="video"]')->length)->toBe(3)
        ->and($xpath->query('//video[@x-ref="previewVideo"][@hidden][not(@src)][@preload="none"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-fff-asset-consumer="video-field"]')->length)->toBe(3)
        ->and($xpath->query('//*[@x-ref="settingsMenu"][@hidden]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-menu-highlight]')->length)->toBe(2);
});

it('rejects crafted URL state on submission', function (mixed $state): void {
    TestableVideoForm::$formSchema = [VideoField::make('clip')];
    Livewire::test(TestableVideoForm::class)->set('data.clip', $state)->call('save')->assertHasErrors(['data.clip']);
})->with([['javascript:alert(1)'], ['https://user:password@example.com/video.mp4'], [['src' => '/clip.mp4']], ['//example.com/clip.mp4']]);

it('saves supported URLs and optional empty values', function (mixed $state): void {
    TestableVideoForm::$formSchema = [VideoField::make('clip')];
    Livewire::test(TestableVideoForm::class)->set('data.clip', $state)->call('save')->assertHasNoErrors();
})->with([['https://example.com/clip.mp4'], ['/videos/clip.mp4'], [null]]);

it('renders preview configuration through the initialized schema without preloading its decoder', function (): void {
    TestableVideoForm::$formSchema = [VideoField::make('clip')->src('/clip.mp4')->previewSource('/low-resolution.mp4')->previewThumbnails([
        ['start' => 0, 'end' => 10, 'src' => '/previews/storyboard.jpg', 'x' => 0, 'y' => 0, 'width' => 192, 'height' => 108],
    ])];
    $html = Livewire::test(TestableVideoForm::class)->html(false);
    expect($html)->toContain('low-resolution.mp4', 'storyboard.jpg', 'previewThumbnails');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//video[@x-ref="previewVideo"][not(@src)]')->length)->toBe(1);
});
