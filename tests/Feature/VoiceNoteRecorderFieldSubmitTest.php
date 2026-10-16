<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

it('requires a voice note when the field is required', function () {
    TestableTranslatableForm::$formSchema = [
        VoiceNoteRecorderField::make('voice_note')
            ->required()
            ->label('Voice note'),
    ];

    $livewire = Livewire::test(TestableTranslatableForm::class);

    expect(fn () => $livewire->instance()->getSchema('form')->validate())
        ->toThrow(ValidationException::class);
});

it('accepts uploaded audio using filament file upload state shape', function () {
    Storage::fake('public');

    TestableTranslatableForm::$formSchema = [
        VoiceNoteRecorderField::make('voice_note')
            ->disk('public')
            ->directory('voice-notes'),
    ];

    $file = UploadedFile::fake()->createWithContent('voice-note.wav', 'RIFF'.pack('V', 36 + 1600).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 1600).str_repeat(chr(0), 1600));
    $fileKey = (string) Str::uuid();

    $livewire = Livewire::test(TestableTranslatableForm::class)
        ->upload("data.voice_note.{$fileKey}", [$file]);

    $state = $livewire->get('data.voice_note');

    expect($state)->toBeArray()
        ->and($state)->toHaveKey($fileKey)
        ->and($state[$fileKey])->toBeInstanceOf(TemporaryUploadedFile::class);
});

it('persists uploaded voice note to disk on form dehydrate', function () {
    Storage::fake('public');

    TestableTranslatableForm::$formSchema = [
        VoiceNoteRecorderField::make('voice_note')
            ->disk('public')
            ->directory('voice-notes'),
    ];

    $file = UploadedFile::fake()->createWithContent('voice-note.wav', 'RIFF'.pack('V', 36 + 1600).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 1600).str_repeat(chr(0), 1600));
    $fileKey = (string) Str::uuid();

    $livewire = Livewire::test(TestableTranslatableForm::class)
        ->upload("data.voice_note.{$fileKey}", [$file]);

    /** @var VoiceNoteRecorderField $field */
    $field = $livewire->instance()->getSchema('form')->getComponent('voice_note');

    $field->saveUploadedFiles();

    $storedPath = $field->getState();

    expect($storedPath)->toBeString()
        ->and($storedPath)->toEndWith('.wav');

    Storage::disk('public')->assertExists($storedPath);
});

it('deletes persisted voice note from disk through delete uploaded file api', function () {
    Storage::fake('public');

    TestableTranslatableForm::$formSchema = [
        VoiceNoteRecorderField::make('voice_note')
            ->disk('public')
            ->directory('voice-notes'),
    ];

    $file = UploadedFile::fake()->createWithContent('voice-note.wav', 'RIFF'.pack('V', 36 + 1600).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 1600).str_repeat(chr(0), 1600));
    $fileKey = (string) Str::uuid();

    $livewire = Livewire::test(TestableTranslatableForm::class)
        ->upload("data.voice_note.{$fileKey}", [$file]);

    /** @var VoiceNoteRecorderField $field */
    $field = $livewire->instance()->getSchema('form')->getComponent('voice_note');

    $field->saveUploadedFiles();

    $storedPath = $field->getState();

    Storage::disk('public')->assertExists($storedPath);

    $field->deleteUploadedFile($fileKey);

    Storage::disk('public')->assertMissing($storedPath);
    expect($field->getState())->toBeNull();
});

it('confirms deletion through a scoped Filament action without using reserved event arguments', function () {
    TestableTranslatableForm::$formSchema = [VoiceNoteRecorderField::make('voice_note')];
    $request = (string) Str::uuid();
    Livewire::test(VoiceNoteActionForm::class)
        ->call('mountAction', 'deleteVoiceNote', ['request' => $request], ['schemaComponent' => 'form.voice_note'])
        ->call('callMountedAction')
        ->assertDispatched('fff-voice-delete-confirmed', request: $request, fieldKey: 'form.voice_note');
});

class VoiceNoteActionForm extends TestableTranslatableForm implements HasActions
{
    use InteractsWithActions;
}
