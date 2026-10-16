<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

it('activates only a file in the native field state and passes canonical PHP data to the callback', function () {
    Storage::fake('public');
    $calls = [];
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()->disk('public')
        ->fileClickAction(function (TemporaryUploadedFile|string $file, string $fileKey, string $fileName) use (&$calls): void {
            $calls[] = [$file, $fileKey, $fileName];
        })];
    $key = (string) Str::uuid();
    $component = Livewire::test(TestableTranslatableForm::class)->upload('data.attachments.'.$key, [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')]);
    $field = $component->instance()->getSchema('form')->getComponent('attachments');
    $component->call('callSchemaComponentMethod', $field->getKey(), 'activateUploadedFile', ['fileKey' => $key]);
    expect($calls)->toHaveCount(1)->and($calls[0][0])->toBeInstanceOf(TemporaryUploadedFile::class)
        ->and($calls[0][1])->toBe($key)->and($calls[0][2])->toBe('report.pdf');
    expect(fn () => $field->activateUploadedFile('another-fields-key'))->toThrow(MediaFailure::class);
    $field->disabled();
    expect(fn () => $field->activateUploadedFile($key))->toThrow(MediaFailure::class);
    expect($calls)->toHaveCount(1);
});

it('resolves an application URL and redirect from the owned upload and rejects active or malformed URLs', function () {
    Storage::fake('public');
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()->disk('public')->fileClickUrl(fn (string $fileKey): string => '/documents/'.$fileKey)];
    $key = (string) Str::uuid();
    $component = Livewire::test(TestableTranslatableForm::class)->upload('data.attachments.'.$key, [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')]);
    $field = $component->instance()->getSchema('form')->getComponent('attachments');
    expect($field->activateUploadedFile($key))->toBe('/documents/'.$key);
    foreach (['javascript:alert(1)', 'data:text/html,x', '//example.com', 'https://user:secret@example.com', "https://example.com/\n", [], false] as $url) {
        $field->fileClickUrl(fn () => $url);
        expect(fn () => $field->activateUploadedFile($key))->toThrow(MediaFailure::class);
    }
    $field->fileClickAction(fn () => redirect('/documents/preview'));
    expect($field->activateUploadedFile($key))->toBe(url('/documents/preview'));
    $field->fileClickAction(null);
    expect(fn () => $field->activateUploadedFile($key))->toThrow(MediaFailure::class);
});

it('renders compact file headers without artwork and disables the picker on native hydrated capacity', function () {
    Storage::fake('public');
    foreach (['a.txt', 'b.txt'] as $name) {
        Storage::disk('public')->put('documents/'.$name, 'fixture');
    }
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->disk('public')->directory('documents')
        ->multiple()->maxFiles(2)->uploadLayout('grid')->compactHeader()->fileClickUrl(fn (): string => '/documents')];
    $component = Livewire::test(TestableTranslatableForm::class)->fillForm(['attachments' => ['documents/a.txt', 'documents/b.txt']]);
    $field = $component->instance()->getSchema('form')->getComponent('attachments');
    expect($field->isUploadAtCapacity())->toBeTrue();
    expect(Validator::make(['attachments' => ['documents/a.txt', 'documents/b.txt', 'documents/a.txt']], ['attachments' => $field->getValidationRules()])->fails())->toBeTrue();
    $html = $component->html(false);
    expect($html)->toContain('fff-flex-file-upload__dropzone--compact', 'fff-flex-file-upload__tile-trigger')->not->toContain('fff-flex-file-upload__paper-scene');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//button[contains(@class,"fff-flex-file-upload__select") and @disabled]')->length)->toBe(1);
    $component->fillForm(['attachments' => ['documents/a.txt']]);
    expect($component->instance()->getSchema('form')->getComponent('attachments')->isUploadAtCapacity())->toBeFalse();
});

it('authorizes stored file clicks within the configured disk namespace without exposing client URLs', function () {
    Storage::fake('public');
    Storage::disk('public')->put('documents/a.txt', 'Owned file');
    Storage::disk('public')->put('outside/b.txt', 'Foreign namespace');
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()->disk('public')->directory('documents')->createFormStrategy()
        ->fileClickUrl(fn (string $file, string $mimeType): string => '/viewer?file='.rawurlencode($file).'&type='.rawurlencode($mimeType))];
    $component = Livewire::test(TestableTranslatableForm::class)->fillForm(['attachments' => ['documents/a.txt']]);
    $field = $component->instance()->getSchema('form')->getComponent('attachments');
    $key = (string) array_key_first($field->getRawState());
    expect($field->activateUploadedFile($key))->toContain('/viewer?file=documents%2Fa.txt');
    foreach ([[], null, false] as $invalid) {
        expect(fn () => $field->activateUploadedFile($invalid))->toThrow(MediaFailure::class);
    }
    foreach (['outside/b.txt', 'documents/../outside/b.txt', 'documents/./a.txt', 'documents\\a.txt'] as $path) {
        $component->instance()->data['attachments'] = ['tampered-key' => $path];
        $field = $component->instance()->getSchema('form')->getComponent('attachments');
        expect(fn () => $field->activateUploadedFile((string) array_key_first($field->getRawState())))->toThrow(MediaFailure::class);
    }
});

it('uses an authorized legacy thumbnail without replacing original download or viewer sources', function () {
    Storage::fake('public');
    Storage::disk('public')->put('documents/photo.jpg', UploadedFile::fake()->image('photo.jpg')->get());
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()->disk('public')->directory('documents')->createFormStrategy()
        ->openable()->downloadable()->fileViewer()->previewUrlUsing(fn (string $file): string => '/legacy-thumbs/'.basename($file))];
    $component = Livewire::test(TestableTranslatableForm::class)->fillForm(['attachments' => ['documents/photo.jpg']]);
    $field = $component->instance()->getSchema('form')->getComponent('attachments');
    $payload = array_values($field->getUploadedFiles())[0];
    expect($payload['url'])->toBe('/legacy-thumbs/photo.jpg')
        ->and(parse_url($payload['openableUrl'], PHP_URL_PATH))->toBe('/documents/photo.jpg')
        ->and($payload['downloadableUrl'])->toBe($payload['openableUrl']);
    expect($field->getFileViewerItems()[0]['url'])->toBe($payload['openableUrl']);
    $field->previewUrlUsing(fn () => null);
    expect(array_values($field->getUploadedFiles())[0]['url'])->toBe($payload['openableUrl']);
    $field->previewUrlUsing(fn () => 'javascript:alert(1)');
    expect(fn () => $field->getUploadedFiles())->toThrow(MediaFailure::class);
});
