<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie;

use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote\PersistsVoiceNotes;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote\VoiceNoteConfiguration;

/** Optional UUID-state adapter; the ordinary recorder never loads this class. */
class VoiceNoteSpatieRecorderField extends FlexSpatieMediaLibraryFileUpload
{
    use PersistsVoiceNotes;
    use ResolvesConfiguredIcons;
    use VoiceNoteConfiguration;

    protected string $view = 'filament-flex-fields::forms.components.voice-note-recorder-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureVoiceNotes(spatie: true);
        $this->collection('voice-notes');
    }
}
