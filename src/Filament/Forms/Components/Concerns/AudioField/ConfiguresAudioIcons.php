<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresAudioIcons
{
    protected string|BackedEnum|Htmlable|Closure|null $playIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $pauseIcon = null;

    public function playIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->playIcon = $icon;

        return $this;
    }

    public function pauseIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->pauseIcon = $icon;

        return $this;
    }

    public function getPlayIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->playIcon, 'audio_play_icon', FlexIcon::PlayFill);
    }

    public function getPauseIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->pauseIcon, 'audio_pause_icon', FlexIcon::PauseFill);
    }
}
