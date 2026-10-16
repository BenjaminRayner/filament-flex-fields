<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\CreditCard;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use InvalidArgumentException;

trait ConfiguresCreditCard
{
    protected string|Closure $variant = 'midnight';

    protected string|Closure $inputVariant = 'primary';

    protected bool|Closure $flipOnCvvFocus = true;

    protected string|Closure|null $numberLabel = null;

    protected string|Closure|null $nameLabel = null;

    protected string|Closure|null $expiryLabel = null;

    protected string|Closure|null $cvvLabel = null;

    protected string|Closure|null $mark = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function inputVariant(string|Closure $inputVariant): static
    {
        $this->inputVariant = $inputVariant;

        return $this;
    }

    public function flipOnCvvFocus(bool|Closure $condition = true): static
    {
        $this->flipOnCvvFocus = $condition;

        return $this;
    }

    public function numberLabel(string|Closure|null $label): static
    {
        $this->numberLabel = $label;

        return $this;
    }

    public function nameLabel(string|Closure|null $label): static
    {
        $this->nameLabel = $label;

        return $this;
    }

    public function expiryLabel(string|Closure|null $label): static
    {
        $this->expiryLabel = $label;

        return $this;
    }

    public function cvvLabel(string|Closure|null $label): static
    {
        $this->cvvLabel = $label;

        return $this;
    }

    public function mark(string|Closure|null $mark): static
    {
        $this->mark = $mark;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['midnight', 'ocean', 'sunset', 'slate'], true)) {
            throw new InvalidArgumentException("Credit card variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function getInputVariant(): string
    {
        $inputVariant = (string) $this->evaluate($this->inputVariant);

        if (! in_array($inputVariant, ['primary', 'secondary', 'flat'], true)) {
            throw new InvalidArgumentException("Credit card input variant [{$inputVariant}] is not supported.");
        }

        return $inputVariant;
    }

    public function shouldFlipOnCvvFocus(): bool
    {
        return (bool) $this->evaluate($this->flipOnCvvFocus);
    }

    public function getNumberLabel(): string
    {
        $label = $this->evaluate($this->numberLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.credit_card.number');
    }

    public function getNameLabel(): string
    {
        $label = $this->evaluate($this->nameLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.credit_card.name');
    }

    public function getExpiryLabel(): string
    {
        $label = $this->evaluate($this->expiryLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.credit_card.expiry');
    }

    public function getCvvLabel(): string
    {
        $label = $this->evaluate($this->cvvLabel);

        return filled($label)
            ? (string) $label
            : Translations::get('filament-flex-fields::default.credit_card.cvv');
    }

    public function getMark(): string
    {
        $mark = $this->evaluate($this->mark);

        return filled($mark)
            ? (string) $mark
            : Translations::get('filament-flex-fields::default.credit_card.mark');
    }
}
