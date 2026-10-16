<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Video;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Support\Video\VideoPlaybackOptions;
use Bjanczak\FilamentFlexFields\Support\VideoSources;
use Closure;
use InvalidArgumentException;

/** @mixin VideoField */
trait ConfiguresVideoPlayback
{
    /** @var array<string, mixed> */
    protected array $videoPlayback = [];

    public function controls(bool|Closure $condition = true): static
    {
        $this->videoPlayback['showControls'] = $condition;

        return $this;
    }

    public function nativeControls(bool|Closure $condition = true): static
    {
        $this->videoPlayback['nativeControls'] = $condition;

        return $this;
    }

    public function autoplay(bool|Closure $condition = true): static
    {
        $this->videoPlayback['autoplay'] = $condition;

        return $this;
    }

    public function loop(bool|Closure $condition = true): static
    {
        $this->videoPlayback['loop'] = $condition;

        return $this;
    }

    public function muted(bool|Closure $condition = true): static
    {
        $this->videoPlayback['muted'] = $condition;

        return $this;
    }

    public function playsInline(bool|Closure $condition = true): static
    {
        $this->videoPlayback['playsInline'] = $condition;

        return $this;
    }

    public function fullscreenable(bool|Closure $condition = true): static
    {
        $this->videoPlayback['fullscreenable'] = $condition;

        return $this;
    }

    public function autoHideControls(bool|Closure $condition = true): static
    {
        $this->videoPlayback['autoHideControls'] = $condition;

        return $this;
    }

    public function pictureInPictureable(bool|Closure $condition = true): static
    {
        $this->videoPlayback['pictureInPictureable'] = $condition;

        return $this;
    }

    public function volumeControl(bool|Closure $condition = true): static
    {
        $this->videoPlayback['volumeControl'] = $condition;

        return $this;
    }

    public function settingsMenu(bool|Closure $condition = true): static
    {
        $this->videoPlayback['settingsMenu'] = $condition;

        return $this;
    }

    public function tooltips(bool|Closure $condition = true): static
    {
        $this->videoPlayback['tooltips'] = $condition;

        return $this;
    }

    public function castable(bool|Closure $condition = true): static
    {
        $this->videoPlayback['castable'] = $condition;

        return $this;
    }

    public function airPlayable(bool|Closure $condition = true): static
    {
        $this->videoPlayback['airPlayable'] = $condition;

        return $this;
    }

    public function skipSeconds(int|Closure $seconds): static
    {
        if (is_int($seconds) && $seconds < 1) {
            throw new InvalidArgumentException('Video skip seconds must be at least 1.');
        }
        $this->videoPlayback['skipSeconds'] = $seconds;

        return $this;
    }

    /** @param array<int, float>|bool|Closure $rates */
    public function playbackRates(array|bool|Closure $rates = true): static
    {
        $this->videoPlayback['playbackRates'] = $rates;

        return $this;
    }

    /** @param array<int, array<string, mixed>>|Closure $options */
    public function qualityOptions(array|Closure $options): static
    {
        $this->videoPlayback['qualityOptions'] = $options;

        return $this;
    }

    public function controlsLayout(string|Closure $layout): static
    {
        $this->videoPlayback['controlsLayout'] = $layout;

        return $this;
    }

    public function compactControls(bool|Closure $condition = true): static
    {
        return $this->controlsLayout(fn (): string => $this->evaluate($condition) ? 'compact' : 'default');
    }

    protected function playbackFlag(string $key, bool $default): bool
    {
        return (bool) $this->evaluate($this->videoPlayback[$key] ?? $default);
    }

    public function shouldShowControls(): bool
    {
        return $this->playbackFlag('showControls', true);
    }

    public function shouldUseNativeControls(): bool
    {
        return $this->playbackFlag('nativeControls', false);
    }

    public function shouldAutoplay(): bool
    {
        return $this->playbackFlag('autoplay', false);
    }

    public function shouldLoop(): bool
    {
        return $this->playbackFlag('loop', false);
    }

    public function shouldStartMuted(): bool
    {
        return $this->playbackFlag('muted', false);
    }

    public function shouldPlayInline(): bool
    {
        return $this->playbackFlag('playsInline', true);
    }

    public function isFullscreenable(): bool
    {
        return $this->playbackFlag('fullscreenable', true);
    }

    public function autoHidesControls(): bool
    {
        return $this->playbackFlag('autoHideControls', (bool) config('filament-flex-fields.ui.video_auto_hide_controls', true));
    }

    public function isPictureInPictureable(): bool
    {
        return $this->playbackFlag('pictureInPictureable', false);
    }

    public function hasVolumeControl(): bool
    {
        return $this->playbackFlag('volumeControl', true);
    }

    public function settingsMenuEnabled(): bool
    {
        return $this->playbackFlag('settingsMenu', true);
    }

    public function showsTooltips(): bool
    {
        return $this->playbackFlag('tooltips', true);
    }

    public function castEnabled(): bool
    {
        return $this->playbackFlag('castable', false);
    }

    public function airPlayEnabled(): bool
    {
        return $this->playbackFlag('airPlayable', false);
    }

    public function getSkipSeconds(): int
    {
        $value = (int) $this->evaluate($this->videoPlayback['skipSeconds'] ?? 10);
        if ($value < 1) {
            throw new InvalidArgumentException('Video skip seconds must be at least 1.');
        }

        return $value;
    }

    public function getControlsLayout(): string
    {
        $value = (string) $this->evaluate($this->videoPlayback['controlsLayout'] ?? 'default');
        if (! in_array($value, ['default', 'compact'], true)) {
            throw new InvalidArgumentException("Video controls layout [{$value}] is not supported.");
        }

        return $value;
    }

    public function usesCompactControls(): bool
    {
        return $this->getControlsLayout() === 'compact';
    }

    /** @return list<float>|null */
    public function getPlaybackRates(): ?array
    {
        return VideoPlaybackOptions::rates($this->evaluate($this->videoPlayback['playbackRates'] ?? null));
    }

    /** @return list<array{key: string, label: string, src: string, height: ?int, default: bool}> */
    public function getQualityOptions(): array
    {
        return VideoPlaybackOptions::qualities($this->evaluate($this->videoPlayback['qualityOptions'] ?? null));
    }

    public function hasPlaybackRateControl(mixed $state = null): bool
    {
        return ! $this->usesYoutubeFacade($state) && count($this->getPlaybackRates() ?? []) > 1;
    }

    public function hasQualityControl(mixed $state = null): bool
    {
        return $this->resolveProvider($state) === VideoSources::PROVIDER_HTML5 && count($this->getQualityOptions()) > 1;
    }

    public function showsSettingsMenu(mixed $state = null): bool
    {
        return $this->settingsMenuEnabled() && ($this->hasPlaybackRateControl($state) || $this->hasQualityControl($state));
    }

    public function supportsPictureInPicture(mixed $state = null): bool
    {
        return $this->isPictureInPictureable() && $this->resolveProvider($state) === VideoSources::PROVIDER_HTML5;
    }

    public function isCastable(mixed $state = null): bool
    {
        return $this->castEnabled() && $this->resolveVideoSrc($state) !== null && $this->resolveProvider($state) === VideoSources::PROVIDER_HTML5;
    }

    public function isAirPlayable(mixed $state = null): bool
    {
        return $this->airPlayEnabled() && $this->resolveVideoSrc($state) !== null && $this->resolveProvider($state) === VideoSources::PROVIDER_HTML5;
    }
}
