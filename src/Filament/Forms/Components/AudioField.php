<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField\ConfiguresPlayback;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField\ConfiguresWaveform;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField\HasWhisperTranscription;
use Bjanczak\FilamentFlexFields\Support\Audio\AudioSourceRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

class AudioField extends Field
{
    use CanBeReadOnly;
    use ConfiguresPlayback;
    use ConfiguresWaveform;
    use HasControlSize;
    use HasFieldRounding;
    use HasWhisperTranscription;
    use ResolvesConfiguredIcons;

    protected string $view = 'filament-flex-fields::forms.components.audio-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default(null)->rules(['nullable', 'string', new AudioSourceRule]);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-audio-field-field', 'fff-audio-field-field--'.$this->getSize(), 'fff-rounding-'.$this->getRounding()];
    }
}
