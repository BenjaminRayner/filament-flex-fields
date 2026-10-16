<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote;

use Bjanczak\FilamentFlexFields\Support\Audio\VoiceNoteTranscript;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Both adapters retain the native upload lifecycle and one Media OS persistence engine. */
trait PersistsVoiceNotes
{
    public function makeUploadMediaWrite(?TemporaryUploadedFile $file = null, ?string $adapter = null): MediaWrite
    {
        $write = parent::makeUploadMediaWrite($file, $adapter);
        $base = $write->policy ?? new MediaPolicy;
        $audioTypes = array_values(array_filter($base->mimeTypes, static fn (string $mime): bool => str_starts_with($mime, 'audio/')));
        if ($base->mimeTypes !== [] && $audioTypes === []) {
            throw new InvalidArgumentException('Voice-note policies must allow an audio MIME type.');
        }
        $policy = new MediaPolicy(
            maxBytes: $base->maxBytes, minBytes: $base->minBytes,
            mimeTypes: $audioTypes === [] ? ['audio/*'] : $audioTypes,
            requireScanner: $base->requireScanner, scanBeforeWrite: $base->scanBeforeWrite,
            scanAfterWrite: $base->scanAfterWrite, verifyIntegrity: $base->verifyIntegrity,
            visibility: $base->visibility, quarantineDisk: $base->quarantineDisk,
        );

        return new MediaWrite(
            adapter: $write->adapter, kind: 'voice_note', disk: $write->disk,
            directory: $write->directory, filename: $write->filename, field: $write->field,
            record: $write->record, spatie: $write->spatie, policy: $policy,
            metadata: $write->metadata, processors: $write->processors, operationId: $write->operationId,
        );
    }

    protected function configureVoiceNotes(bool $spatie = false): void
    {
        $this->acceptedFileTypes(['audio/*', 'audio/mpeg', 'audio/wav', 'audio/webm', 'video/webm', 'audio/ogg', 'audio/x-m4a', 'audio/aac']);
        $this->maxSize(25600)->maxFiles(1);
        $this->registerActions([
            fn (): Action => Action::make('deleteVoiceNote')
                ->label(fn () => Translations::get('filament-flex-fields::default.audio.delete'))
                ->color('danger')->icon($this->getTrashIcon())->modalIcon($this->getTrashIcon())
                ->requiresConfirmation()
                ->modalDescription(fn () => Translations::get('filament-flex-fields::default.audio.delete_confirm'))
                ->disabled(fn (): bool => $this->isDisabled())
                ->action(function (array $arguments, Component $livewire): void {
                    $request = $arguments['request'] ?? null;
                    if (! is_string($request) || ! preg_match('/^[a-f0-9-]{36}$/D', $request)) {
                        return;
                    }
                    $livewire->dispatch('fff-voice-delete-confirmed', request: $request, fieldKey: $this->getKey());
                }),
        ]);
        $this->afterPersistUploadedFileUsing(static function (BaseFileUpload $component, string $path) use ($spatie): void {
            VoiceNoteTranscript::persist($component, $path, $spatie);
        });
    }
}
