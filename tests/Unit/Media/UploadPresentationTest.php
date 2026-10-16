<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarSize;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\FileIconCatalog;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadPresentation;

it('scales grid presentation through validated dynamic per-field configuration', function (): void {
    $size = 144;
    $field = FlexFileUpload::make('attachments')->uploadLayout('grid')->gridTileSize(function () use (&$size): int {
        return $size;
    });
    expect($field->getUploadPresentation()->gridTileSize)->toBe(144);
    $size = 192;
    expect($field->getUploadPresentation()->gridTileSize)->toBe(192)
        ->and(FlexFileUpload::make('other')->getUploadPresentation()->gridTileSize)->toBe(116);
    foreach ([79, 321] as $invalid) {
        expect(fn () => $field->gridTileSize($invalid)->getUploadPresentation())->toThrow(InvalidArgumentException::class);
    }
});

it('offers three avatar sizes and re-evaluates dynamic configuration', function (): void {
    foreach (['sm' => 96, 'md' => 128, 'lg' => 160] as $name => $pixels) {
        expect(FlexImageUpload::make('avatar')->avatar()->avatarSize($name)->getUploadPresentation()->avatarSize)->toBe($pixels);
    }
    expect(FlexImageUpload::make('avatar')->avatarSize(AvatarSize::Large)->getUploadPresentation()->avatarSize)->toBe(160);
    $size = 110;
    $field = FlexImageUpload::make('avatar')->avatarSize(function () use (&$size): int {
        return $size;
    });
    expect($field->getUploadPresentation()->avatarSize)->toBe(110);
    $size = 144;
    expect($field->getUploadPresentation()->avatarSize)->toBe(144);
    expect(fn () => $field->avatarSize('huge')->getUploadPresentation())->toThrow(InvalidArgumentException::class);
    expect(fn () => $field->avatarSize(513)->getUploadPresentation())->toThrow(InvalidArgumentException::class);
});

it('shows only allowed image types on image-only dropzones', function (): void {
    $presentation = new UploadPresentation;
    expect(array_column($presentation->illustration(['image/jpeg', 'image/png']), 'label'))->toBe(['JPG', 'PNG'])
        ->and(array_unique(array_column($presentation->illustration(['image/*']), 'kind')))->toBe(['image'])
        ->and(array_column($presentation->illustration(['application/pdf', 'text/plain']), 'label'))->toBe(['PDF', 'TXT'])
        ->and(array_column($presentation->illustration(null), 'kind'))->toBe(['image', 'document', 'document']);
});

it('keeps authorized SSR identities and never embeds executable preview urls', function (): void {
    $presentation = new UploadPresentation;
    $files = $presentation->files([
        'owned-key' => ['name' => 'portrait.png', 'type' => 'image/png', 'size' => 32, 'url' => '/signed/preview?expires=10'],
        'unavailable' => null,
        'bad-url' => ['name' => 'other.png', 'type' => 'image/png', 'size' => 42, 'url' => 'javascript:alert(1)', 'openableUrl' => '//outside.test/file'],
    ]);
    expect($files)->toHaveCount(2)
        ->and($files[0]['id'])->toBe('stored:owned-key')
        ->and($files[0]['preview'])->toBe('/signed/preview?expires=10')
        ->and($files[1]['preview'])->toBeNull()
        ->and($files[1]['openUrl'])->toBeNull()
        ->and($presentation->files(['file' => ['name' => 'portrait.png', 'type' => 'image/png', 'url' => '/preview']], false)[0]['preview'])->toBeNull();
});

it('evaluates instant upload per instance without affecting other fields', function (): void {
    $instant = false;
    $field = FlexImageUpload::make('manual_avatar')->instantUpload(function () use (&$instant): bool {
        return $instant;
    });
    expect($field->shouldUploadInstantly())->toBeFalse()
        ->and(FlexImageUpload::make('automatic_avatar')->shouldUploadInstantly())->toBeTrue();
    $instant = true;
    expect($field->shouldUploadInstantly())->toBeTrue();
});

it('evaluates pause support per instance and keeps native avatar actions unchanged', function (): void {
    expect(FlexFileUpload::make('grid')->multiple()->uploadLayout('grid')->pausable()->isPausable())->toBeTrue()
        ->and(FlexFileUpload::make('list')->multiple()->pausable()->isPausable())->toBeTrue()
        ->and(FlexFileUpload::make('default')->isPausable())->toBeFalse()
        ->and(FlexImageUpload::make('avatar')->avatar()->pausable()->isPausable())->toBeFalse();
});

it('resolves declared file artwork with MIME precedence and safe unknown-file fallback', function (): void {
    expect(FileIconCatalog::kind('XLSX'))->toBe('sheet')
        ->and(FileIconCatalog::kind('docx'))->toBe('document')
        ->and(FileIconCatalog::kind('SKETCH'))->toBe('sketch')
        ->and(FileIconCatalog::kind('jpg', 'application/pdf'))->toBe('document')
        ->and(FileIconCatalog::kind('bin', 'IMAGE/PNG; charset=binary'))->toBe('image')
        ->and(FileIconCatalog::kind('../../outside'))->toBe('attachment');
    $catalog = FileIconCatalog::configuration();
    foreach ($catalog['urls'] as $kind => $url) {
        expect($url)->toBe(asset(app(BuiltInAssetRelease::class)->publicDirectory().'/assets/file-upload/icons/color-'.$kind.'.svg'))
            ->not->toContain('file%20icons', '../');
    }
    $files = (new UploadPresentation)->files(['design' => ['name' => 'mockup.sketch']]);
    expect(FileIconCatalog::kind($files[0]['extension']))->toBe('sketch');
});
