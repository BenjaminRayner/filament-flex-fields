<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Video;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;
use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Bjanczak\FilamentFlexFields\Support\Video\VideoPresentation;
use Closure;

/** @mixin VideoField */
trait PresentsVideo
{
    /** @var array<string, string|bool|Closure|null> */
    protected array $videoAppearance = [];

    public function poster(string|Closure|null $poster): static
    {
        $this->videoAppearance['poster'] = $poster;

        return $this;
    }

    public function title(string|Closure|null $title): static
    {
        $this->videoAppearance['title'] = $title;

        return $this;
    }

    public function subtitle(string|Closure|null $subtitle): static
    {
        $this->videoAppearance['subtitle'] = $subtitle;

        return $this;
    }

    public function ratio(string|Closure|null $ratio): static
    {
        $this->videoAppearance['ratio'] = $ratio;

        return $this;
    }

    public function placeholder(string|Closure|null $poster): static
    {
        return $this->poster($poster);
    }

    public function fullWidth(bool|Closure $condition = true): static
    {
        $this->videoAppearance['fullWidth'] = $condition;

        return $this;
    }

    protected function appearanceText(string $key): ?string
    {
        $value = $this->evaluate($this->videoAppearance[$key] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getPoster(): ?string
    {
        return SafeMediaUrl::sanitize($this->appearanceText('poster'));
    }

    public function getTitle(): ?string
    {
        return $this->appearanceText('title');
    }

    public function getSubtitle(): ?string
    {
        return $this->appearanceText('subtitle');
    }

    public function getRatio(): ?string
    {
        $ratio = array_key_exists('ratio', $this->videoAppearance) ? $this->appearanceText('ratio') : '16:9';

        return $ratio === 'auto' ? null : $ratio;
    }

    public function getAspectRatioStyle(): ?string
    {
        return VideoPresentation::aspectRatio($this->getRatio());
    }

    public function isFullWidth(): bool
    {
        return (bool) $this->evaluate($this->videoAppearance['fullWidth'] ?? false);
    }

    public function hasMetadata(): bool
    {
        return $this->getTitle() !== null || $this->getSubtitle() !== null;
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-video-field-field', 'fff-video-field-field--'.$this->getSize(), 'fff-rounding-'.$this->getRounding()];
    }
}
