<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Validation\StaticOptionSelection;
use Closure;

/** @mixin SelectField */
trait ValidatesSelectValues
{
    protected function configureSelectValueValidation(): void
    {
        $this->rule(function (SelectField $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if (blank($value)) {
                    return;
                }

                $selection = new StaticOptionSelection;
                $valid = $component->shouldEnforceStaticOptionKeys()
                    ? $selection->accepts($component->getStaticOptionKeysForValidation(), $value, $component->isMultiple())
                    : $selection->acceptsShape($value, $component->isMultiple());
                if (! $valid) {
                    $fail(__('validation.in', ['attribute' => $component->getLabel()]));
                }
            };
        });
    }

    /**
     * When inline create is enabled, created string keys are intentional state —
     * skip Filament’s “must resolve an option label” In probe.
     *
     * For static options() with create off, the package Closure on setUp owns
     * key enforcement — also skip the Filament probe so validation does not
     * require a Livewire container solely to build rules.
     *
     * @return ?array<string>
     */
    public function getInValidationRuleValues(): ?array
    {
        if ($this->allowsCreateOption() || $this->shouldEnforceStaticOptionKeys()) {
            return null;
        }

        return parent::getInValidationRuleValues();
    }

    /**
     * Package Rule::in-equivalent applies only to configured static options().
     */
    public function shouldEnforceStaticOptionKeys(): bool
    {
        if ($this->allowsCreateOption()) {
            return false;
        }

        if ($this->hasRelationship() || $this->hasDynamicSearchResults()
            || ($this->isMultiple() ? $this->getOptionLabelsUsing !== null : $this->getOptionLabelUsing !== null)) {
            return false;
        }

        if ($this->options === null && blank($this->getEnum())) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function getStaticOptionKeysForValidation(): array
    {
        $previous = $this->allowDeferredOptionResolution;
        $this->allowDeferredOptionResolution = true;

        try {
            return $this->flattenStaticOptionKeys($this->getOptions());
        } finally {
            $this->allowDeferredOptionResolution = $previous;
        }
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @return list<string>
     */
    protected function flattenStaticOptionKeys(array $options): array
    {
        return (new StaticOptionSelection)->enabledKeys(
            $options,
            fn (string|int $value, mixed $label): array => $this->normalizeOption($value, $label),
            fn (array $label): bool => $this->isOptionGroupArray($label),
        );
    }
}
