<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\AddressAutocomplete;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AddressAutocompleteField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin AddressAutocompleteField */
trait ConfiguresAddressAutocomplete
{
    protected string|Closure $variant = 'primary';

    protected bool|Closure $searchable = true;

    protected string|BackedEnum|Htmlable|Closure|null $prefixIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $clearIcon = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->variant), ['primary', 'secondary', 'flat'], 'variant');
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }

    public function prefixIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->prefixIcon = $icon;

        return $this;
    }

    public function clearIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->clearIcon = $icon;

        return $this;
    }
}
