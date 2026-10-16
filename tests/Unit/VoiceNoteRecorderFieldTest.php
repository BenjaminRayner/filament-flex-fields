<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\WebmAudio;
use Bjanczak\FilamentFlexFields\Support\Playground\VoiceNoteRecorderFieldPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Symfony\Component\Process\Process;

it('exposes voice note recorder field configuration api', function () {
    $field = VoiceNoteRecorderField::make('voice_note')
        ->maxDuration(60)
        ->size('sm');

    expect($field->getMaxDuration())->toBe(60)
        ->and($field->getSize())->toBe('sm')
        ->and($field->shouldUploadImmediately())->toBeFalse();
});

it('can upload immediately after recording', function () {
    $field = VoiceNoteRecorderField::make('voice_note')
        ->uploadImmediately();

    expect($field->shouldUploadImmediately())->toBeTrue();
});

it('can defer upload until form submit', function () {
    $field = VoiceNoteRecorderField::make('voice_note')
        ->uploadOnSubmit();

    expect($field->shouldUploadImmediately())->toBeFalse();
});

it('has sensible default accepted file types', function () {
    $field = VoiceNoteRecorderField::make('voice_note');

    expect($field->getAcceptedFileTypes())->toBe([
        'audio/*',
        'audio/mpeg',
        'audio/wav',
        'audio/webm',
        'video/webm',
        'audio/ogg',
        'audio/x-m4a',
        'audio/aac',
    ]);
});

it('resolves initial audio url from state', function () {
    $field = VoiceNoteRecorderField::make('voice_note')
        ->disk('public')->visibility('public');

    TestableTranslatableForm::$formSchema = [$field];

    $livewire = Livewire::test(TestableTranslatableForm::class);
    $livewireComponent = $livewire->instance();

    /** @var VoiceNoteRecorderField $resolvedField */
    $resolvedField = $livewireComponent->getSchema('form')->getComponent('voice_note');

    expect($resolvedField->getInitialAudioUrl())->toBeNull();

    $resolvedField->state('voice-notes/sample.wav');

    Storage::fake('public');
    Storage::disk('public')->put('voice-notes/sample.wav', 'audio');
    Storage::disk('public')->put('voice-notes/array.wav', 'audio');
    // Missing files must never resolve to an unverified storage URL.
    expect(parse_url($resolvedField->getInitialAudioUrl(), PHP_URL_PATH))->toBe('/voice-notes/sample.wav');

    $resolvedField->state(['voice-notes/missing.wav']);
    expect($resolvedField->getInitialAudioUrl())->toBeNull();

    $resolvedField->state(['voice-notes/array.wav']);
    expect(parse_url($resolvedField->getInitialAudioUrl(), PHP_URL_PATH))->toBe('/voice-notes/array.wav');
});

it('uses Hugeicons for voice note controls by default', function () {
    $field = VoiceNoteRecorderField::make('voice_note');

    expect($field->getPlayIcon())->toBe(FlexIcon::PlayFill)
        ->and($field->getPauseIcon())->toBe(FlexIcon::PauseFill)
        ->and($field->getMicrophoneIcon())->toBe(FlexIcon::Microphone)
        ->and($field->getStopIcon())->toBe('hugeicons-stop')
        ->and($field->getTrashIcon())->toBe(FlexIcon::TrashBin)
        ->and($field->getCheckmarkIcon())->toBe(FlexIcon::Check);
});

it('allows overriding voice note control icons', function () {
    $field = VoiceNoteRecorderField::make('voice_note')
        ->playIcon(Heroicon::OutlinedPlay)
        ->pauseIcon(Heroicon::OutlinedPause)
        ->microphoneIcon(Heroicon::OutlinedMicrophone)
        ->stopIcon(Heroicon::OutlinedStop)
        ->trashIcon(Heroicon::OutlinedTrash)
        ->checkmarkIcon(Heroicon::OutlinedCheck);

    expect($field->getPlayIcon())->toBe(Heroicon::OutlinedPlay)
        ->and($field->getPauseIcon())->toBe(Heroicon::OutlinedPause)
        ->and($field->getMicrophoneIcon())->toBe(Heroicon::OutlinedMicrophone)
        ->and($field->getStopIcon())->toBe(Heroicon::OutlinedStop)
        ->and($field->getTrashIcon())->toBe(Heroicon::OutlinedTrash)
        ->and($field->getCheckmarkIcon())->toBe(Heroicon::OutlinedCheck);
});

