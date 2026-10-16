<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AudioField;

AudioField::make('recording_url')
    ->label('Voice message');

AudioField::make('wide_recording_url')
    ->fullWidth()
    ->size('lg');

AudioField::make('notification')
    ->src('/audio/notification.mp3')
    ->waveform([20, 45, 80, 60, 35, 90, 50, 30])
    ->loop();

AudioField::make('archived_url')
    ->readOnly()
    ->size('sm');

// Optional browser transcription requires fff:whisper:install on the host.
AudioField::make('support_call_url')
    ->transcription()
    ->whisperModel('Xenova/whisper-tiny')
    ->whisperLanguage('pl')
    ->whisperQuantized()
    ->fullWidth();

AudioField::make('translated_url')
    ->transcription()
    ->whisperMultilingual()
    ->whisperTask('translate');

AudioField::make('fixed_settings_url')
    ->transcription()
    ->transcriptionSettings(false)
    ->whisperMultilingual(false)
    ->whisperModel('Xenova/whisper-tiny.en');
