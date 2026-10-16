<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Video;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\Video\VideoIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin VideoField */
trait HasVideoIcons
{
    /** @var array<string, string|BackedEnum|Htmlable|Closure|null> */
    protected array $videoIcons = [];

    public function playIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['playIcon'] = $icon;

        return $this;
    }

    public function getPlayIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['playIcon'] ?? null, 'video_play_icon', VideoIcon::Play);
    }

    public function pauseIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['pauseIcon'] = $icon;

        return $this;
    }

    public function getPauseIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['pauseIcon'] ?? null, 'video_pause_icon', VideoIcon::Pause);
    }

    public function volumeIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['volumeIcon'] = $icon;

        return $this;
    }

    public function getVolumeIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['volumeIcon'] ?? null, 'video_volume_icon', VideoIcon::VolumeHigh);
    }

    public function muteIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['muteIcon'] = $icon;

        return $this;
    }

    public function getMuteIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['muteIcon'] ?? null, 'video_mute_icon', VideoIcon::VolumeOff);
    }

    public function fullscreenIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['fullscreenIcon'] = $icon;

        return $this;
    }

    public function getFullscreenIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['fullscreenIcon'] ?? null, 'video_fullscreen_icon', VideoIcon::FullscreenEnter);
    }

    public function exitFullscreenIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['exitFullscreenIcon'] = $icon;

        return $this;
    }

    public function getExitFullscreenIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['exitFullscreenIcon'] ?? null, 'video_exit_fullscreen_icon', VideoIcon::FullscreenExit);
    }

    public function pictureInPictureIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['pictureInPictureIcon'] = $icon;

        return $this;
    }

    public function getPictureInPictureIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['pictureInPictureIcon'] ?? null, 'video_picture_in_picture_icon', VideoIcon::PictureInPictureEnter);
    }

    public function exitPictureInPictureIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['exitPictureInPictureIcon'] = $icon;

        return $this;
    }

    public function getExitPictureInPictureIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['exitPictureInPictureIcon'] ?? null, 'video_exit_picture_in_picture_icon', VideoIcon::PictureInPictureExit);
    }

    public function settingsIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['settingsIcon'] = $icon;

        return $this;
    }

    public function getSettingsIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['settingsIcon'] ?? null, 'video_settings_icon', VideoIcon::Settings);
    }

    public function speedIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['speedIcon'] = $icon;

        return $this;
    }

    public function getSpeedIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['speedIcon'] ?? null, 'video_speed_icon', VideoIcon::Speed);
    }

    public function qualityIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['qualityIcon'] = $icon;

        return $this;
    }

    public function getQualityIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['qualityIcon'] ?? null, 'video_quality_icon', VideoIcon::Quality);
    }

    public function castIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['castIcon'] = $icon;

        return $this;
    }

    public function getCastIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['castIcon'] ?? null, 'video_cast_icon', VideoIcon::CastEnter);
    }

    public function airPlayIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['airPlayIcon'] = $icon;

        return $this;
    }

    public function getAirPlayIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['airPlayIcon'] ?? null, 'video_airplay_icon', VideoIcon::AirPlayEnter);
    }

    public function volumeLowIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['volumeLowIcon'] = $icon;

        return $this;
    }

    public function getVolumeLowIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->getVolumeIcon();

        return $this->resolveFieldIcon($this->videoIcons['volumeLowIcon'] ?? null, 'video_volume_low_icon', $icon === VideoIcon::VolumeHigh ? VideoIcon::VolumeLow : $icon);
    }

    public function exitCastIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['exitCastIcon'] = $icon;

        return $this;
    }

    public function getExitCastIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->getCastIcon();

        return $this->resolveFieldIcon($this->videoIcons['exitCastIcon'] ?? null, 'video_exit_cast_icon', $icon === VideoIcon::CastEnter ? VideoIcon::CastExit : $icon);
    }

    public function exitAirPlayIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['exitAirPlayIcon'] = $icon;

        return $this;
    }

    public function getExitAirPlayIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->getAirPlayIcon();

        return $this->resolveFieldIcon($this->videoIcons['exitAirPlayIcon'] ?? null, 'video_exit_airplay_icon', $icon === VideoIcon::AirPlayEnter ? VideoIcon::AirPlayExit : $icon);
    }

    public function placeholderIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->videoIcons['placeholderIcon'] = $icon;

        return $this;
    }

    public function getPlaceholderIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->videoIcons['placeholderIcon'] ?? null, 'video_placeholder_icon', FlexIcon::Video);
    }
}
