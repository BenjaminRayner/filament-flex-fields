<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Support\Video\Validation\VideoSourceRule;
use Bjanczak\FilamentFlexFields\Support\Video\VideoRenderState;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

class VideoField extends Field
{
    use CanBeReadOnly;
    use Concerns\Video\ConfiguresVideoPlayback;
    use Concerns\Video\ConfiguresVideoPreviews;
    use Concerns\Video\HasVideoIcons;
    use Concerns\Video\PresentsVideo;
    use Concerns\Video\ResolvesVideoSources;
    use HasControlSize;
    use HasFieldRounding;
    use ResolvesConfiguredIcons;

    protected string $view = 'filament-flex-fields::forms.components.video-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(null);

        $this->rules(['nullable', 'string', new VideoSourceRule]);

        $defaultLayout = config('filament-flex-fields.ui.video_controls_layout');

        if (is_string($defaultLayout) && in_array($defaultLayout, ['default', 'compact'], true)) {
            $this->controlsLayout($defaultLayout);
        }
    }

    public function getVideoRenderState(mixed $state): VideoRenderState
    {
        return VideoRenderState::from($this, $state);
    }
}
