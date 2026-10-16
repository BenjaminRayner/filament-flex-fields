<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Video;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Bjanczak\FilamentFlexFields\Support\VideoSources;

/** A request-local snapshot; configuration closures are not retained or process-cached. */
final readonly class VideoRenderState
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public array $options,
        public ?string $source,
        public ?string $ratio,
        public string $size,
        public string $rounding,
        public bool $fullWidth,
        public bool $disabled,
        public bool $readOnly,
        public bool $customControls,
        public bool $compact,
        public bool $qualityControl,
        public bool $speedControl,
        public ?string $embedUrl,
    ) {}

    public static function from(VideoField $field, mixed $state): self
    {
        $static = $field->getSrc();
        $source = $static ?? (is_string($state) ? SafeMediaUrl::sanitize($state) : null);
        $provider = VideoSources::resolveProvider($source, $field->allowsYoutube(), $field->allowsVimeo());
        $controls = $field->shouldShowControls();
        $native = $field->shouldUseNativeControls();
        $autoplay = $field->shouldAutoplay();
        $loop = $field->shouldLoop();
        $muted = $field->shouldStartMuted();
        $poster = $field->getPoster();
        $rates = $field->getPlaybackRates() ?? [];
        $qualities = $field->getQualityOptions();
        $custom = $controls && ! $native && $provider !== VideoSources::PROVIDER_VIMEO && $source !== null;
        $speed = $custom && count($rates) > 1;
        $quality = $custom && $provider === VideoSources::PROVIDER_HTML5 && count($qualities) > 1;
        $youtubeId = $provider === VideoSources::PROVIDER_YOUTUBE ? VideoSources::youtubeId($source) : null;
        $facade = $provider === VideoSources::PROVIDER_YOUTUBE && ! $custom;
        $noCookie = $field->shouldUseYoutubeNoCookie();
        $vars = VideoSources::youtubeIframePlayerVars($autoplay, $loop, $muted, hideYoutubeControls: true);
        if ($loop && $youtubeId !== null) {
            $vars['playlist'] = $youtubeId;
        }
        $embed = match ($provider) {
            VideoSources::PROVIDER_YOUTUBE => $youtubeId !== null ? VideoSources::youtubeEmbedUrl($youtubeId, $autoplay, $noCookie, $native || ! $controls) : null,
            VideoSources::PROVIDER_VIMEO => ($id = VideoSources::vimeoId($source)) !== null ? VideoSources::vimeoEmbedUrl($id, $autoplay, $loop, $muted) : null,
            default => null,
        };
        $disabled = $field->isDisabled();
        $readOnly = $field->isReadOnly();
        $labels = [];
        foreach (['play', 'pause', 'settings', 'speed', 'quality', 'cast', 'airplay', 'connecting'] as $key) {
            $labels[$key] = Translations::get('filament-flex-fields::default.video.'.$key);
        }
        $labels['showDuration'] = Translations::get('filament-flex-fields::default.video.show_duration');
        $labels['showRemaining'] = Translations::get('filament-flex-fields::default.video.show_remaining');
        $labels['stopCast'] = Translations::get('filament-flex-fields::default.video.stop_cast');
        $labels['stopAirplay'] = Translations::get('filament-flex-fields::default.video.stop_airplay');

        return new self(
            options: [
                'staticSrc' => $static, 'provider' => $provider, 'youtubeId' => $youtubeId,
                'youtubeNoCookie' => $noCookie, 'youtubePlayerVars' => $vars,
                'youtubeThumbnail' => $youtubeId !== null ? $poster ?? VideoSources::youtubeThumbnail($youtubeId) : null,
                'facadeOnly' => $facade, 'embedUrl' => $embed, 'poster' => $poster,
                'title' => $field->getTitle(), 'subtitle' => $field->getSubtitle(),
                'showControls' => $controls, 'nativeControls' => $native, 'autoplay' => $autoplay,
                'loop' => $loop, 'muted' => $muted, 'playsInline' => $field->shouldPlayInline(),
                'readOnly' => $disabled || $readOnly, 'fullscreenable' => $field->isFullscreenable(),
                'autoHideControls' => $field->autoHidesControls(),
                'pictureInPictureable' => $custom && $provider === VideoSources::PROVIDER_HTML5 && $field->isPictureInPictureable(),
                'volumeControl' => $field->hasVolumeControl(), 'settingsMenu' => $custom && $field->settingsMenuEnabled() && ($speed || $quality),
                'tooltips' => $custom && $field->showsTooltips(),
                'castable' => $custom && $provider === VideoSources::PROVIDER_HTML5 && $field->castEnabled(),
                'airPlayable' => $custom && $provider === VideoSources::PROVIDER_HTML5 && $field->airPlayEnabled(),
                'playbackRates' => $rates, 'qualityOptions' => $qualities, 'labels' => $labels,
                'skipSeconds' => $field->getSkipSeconds(),
                'previewSource' => $field->getPreviewSource(), 'previewThumbnails' => $field->getPreviewThumbnails(),
            ],
            source: $source, ratio: $field->getAspectRatioStyle(), size: $field->getSize(), rounding: $field->getRounding(),
            fullWidth: $field->isFullWidth(), disabled: $disabled, readOnly: $readOnly,
            customControls: $custom, compact: $field->usesCompactControls(), qualityControl: $quality, speedControl: $speed,
            embedUrl: $embed,
        );
    }

    public function fingerprint(): string
    {
        return hash('sha256', serialize([$this->options, $this->source, $this->ratio, $this->size, $this->rounding, $this->fullWidth, $this->disabled, $this->readOnly, $this->compact]));
    }
}
