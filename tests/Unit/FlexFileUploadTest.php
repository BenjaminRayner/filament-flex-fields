<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\ExecutableExtensionGuard;
use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadMimePresets;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadRequirements;
use Bjanczak\FilamentFlexFields\Support\FileUpload\ScopedDirectoryResolver;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexFileUploadPlayground;
use Bjanczak\FilamentFlexFields\Support\Schema\Configuration\BuiltinConfigurationContract;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

it('defaults remove uploaded file button to the right', function () {
    $field = FlexFileUpload::make('attachment');

    expect($field->getRemoveUploadedFileButtonPosition())->toBe('right');
});

it('moves remove button left when image editor is enabled so delete stays visible', function () {
    $field = FlexImageUpload::make('photo')->imageEditor();

    expect($field->getRemoveUploadedFileButtonPosition())->toBe('left');
});

it('applies mime presets on flex file upload fields', function () {
    $documents = FlexFileUpload::make('attachment')->documentsOnly();
    $images = FlexImageUpload::make('photo')->imagesOnly();
    $spreadsheets = FlexFileUpload::make('sheet')->spreadsheetsOnly();

    expect($documents->getAcceptedFileTypes())->toBe(FileUploadMimePresets::documents())
        ->and($images->getAcceptedFileTypes())->toBe(FileUploadMimePresets::images())
        ->and($spreadsheets->getAcceptedFileTypes())->toBe(FileUploadMimePresets::spreadsheets());
});

it('registers flex file upload validation helpers', function () {
    $field = FlexFileUpload::make('attachment')
        ->allowedExtensions(['pdf', 'docx'])
        ->rejectExecutableFiles()
        ->maxTotalSizeKb(2048)
        ->minImageDimensions(800, 600)
        ->maxImageDimensions(4000, 4000);

    expect($field->getAllowedExtensions())->toBe(['pdf', 'docx'])
        ->and($field->shouldRejectExecutableFiles())->toBeTrue()
        ->and($field->getMaxTotalSizeKb())->toBe(2048)
        ->and($field->getFlexMinImageWidth())->toBe(800)
        ->and($field->getFlexMinImageHeight())->toBe(600)
        ->and($field->getFlexMaxImageWidth())->toBe(4000)
        ->and($field->getFlexMaxImageHeight())->toBe(4000);
});

it('resolves scoped upload directories for records and drafts', function () {
    $record = new class extends Model
    {
        protected $table = 'posts';

        public function getKey(): int
        {
            return 42;
        }
    };

    expect(ScopedDirectoryResolver::resolve('uploads', $record, 7))
        ->toBe('uploads/'.class_basename($record).'/42')
        ->and(ScopedDirectoryResolver::resolve('uploads', null, 7))
        ->toBe('uploads/drafts/7');
});

it('exposes metadata storage path configuration', function () {
    $field = FlexFileUpload::make('attachment')
        ->storeMetadataIn('attachment_meta');

    expect($field->getStoreMetadataInPath())->toBe('attachment_meta');
});

