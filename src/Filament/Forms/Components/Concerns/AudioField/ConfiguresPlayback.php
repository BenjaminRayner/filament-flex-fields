<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AudioField;

use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Closure;

trait ConfiguresPlayback
{
    use ConfiguresAudioIcons;

    protected string|Closure|null $src = null;

    protected bool|Closure $isFullWidth = false;

    protected bool|Closure $loop = false;

    public function src(string|Closure|null $src): static
    {
        $this->src = $src;

        return $this;
    }

    public function fullWidth(bool|Closure $condition = true): static
    {
        $this->isFullWidth = $condition;

        return $this;
    }

    public function loop(bool|Closure $condition = true): static
    {
        $this->loop = $condition;

        return $this;
    }

    public function isFullWidth(): bool
    {
        return (bool) $this->evaluate($this->isFullWidth);
    }

    public function shouldLoop(): bool
    {
        return (bool) $this->evaluate($this->loop);
    }

    public function getSrc(): ?string
    {
        $value = $this->evaluate($this->src);

        return is_string($value) && filled($value) ? SafeMediaUrl::sanitize($value) : null;
    }

    public function resolveAudioSrc(mixed $state): ?string
    {
        return $this->getSrc() ?? (is_string($state) && filled($state) ? SafeMediaUrl::sanitize($state) : null);
    }
}
