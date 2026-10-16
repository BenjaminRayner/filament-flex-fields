<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresTextInputFeedback
{
    protected bool|Closure $showCharacterCounter = false;

    protected bool|Closure $clearable = false;

    protected bool|Closure $loadingIndicator = false;

    protected string|Htmlable|Closure|null $verificationStatus = null;

    protected string|BackedEnum|Htmlable|Closure|null $verificationStatusIcon = null;

    protected string|Closure $verificationStatusColor = 'primary';

    public function characterCounter(bool|Closure $condition = true): static
    {
        $this->showCharacterCounter = $condition;

        return $this;
    }

    public function shouldShowCharacterCounter(): bool
    {
        return (bool) $this->evaluate($this->showCharacterCounter);
    }

    public function getCharacterLimit(): ?int
    {
        $max = $this->getMaxLength();

        return $max === null ? null : (int) $max;
    }

    public function clearable(bool|Closure $condition = true): static
    {
        $this->clearable = $condition;

        return $this;
    }

    public function isClearable(): bool
    {
        return (bool) $this->evaluate($this->clearable);
    }

    public function loading(bool|Closure $condition = true): static
    {
        $this->loadingIndicator = $condition;

        return $this;
    }

    public function validating(bool|Closure $condition = true): static
    {
        return $this->loading($condition);
    }

    public function shouldShowLoadingIndicator(): bool
    {
        return (bool) $this->evaluate($this->loadingIndicator);
    }

    public function getLoadingWireTargets(): string
    {
        $targets = array_filter([
            $this->getStatePath(),
            $this->getLivewireKey(),
        ]);

        return implode(',', array_unique($targets));
    }

    public function verificationStatus(string|Htmlable|Closure|null $status): static
    {
        $this->verificationStatus = $status;

        return $this;
    }

    public function getVerificationStatus(): string|Htmlable|null
    {
        return $this->evaluate($this->verificationStatus);
    }

    public function hasVerificationStatus(): bool
    {
        return filled($this->getVerificationStatus());
    }

    public function verificationStatusIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->verificationStatusIcon = $icon;

        return $this;
    }

    public function getVerificationStatusIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->verificationStatusIcon) ?? FlexIcon::SealCheck;
    }

    public function verificationStatusColor(string|Closure $color): static
    {
        $this->verificationStatusColor = $color;

        return $this;
    }

    public function getVerificationStatusColor(): string
    {
        return (string) $this->evaluate($this->verificationStatusColor);
    }
}
