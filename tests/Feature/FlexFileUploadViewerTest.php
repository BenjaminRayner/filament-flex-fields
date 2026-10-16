<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableFileViewerForm;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('keeps viewer and metadata writes opt in', function () {
    expect(FlexFileUpload::make('photos')->hasFileViewer())->toBeFalse();
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->fileViewer()->fileMetadataEditor()->fileMetadataStatePath('details')];
    $field = Livewire::test(TestableFileViewerForm::class)->instance()->getSchema('form')->getComponent('photos');
    expect($field->hasFileViewerMetadata())->toBeTrue()->and($field->canEditFileViewerMetadata())->toBeFalse();
    $field->fileViewerEditableMetadata()->disabled();
    expect($field->canEditFileViewerMetadata())->toBeFalse();
});

it('denies navigation when the viewer is disabled or the target is not owned', function () {
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')];
    $livewire = Livewire::test(TestableFileViewerForm::class);
    $field = $livewire->instance()->getSchema('form')->getComponent('photos');
    expect(fn () => $field->getFileViewerItems())->toThrow(MediaFailure::class);
    $field->fileViewer();
    expect(fn () => $field->navigateFileViewer('foreign'))->toThrow(MediaFailure::class);
});

it('removes the information column without changing fullscreen gallery navigation', function () {
    Storage::fake('public');
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->disk('public')->multiple()
        ->fileViewer()->fileMetadataEditor()->fileMetadataStatePath('details')->fileViewerMetadata(false)];
    $livewire = Livewire::test(TestableFileViewerForm::class)
        ->upload('data.photos.first', [UploadedFile::fake()->image('first.jpg')])
        ->upload('data.photos.second', [UploadedFile::fake()->image('second.jpg')]);
    $instance = $livewire->instance();
    $field = $instance->getSchema('form')->getComponent('photos');
    $instance->mountAction('viewFile', ['fileKey' => 'first'], ['schemaComponent' => $field->getKey()]);
    $schema = $instance->getSchema($instance->getMountedActionSchemaName());
    $layout = $schema->getComponents()[0];
    expect($field->hasFileViewerMetadata())->toBeFalse()
        ->and($field->getFlexFileUploadAlpineConfiguration()['hasFileViewerMetadata'])->toBeFalse()
        ->and($layout->getExtraAttributes()['class'])->toBe('fff-upload-lightbox-layout')
        ->and($layout->getChildSchema()->getComponents())->toHaveCount(1)
        ->and($field->navigateFileViewer('second'))->toBeTrue();
});

it('bounds optional local thumbnails independently of server preview conversions', function () {
    $field = FlexFileUpload::make('photo');
    expect($field->getPreviewThumbnailSize())->toBeNull();
    $field->previewThumbnailSize(768)->previewConversion('thumb');
    expect($field->getPreviewThumbnailSize())->toBe(768)->and($field->getPreviewConversion())->toBe('thumb');
    $field->previewThumbnailSize(null);
    expect($field->getPreviewThumbnailSize())->toBeNull()->and($field->getPreviewConversion())->toBe('thumb');
    $field->previewThumbnailSize(9000);
    expect(fn () => $field->getPreviewThumbnailSize())->toThrow(InvalidArgumentException::class);
});

it('saves configured FLEX metadata when moving between owned images in the same mounted action', function () {
    Storage::fake('public');
    TestableFileViewerForm::$formSchema = [
        FlexFileUpload::make('photos')->disk('public')->multiple()->fileViewer()
            ->fileMetadataEditor()->fileMetadataStatePath('details')
            ->fileMetadataSchema([FlexTextInput::make('alt')->required()])->fileViewerEditableMetadata(),
    ];
    $livewire = Livewire::test(TestableFileViewerForm::class)
        ->upload('data.photos.first', [UploadedFile::fake()->image('first.jpg')])
        ->upload('data.photos.second', [UploadedFile::fake()->image('second.jpg')]);
    $instance = $livewire->instance();
    $field = $instance->getSchema('form')->getComponent('photos');
    expect($field->getFileViewerItems())->toHaveCount(2);
    $instance->mountAction('viewFile', ['fileKey' => 'first'], ['schemaComponent' => $field->getKey()]);
    expect($instance->getMountedAction()->getModalHeading())->toBe('')
        ->and($instance->getMountedAction()->getModalFooterActions())->toBe([]);
    expect($instance->getMountedAction()->getModalWidth())->toBe(Width::Screen)
        ->and($instance->getMountedAction()->isModalClosedByClickingAway())->toBeFalse()
        ->and($instance->getMountedAction()->isModalClosedByEscaping())->toBeFalse();
    expect($instance->getMountedAction()->getExtraModalWindowAttributes()['class'])->toBe('fff-upload-lightbox-window');
    $schema = $instance->getSchema($instance->getMountedActionSchemaName());
    $schema->fill(['alt' => 'First image']);
    expect($field->navigateFileViewer('second'))->toBeTrue();
    expect($instance->getMountedAction()->getArguments()['fileKey'])->toBe('second')
        ->and($field->readFileEditorialMetadata('first')['alt'])->toBe('First image');
});