it('registers voice note field playground variants', function () {
    $builder = app(VoiceNoteRecorderFieldPlayground::class);
    $state = $builder->defaultState();

    expect($state)->toHaveKeys([
        'voice_note__basic',
        'voice_note__sm',
        'voice_note__lg',
        'voice_note__with_limit',
        'voice_note__immediate',
    ]);
});

it('loads the disk recorder without attempting to autoload an optional Spatie driver', function () {
    $autoload = realpath(__DIR__.'/../../vendor/autoload.php');
    $source = 'require '.var_export($autoload, true).'; spl_autoload_register(static function (string $class): void { if (str_contains($class, "Spatie") && str_contains($class, "FileUpload")) { throw new RuntimeException("Optional driver unexpectedly loaded: ".$class); } }, true, true); echo class_exists('.var_export(VoiceNoteRecorderField::class, true).') ? "native-loaded" : "missing";';
    $process = new Process([PHP_BINARY, '-r', $source]);
    $process->run();
    expect($process->getExitCode())->toBe(0)->and($process->getOutput())->toBe('native-loaded');
});

it('bounds recording duration and bitrate without silently coercing closure values', function (string $method, mixed $value, string $getter) {
    VoiceNoteRecorderField::make('voice')->{$method}(fn () => $value)->{$getter}();
})->with([
    ['maxDuration', 0, 'getMaxDuration'], ['maxDuration', 3601, 'getMaxDuration'],
    ['maxDuration', '120', 'getMaxDuration'], ['audioBitsPerSecond', 15999, 'getAudioBitsPerSecond'],
    ['audioBitsPerSecond', 512001, 'getAudioBitsPerSecond'],
])->throws(InvalidArgumentException::class);

it('configures deletion confirmation and voice quality alongside Media OS policy', function () {
    $field = VoiceNoteRecorderField::make('voice')->audioBitsPerSecond(64000)->confirmDeletion(false)
        ->mediaPolicy(new MediaPolicy(maxBytes: 1024, visibility: 'private'));
    expect($field->getAudioBitsPerSecond())->toBe(64000)->and($field->shouldConfirmDeletion())->toBeFalse()
        ->and($field->getMediaPolicy()->maxBytes)->toBe(1024)->and($field->getMediaPolicy()->visibility)->toBe('private');
});

it('resolves waveform metadata in the owning form and repeater scope', function () {
    $field = VoiceNoteRecorderField::make('voice')->storeWaveformIn('voice_meta');
    Schema::make()->statePath('data.rows.abc')->components([$field])->getComponents();
    expect($field->resolveRelativeStatePath($field->getStoreWaveformInPath()))->toBe('data.rows.abc.voice_meta');
    expect($field->resolveRelativeStatePath('/data.shared'))->toBe('data.shared');
});

it('distinguishes audio WebM from video tracks before Media OS admission', function () {
    $element = static fn (string $id, string $data): string => $id.chr(128 + strlen($data)).$data;
    $inspect = static function (string $tracks) use ($element): bool {
        $path = tempnam(sys_get_temp_dir(), 'voice-ebml-');
        file_put_contents($path, $element("\x1a\x45\xdf\xa3", '')."\x18\x53\x80\x67\xff".$element("\x16\x54\xae\x6b", $tracks));
        try {
            return WebmAudio::isAudioOnly($path);
        } finally {
            unlink($path);
        }
    };
    $audio = $element("\xae", $element("\x83", "\x02").$element("\x86", 'A_OPUS'));
    $video = $element("\xae", $element("\x83", "\x01").$element("\x86", 'V_VP8'));
    expect($inspect($audio))->toBeTrue()->and($inspect($video))->toBeFalse()->and($inspect($audio.$video))->toBeFalse();
    $field = VoiceNoteRecorderField::make('voice');
    Schema::make()->components([$field])->getComponents();
    expect($field->makeUploadMediaWrite()->policy->mimeTypes)->not->toContain('video/webm');
});
