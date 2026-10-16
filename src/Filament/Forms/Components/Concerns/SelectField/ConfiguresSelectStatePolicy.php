<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;

/** @mixin SelectField */
trait ConfiguresSelectStatePolicy
{
    protected bool|Closure|null $clearable = null;

    // Filament's renderer chooses JavaScript when native(true) is combined with
    // search, multiple or HTML. Inherit those configuration methods unchanged:
    // fluent call order and evaluated closures must not change that contract.

    public function clearable(bool|Closure $condition = true): static
    {
        $this->clearable = $condition;

        parent::selectablePlaceholder($condition);

        return $this;
    }

    public function isClearable(): bool
    {
        if ($this->clearable !== null) {
            return (bool) $this->evaluate($this->clearable);
        }

        return $this->getVariant() !== 'item-card';
    }

    public function selectablePlaceholder(bool|Closure $condition = true): static
    {
        return parent::selectablePlaceholder($condition);
    }

    /**
     * Whether the clear (×) control and null-placeholder selection are available in the UI.
     * Combines Flex Fields `clearable()` with Filament `selectablePlaceholder()`.
     */
    public function isClearableInUi(): bool
    {
        return $this->isClearable() && $this->canSelectPlaceholder();
    }
}