it('keeps the selected image when required metadata is invalid', function () {
    Storage::fake('public');
    TestableFileViewerForm::$formSchema = [
        FlexFileUpload::make('photos')->disk('public')->multiple()->fileViewer()
            ->fileMetadataEditor()->fileMetadataStatePath('details')
            ->fileMetadataSchema([FlexTextInput::make('alt')->required()])->fileViewerEditableMetadata(),
    ];
    $livewire = Livewire::test(TestableFileViewerForm::class)
        ->upload('data.photos.first', [UploadedFile::fake()->image('first.jpg')])
        ->upload('data.photos.second', [UploadedFile::fake()->image('second.jpg')]);
    $instance = $livewire->instance();
    $field = $instance->getSchema('form')->getComponent('photos');
    $instance->mountAction('viewFile', ['fileKey' => 'first'], ['schemaComponent' => $field->getKey()]);
    expect(fn () => $field->navigateFileViewer('second'))->toThrow(ValidationException::class);
    expect($instance->getMountedAction()->getArguments()['fileKey'])->toBe('first');
});

it('provides navigation without a submit action when metadata is read only', function () {
    Storage::fake('public');
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->disk('public')->multiple()->fileViewer()->fileMetadataEditor()->fileMetadataStatePath('details')];
    $livewire = Livewire::test(TestableFileViewerForm::class)
        ->upload('data.photos.first', [UploadedFile::fake()->image('first.jpg')])
        ->upload('data.photos.second', [UploadedFile::fake()->image('second.jpg')]);
    $instance = $livewire->instance();
    $field = $instance->getSchema('form')->getComponent('photos');
    $instance->mountAction('viewFile', ['fileKey' => 'first'], ['schemaComponent' => $field->getKey()]);
    expect($instance->getMountedAction()->getModalSubmitAction())->toBeNull();
    expect($field->navigateFileViewer('second'))->toBeTrue();
    expect($instance->getMountedAction()->getArguments()['fileKey'])->toBe('second')
        ->and($field->readFileEditorialMetadata('first'))->toBe([]);
});

it('renders a separate avatar preview hit target only when the viewer is configured', function () {
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->avatar()];
    expect(Livewire::test(TestableFileViewerForm::class)->html())->not->toContain('fff-flex-file-upload__avatar-viewer');
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->avatar()->fileViewer()->imageEditor()];
    $html = Livewire::test(TestableFileViewerForm::class)->html();
    expect($html)->toContain('fff-flex-file-upload__avatar-viewer')->toContain('View image')
        ->toContain('fff-flex-file-upload__avatar-action--center');
});

it('uses a separate viewer conversion while retaining the thumbnail and original download', function () {
    Storage::fake('public');
    TestableFileViewerForm::$formSchema = [FlexFileUpload::make('photos')->multiple()->disk('public')->visibility('public')
        ->directory('images')->createFormStrategy()->fileViewer()->openable()->downloadable()
        ->imageConversions([
            new ImageConversion('thumb', 32, 32),
            new ImageConversion('large', 128, 128),
        ])->previewConversion('thumb')->fileViewerConversion('large')];
    $form = Livewire::test(TestableFileViewerForm::class)->upload('data.photos.first', [UploadedFile::fake()->image('photo.jpg', 256, 256)]);
    $field = $form->instance()->getSchema('form')->getComponent('photos');
    $field->saveUploadedFiles();
    $path = array_values($field->getRawState())[0];
    $manifest = app(UploadManifestRepository::class)->read('public', $path);
    $files = array_values($field->getUploadedFiles());
    expect(parse_url($files[0]['url'], PHP_URL_PATH))->toEndWith($manifest->variants['thumb']->path)
        ->and(parse_url($field->getFileViewerItems()[0]['url'], PHP_URL_PATH))->toEndWith($manifest->variants['large']->path)
        ->and(parse_url($files[0]['downloadableUrl'], PHP_URL_PATH))->toEndWith($manifest->original->path);
    $field->fileViewerConversion('missing');
    expect($field->getFileViewerItems()[0]['url'])->toBe($files[0]['openableUrl']);
    $field->fileViewerUrlUsing(fn () => '/legacy/large.jpg');
    expect($field->getFileViewerItems()[0]['url'])->toBe('/legacy/large.jpg');
    $field->fileViewerUrlUsing(fn () => 'javascript:alert(1)');
    expect(fn () => $field->getFileViewerItems())->toThrow(MediaFailure::class);
    $field->fileViewerConversion('../large');
    expect(fn () => $field->getFileViewerConversion())->toThrow(InvalidArgumentException::class);
});
