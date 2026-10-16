<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\HoldConfirm;

use Bjanczak\FilamentFlexFields\Support\Confirmation\ConfirmationOptions;
use Closure;

trait ConfiguresHoldConfirmation
{
    protected int|Closure|null $holdConfirmDuration = null;

    protected string|Closure $holdConfirmSweep = 'right';

    protected int|Closure $holdConfirmReleaseDuration = 200;

    protected bool|Closure $holdConfirmThemed = true;

    protected ?string $confirmationVariant = null;

    protected ?string $holdConfirmOriginalView = null;

    public function holdConfirm(int|Closure $duration = 2000, string|Closure $sweep = 'right'): static
    {
        $this->holdConfirmDuration = $duration;
        $this->holdConfirmSweep = $sweep;
        $this->enableConfirmation('hold');
        if ($this->getRounded() === null && $this->getColor() === 'danger') {
            $this->rounded('full');
        }

        return $this;
    }

    protected function enableConfirmation(string $variant): void
    {
        $this->holdConfirmOriginalView ??= $this->getView();
        $this->confirmationVariant = $variant;
        $this->livewireClickHandlerEnabled(false);
        $this->view('filament-flex-fields::actions.hold-confirm');
    }

    public function holdConfirmSweep(string|Closure $sweep): static
    {
        $this->holdConfirmSweep = $sweep;

        return $this;
    }

    public function holdConfirmReleaseDuration(int|Closure $duration): static
    {
        $this->holdConfirmReleaseDuration = $duration;

        return $this;
    }

    public function holdConfirmThemed(bool|Closure $themed = true): static
    {
        $this->holdConfirmThemed = $themed;

        return $this;
    }

    public function getHoldConfirmDuration(): ?int
    {
        $value = $this->evaluate($this->holdConfirmDuration);

        return $value === null ? null : ConfirmationOptions::milliseconds($value, 'Hold duration', 200);
    }

    public function getHoldConfirmReleaseDuration(): int
    {
        return ConfirmationOptions::milliseconds($this->evaluate($this->holdConfirmReleaseDuration), 'Release duration', maximum: 5000);
    }

    public function getHoldConfirmSweep(): string
    {
        return ConfirmationOptions::choice($this->evaluate($this->holdConfirmSweep), 'hold sweep', ['right', 'left', 'up', 'down']);
    }

    public function hasHoldConfirm(): bool
    {
        return $this->confirmationVariant !== null;
    }

    public function getConfirmationVariant(): ?string
    {
        return $this->confirmationVariant;
    }

    public function isConfirmMorph(): bool
    {
        return $this->confirmationVariant === 'morph';
    }

    public function isHoldConfirmThemed(): bool
    {
        return (bool) $this->evaluate($this->holdConfirmThemed);
    }

    public function getHoldConfirmPalette(): ?string
    {
        return $this->isHoldConfirmThemed() && $this->getColor() === 'danger' ? 'danger' : null;
    }
}
