<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresTextInputAppearance
{
    protected string|Closure $variant = 'primary';

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['primary', 'secondary', 'flat', 'soft'], true)) {
            throw new InvalidArgumentException("Flex text input variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return [
            'fff-flex-text-input-field',
            'fff-flex-text-input-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(),
            'fff-flex-text-input-field--'.$this->getVariant(),
        ];
    }

    public function prefix(string|Htmlable|Closure|null $label, bool|Closure $isInline = true): static
    {
        return parent::prefix($label, $isInline);
    }

    public function suffix(string|Htmlable|Closure|null $label, bool|Closure $isInline = true): static
    {
        return parent::suffix($label, $isInline);
    }

    public function prefixIcon(string|BackedEnum|Htmlable|Closure|null $icon, bool|Closure $isInline = true): static
    {
        return parent::prefixIcon($icon, $isInline);
    }

    public function suffixIcon(string|BackedEnum|Htmlable|Closure|null $icon, bool|Closure $isInline = true): static
    {
        return parent::suffixIcon($icon, $isInline);
    }

    public function prefixAction(Action|Closure $action, bool|Closure $isInline = true): static
    {
        return parent::prefixAction($action, $isInline);
    }

    public function prefixActions(array $actions, bool|Closure $isInline = true): static
    {
        return parent::prefixActions($actions, $isInline);
    }

    public function suffixAction(Action|Closure $action, bool|Closure $isInline = true): static
    {
        return parent::suffixAction($action, $isInline);
    }

    public function suffixActions(array $actions, bool|Closure $isInline = true): static
    {
        return parent::suffixActions($actions, $isInline);
    }
}
