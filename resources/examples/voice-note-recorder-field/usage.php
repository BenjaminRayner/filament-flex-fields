<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VoiceNoteRecorderField;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Filament\Forms\Components\Hidden;

VoiceNoteRecorderField::make('voice_note')
    ->label('Record voice note')
    ->directory('voice-notes')
    ->storeMetadataIn('voice_note_meta')
    ->storeWaveformIn('voice_note_meta');

VoiceNoteRecorderField::make('short_note')
    ->directory('voice-notes')->maxDuration(30)->size('sm');

VoiceNoteRecorderField::make('immediate_note')
    ->directory('voice-notes')->uploadImmediately();

// Encoder quality and confirmation modal.
VoiceNoteRecorderField::make('feedback')
    ->label('Feedback')
    ->maxDuration(60)
    ->audioBitsPerSecond(64000)
    ->maxSize(5 * 1024)
    ->confirmDeletion()
    ->directory('feedback');

// Private storage with the shared admission policy.
VoiceNoteRecorderField::make('private_voice')
    ->disk('s3')
    ->directory('private/voice')
    ->visibility('private')
    ->mediaPolicy(new MediaPolicy(
        maxBytes: 5 * 1024 * 1024,
        mimeTypes: ['audio/*'],
        visibility: 'private',
    ));

// Optional integration: install and configure the Filament Spatie plugin first.
VoiceNoteRecorderField::spatie('meeting_voice')
    ->collection('voice-notes')
    ->maxDuration(300)
    ->uploadImmediately();

// The sidecar must exist in the owning form state and use an array cast in Eloquent.
VoiceNoteRecorderField::make('note_with_metadata')
    ->storeMetadataIn('note_meta')
    ->storeWaveformIn('note_meta');

Hidden::make('note_meta')->default([]);
