<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Video;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Bjanczak\FilamentFlexFields\Support\VideoSources;
use Closure;

/** @mixin VideoField */
trait ResolvesVideoSources
{
    /** @var array<string, string|bool|Closure|null> */
    protected array $videoSources = [];

    public function src(string|Closure|null $src): static
    {
        $this->videoSources['src'] = $src;

        return $this;
    }

    public function allowYoutube(bool|Closure $condition = true): static
    {
        $this->videoSources['allowYoutube'] = $condition;

        return $this;
    }

    public function allowVimeo(bool|Closure $condition = true): static
    {
        $this->videoSources['allowVimeo'] = $condition;

        return $this;
    }

    public function youtubeNoCookie(bool|Closure $condition = true): static
    {
        $this->videoSources['youtubeNoCookie'] = $condition;

        return $this;
    }

    public function getSrc(): ?string
    {
        $src = $this->evaluate($this->videoSources['src'] ?? null);

        if (! is_string($src) || $src === '') {
            return null;
        }

        return SafeMediaUrl::sanitize($src);
    }

    public function allowsYoutube(): bool
    {
        return (bool) $this->evaluate($this->videoSources['allowYoutube'] ?? true);
    }

    public function allowsVimeo(): bool
    {
        return (bool) $this->evaluate($this->videoSources['allowVimeo'] ?? true);
    }

    public function shouldUseYoutubeNoCookie(): bool
    {
        return (bool) $this->evaluate($this->videoSources['youtubeNoCookie'] ?? true);
    }

    public function resolveVideoSrc(mixed $state): ?string
    {
        $staticSrc = $this->getSrc();

        if ($staticSrc !== null) {
            return $staticSrc;
        }

        if (is_string($state) && filled($state)) {
            return SafeMediaUrl::sanitize($state);
        }

        return null;
    }

    public function resolveYoutubeId(mixed $state): ?string
    {
        if (! $this->allowsYoutube()) {
            return null;
        }

        return VideoSources::youtubeId($this->resolveVideoSrc($state));
    }

    public function usesYoutubeEmbed(mixed $state): bool
    {
        return $this->resolveYoutubeId($state) !== null;
    }

    public function resolveProvider(mixed $state): string
    {
        return VideoSources::resolveProvider(
            $this->resolveVideoSrc($state),
            $this->allowsYoutube(),
            $this->allowsVimeo(),
        );
    }

    public function resolveYoutubeEmbedUrl(mixed $state, bool $autoplay = false): ?string
    {
        $videoId = $this->resolveYoutubeId($state);

        if ($videoId === null) {
            return null;
        }

        return VideoSources::youtubeEmbedUrl(
            $videoId,
            $autoplay,
            $this->shouldUseYoutubeNoCookie(),
            $this->shouldUseNativeControls() || ! $this->shouldShowControls(),
        );
    }

    public function resolveVimeoId(mixed $state): ?string
    {
        if (! $this->allowsVimeo()) {
            return null;
        }

        return VideoSources::vimeoId($this->resolveVideoSrc($state));
    }

    public function usesVimeoEmbed(mixed $state): bool
    {
        return $this->resolveVimeoId($state) !== null;
    }

    public function resolveVimeoEmbedUrl(mixed $state, bool $autoplay = false): ?string
    {
        $videoId = $this->resolveVimeoId($state);

        if ($videoId === null) {
            return null;
        }

        return VideoSources::vimeoEmbedUrl(
            $videoId,
            $autoplay,
            $this->shouldLoop(),
            $this->shouldStartMuted(),
        );
    }

    public function usesYoutubeCustomControls(mixed $state): bool
    {
        return $this->usesYoutubeEmbed($state)
            && $this->shouldShowControls()
            && ! $this->shouldUseNativeControls();
    }

    public function usesYoutubeFacade(mixed $state): bool
    {
        return $this->usesYoutubeEmbed($state) && ! $this->usesYoutubeCustomControls($state);
    }

    /**
     * @return array<string, int|string>
     */
    public function getYoutubeIframePlayerVars(mixed $state, bool $autoplay = false): array
    {
        $videoId = $this->resolveYoutubeId($state);

        $vars = VideoSources::youtubeIframePlayerVars(
            $autoplay,
            $this->shouldLoop(),
            $this->shouldStartMuted(),
            hideYoutubeControls: true,
        );

        if ($this->shouldLoop() && $videoId !== null) {
            $vars['playlist'] = $videoId;
        }

        return $vars;
    }

    public function resolveYoutubeThumbnail(mixed $state): ?string
    {
        $videoId = $this->resolveYoutubeId($state);

        if ($videoId === null) {
            return null;
        }

        if ($this->getPoster() !== null) {
            return $this->getPoster();
        }

        return VideoSources::youtubeThumbnail($videoId);
    }
}