it('skips getOriginalFilePaths when the field name is not a model attribute', function () {
    Model::preventAccessingMissingAttributes();

    try {
        $record = new class extends Model
        {
            protected $table = 'posts';

            public $exists = true;

            protected $attributes = [
                'id' => 1,
                'title' => 'Nested uploads',
            ];
        };

        $record->syncOriginal();

        $nestedField = FlexFileUpload::make('image_desktop')->model($record);
        $ownedField = FlexFileUpload::make('title')->model($record);

        expect($nestedField->getOriginalFilePaths())->toBe([])
            ->and($ownedField->getOriginalFilePaths())->toBe(['Nested uploads']);
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});

it('applies recommended defaults for file and image uploads', function () {
    $file = FlexFileUpload::make('attachment')->withRecommendedDefaults();
    $image = FlexImageUpload::make('photo')->withRecommendedDefaults();

    expect($file->getMaxSize())->toBe(5120)
        ->and($file->isDownloadable())->toBeTrue()
        ->and($file->isOpenable())->toBeTrue()
        ->and($file->shouldDeleteFileOnRemove())->toBeTrue()
        ->and($file->shouldDeleteReplacedFiles())->toBeTrue()
        ->and($file->getAcceptedFileTypes())->toBe(FileUploadMimePresets::documents())
        ->and($image->getAcceptedFileTypes())->toBe(FileUploadMimePresets::images());
});

it('blocks executable extensions via guard', function () {
    expect(ExecutableExtensionGuard::isBlocked('shell.php'))->toBeTrue()
        ->and(ExecutableExtensionGuard::isBlocked('.htaccess'))->toBeTrue()
        ->and(ExecutableExtensionGuard::isBlocked('report.pdf'))->toBeFalse();
});

it('supports avatar mode on flex image upload', function () {
    $field = FlexImageUpload::make('avatar')
        ->avatar()
        ->imageEditor()
        ->circleCropper();

    expect($field->isAvatar())->toBeTrue()
        ->and($field->hasImageEditor())->toBeTrue()
        ->and($field->hasCircleCropper())->toBeTrue()
        ->and($field->getWrapperClasses())->toContain('fff-flex-file-upload--avatar');
});

it('renders the real dropzone before native transport initializes', function (): void {
    TestableTranslatableForm::$formSchema = [
        FlexFileUpload::make('attachment')
            ->withRecommendedDefaults()
            ->uploadSummary()
            ->maxFiles(1)
            ->remainingSlotsLabel(),
    ];

    $html = Livewire::test(TestableTranslatableForm::class)->html(false);

    expect($html)
        ->toContain('fff-flex-file-upload__dropzone')
        ->toContain('Select files')
        ->toContain('fff-flex-file-upload__engine')
        ->not->toContain('fff-flex-file-upload__skeleton')
        ->not->toContain('x-init="init()"');
});

it('renders avatar controls only in the circular surface', function (): void {
    TestableTranslatableForm::$formSchema = [
        FlexImageUpload::make('avatar')
            ->avatar()
            ->emptyStateHint('Upload profile photo'),
    ];

    $html = Livewire::test(TestableTranslatableForm::class)->html(false);

    expect($html)
        ->toContain('fff-flex-file-upload__avatar-shell')
        ->toContain('fff-flex-file-upload__engine')
        ->not->toContain('fff-flex-file-upload__avatar-native')
        ->toContain('fff-flex-file-upload__avatar-fallback')
        ->toContain('flexFileUploadFormComponent(')
        ->not->toContain('fff-flex-file-upload__select')
        ->not->toContain('fff-flex-file-upload__card')
        ->not->toContain('fff-flex-file-upload__skeleton');

    expect(substr_count($html, 'class="fi-fo-file-upload-input-ctn fff-flex-file-upload__transport"'))->toBe(1);
});

it('registers flex file upload playground section', function () {
    $state = app(FlexFileUploadPlayground::class)->defaultState();

    expect($state)->toHaveKeys([
        'file_upload__avatar',
        'file_upload__documents',
        'file_upload__images',
        'file_upload__multi',
        'file_upload__metadata',
        'file_upload__metadata_meta',
    ]);
});

it('invokes MediaCaptureOs virus-scan callback from FlexFileUpload', function () {
    $scanned = [];

    MediaCaptureOs::registerVirusScanCallback(function (string $path) use (&$scanned): bool {
        $scanned[] = $path;

        return true;
    });

    $field = FlexFileUpload::make('attachment')->disk('public');

    expect($field->passesMediaCaptureVirusScan('uploads/invoice.pdf'))->toBeTrue()
        ->and($scanned)->toBe(['uploads/invoice.pdf']);

    MediaCaptureOs::registerVirusScanCallback(fn (string $path): bool => false);

    expect($field->passesMediaCaptureVirusScan('uploads/malware.exe'))->toBeFalse();

    MediaCaptureOs::registerVirusScanCallback(null);

    expect($field->passesMediaCaptureVirusScan('uploads/safe.pdf'))->toBeTrue();
});

it('resolves signed upload urls via MediaCaptureOs when registered', function () {
    MediaCaptureOs::registerSignedUploadUrlResolver(
        fn (string $disk, string $path, array $context): string => "https://signed.test/{$disk}/{$path}",
    );

    $field = FlexFileUpload::make('attachment')->disk('public');

    expect($field->resolveMediaCaptureSignedUrl('uploads/a.pdf'))
        ->toBe('https://signed.test/public/uploads/a.pdf');

    MediaCaptureOs::registerSignedUploadUrlResolver(null);

    expect($field->resolveMediaCaptureSignedUrl('uploads/a.pdf'))->toBeNull();
});

it('keeps header density independent of uploaded item layout and exposes the declared JSON option', function () {
    $field = FlexFileUpload::make('files')->uploadLayout('grid')->compactHeader();
    expect($field->shouldUseCompactHeader())->toBeTrue()
        ->and($field->getUploadPresentation()->layout->value)->toBe('grid');
    $field->compactHeader(false)->uploadLayout('list');
    expect($field->shouldUseCompactHeader())->toBeFalse()
        ->and($field->getUploadPresentation()->layout->value)->toBe('list');
    expect((new UploadRequirements)->icons('file')[1])->toBe('hugeicons-upload-01');
});

it('declares compact header configuration and rejects malformed JSON booleans', function () {
    BuiltinConfigurationContract::assert(FieldType::File, ['compact_header' => true]);
    expect(fn () => BuiltinConfigurationContract::assert(FieldType::File, ['compact_header' => 'true']))->toThrow(InvalidArgumentException::class);
});

it('offers consistent named grid sizes and independently configurable action presentation', function () {
    $field = FlexFileUpload::make('files')->uploadLayout('grid')->gridSize('sm')->gridActions('menu')->fileClickPreview();
    expect($field->getUploadPresentation()->gridTileSize)->toBe(96)->and($field->getGridActions())->toBe('menu')
        ->and($field->shouldPreviewFileOnClick())->toBeTrue();
    $field->gridSize('md');
    expect($field->getUploadPresentation()->gridTileSize)->toBe(116);
    $field->gridTileSize(100)->fileClickAction(fn () => null);
    expect($field->getUploadPresentation()->gridTileSize)->toBe(100)->and($field->shouldPreviewFileOnClick())->toBeFalse();
    $field->gridActions('invalid');
    expect(fn () => $field->getGridActions())->toThrow(InvalidArgumentException::class);
    $field->gridActions('below')->gridSize('invalid');
    expect(fn () => $field->getUploadPresentation())->toThrow(InvalidArgumentException::class);
});
