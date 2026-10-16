<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote\PersistsVoiceNotes;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\VoiceNote\VoiceNoteConfiguration;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\VoiceNoteSpatieRecorderField;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use LogicException;

class VoiceNoteRecorderField extends FlexFileUpload
{
    use PersistsVoiceNotes;
    use ResolvesConfiguredIcons;
    use VoiceNoteConfiguration;

    protected string $view = 'filament-flex-fields::forms.components.voice-note-recorder-field';

    public function disk(string|Closure|null $name = null): static
    {
        return $name === null ? $this : parent::disk($name);
    }

    /** @return VoiceNoteSpatieRecorderField */
    public static function spatie(string $name): BaseFileUpload
    {
        if (! class_exists(SpatieMediaLibraryFileUpload::class)) {
            throw new LogicException('Install filament/spatie-laravel-media-library-plugin before selecting the Spatie upload driver.');
        }

        return VoiceNoteSpatieRecorderField::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureVoiceNotes();
        $this->deleteFileOnRemove();
    }
}
