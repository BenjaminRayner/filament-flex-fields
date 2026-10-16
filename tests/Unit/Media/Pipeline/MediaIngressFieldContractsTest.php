<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\VoiceNoteSpatieRecorderField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\UploadAdmission;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

beforeEach(function () {
    MediaCaptureOs::resetRuntimeState();
    MediaCaptureOs::bootSafeDefaults();
    Storage::fake('local');
    config([
        'filament-flex-fields.media_capture.disk' => 'local',
        'filament-flex-fields.media_capture.require_virus_scan' => false,
    ]);
});

it('exposes FlexFileUpload::spatie factory sugar returning Spatie class', function () {
    $field = FlexFileUpload::spatie('docs');

    expect($field)->toBeInstanceOf(FlexSpatieMediaLibraryFileUpload::class)
        ->and($field->getName())->toBe('docs');
});

it('exposes VoiceNoteRecorderField::spatie factory sugar', function () {
    $field = VoiceNoteRecorderField::spatie('note');

    expect($field)->toBeInstanceOf(VoiceNoteSpatieRecorderField::class)
        ->and($field->getName())->toBe('note');
});

it('SignatureField::disk enables store-to-disk without changing Spatie state contract', function () {
    $field = SignatureField::make('sig')->disk('signatures', 'local');

    expect($field->shouldStoreToDisk())->toBeTrue()
        ->and($field->getStorageDirectory())->toBe('signatures')
        ->and($field->getStorageDisk())->toBe('local')
        ->and($field->shouldSpatieSink())->toBeFalse();
});

it('SignatureField::spatieSink enables optional Media sink metadata path', function () {
    $field = SignatureField::make('sig')
        ->disk()
        ->spatieSink('signatures', 'local', 'sig_media_uuid');

    expect($field->shouldSpatieSink())->toBeTrue()
        ->and($field->getSpatieSinkCollection())->toBe('signatures')
        ->and($field->getSpatieSinkUuidInPath())->toBe('sig_media_uuid');
});

it('routes Spatie voice persistence through the standard admission and staged upload bridge', function () {
    $field = VoiceNoteRecorderField::spatie('note')->disk('local')->mediaStaging()->mediaAutoRecovery(false);
    $record = new class extends Model {};
    $file = new TemporaryUploadedFile('voice.wav', 'local');
    $admission = new class
    {
        public int $calls = 0;

        public function validate($field, $file): void
        {
            $this->calls++;
        }
    };
    $bridge = new class
    {
        public bool $staged = false;

        public function save($field, $file, $record = null, bool $stage = true): string
        {
            $this->staged = $stage;

            return 'voice-uuid';
        }
    };
    app()->instance(UploadAdmission::class, $admission);
    app()->instance(SpatieUploadBridge::class, $bridge);
    $callback = (new ReflectionProperty(BaseFileUpload::class, 'saveUploadedFileUsing'))->getValue($field);
    $uuid = $field->evaluate($callback, ['component' => $field, 'file' => $file, 'record' => $record]);
    expect($uuid)->toBe('voice-uuid')->and($admission->calls)->toBe(1)->and($bridge->staged)->toBeTrue()
        ->and($field->shouldAutomaticallyRecoverMedia())->toBeFalse();
});
