<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Audio;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\VoiceNoteSpatieRecorderField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\Media\Capture\TranscriptionResolver;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Filament\Forms\Components\BaseFileUpload;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class VoiceNoteTranscript
{
    public static function persist(BaseFileUpload $field, string $identity, bool $spatie): void
    {
        if (! $field instanceof VoiceNoteRecorderField && ! $field instanceof VoiceNoteSpatieRecorderField) {
            throw new LogicException('Voice transcription requires its owning recorder field.');
        }
        $transcriber = app(TranscriptionResolver::class)->resolve();
        if ($transcriber === null || blank($metadataPath = $field->getStoreMetadataInPath())) {
            return;
        }
        $disk = $field->getDiskName();
        $path = $identity;
        if ($spatie) {
            if (! $field instanceof VoiceNoteSpatieRecorderField) {
                throw new LogicException('The Spatie transcript adapter requires a Spatie recorder.');
            }
            $media = app(SpatieUploadBridge::class)->find($field, $identity);
            if ($media === null) {
                return;
            }
            if (! $media instanceof Media) {
                throw new LogicException('The voice-note adapter requires a native Spatie media model.');
            }
            $disk = $media->disk;
            $path = $media->getPathRelativeToRoot();
        }
        $transcript = $transcriber->transcribe($disk, $path, ['field' => $field->getName(), 'kind' => 'voice_note', ...($spatie ? ['uuid' => $identity] : [])]);
        if (! is_string($transcript) || $transcript === '') {
            return;
        }
        $get = $field->makeGetUtility();
        $set = $field->makeSetUtility();
        $metadata = $get($metadataPath);
        $metadata = is_array($metadata) ? $metadata : [];
        if ($field->isMultiple()) {
            $entry = is_array($metadata[$identity] ?? null) ? $metadata[$identity] : [];
            $metadata[$identity] = [...$entry, 'transcript' => $transcript];
        } else {
            $metadata['transcript'] = $transcript;
        }
        $set($metadataPath, $metadata);
    }
}
