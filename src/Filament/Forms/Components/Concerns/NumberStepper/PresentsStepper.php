<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NumberStepper;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Bjanczak\FilamentFlexFields\Support\NumberStepper\SuffixForms;

/** @mixin NumberStepper */
trait PresentsStepper
{
    public function hasDisplayValue(mixed $value): bool
    {
        return $this->normalizeDisplayValue($value) !== null;
    }

    public function canStepValue(mixed $value, int $direction): bool
    {
        if ($this->isDisabled()) {
            return false;
        }
        $number = $this->normalizeDisplayValue($value);
        if ($number !== null && $this->isInteger()) {
            $number = NumericValue::round($number);
        }
        if ($direction > 0) {
            return $number === null || $this->getMaxValue() === null || $number < $this->getMaxValue();
        }

        return $number !== null && ($this->getMinValue() === null
            ? ($this->isNullable() || $number > 0)
            : ($this->isNullable() || $number > $this->getMinValue()));
    }

    protected function normalizeDisplayValue(mixed $value): ?float
    {
        $numeric = NumericValue::parse($value);

        return $numeric === null ? null : (float) $numeric;
    }

    public function formatDisplayMain(mixed $value): ?string
    {
        $numeric = $this->normalizeDisplayValue($value);
        if ($numeric === null) {
            return null;
        }

        $numeric = $this->isInteger() ? NumericValue::round($numeric) : $numeric;
        $places = $this->getDecimalPlaces();
        $formatted = $this->isInteger() && $places === null && $this->getLocale() === null
            ? sprintf('%.0f', $numeric)
            : $this->formatNumberForLocale($numeric, $places);

        return ($this->getDisplayPrefix() ?? '').$formatted;
    }

    public function getSuffixForValue(mixed $value): ?string
    {
        $numeric = $this->normalizeDisplayValue($value);
        $forms = $this->getSuffixForms();

        return $forms === [] || $numeric === null
            ? $this->getDisplaySuffix()
            : SuffixForms::resolve($forms, $this->isInteger() ? NumericValue::round($numeric) : $numeric, $this->getLocale());
    }

    public function formatDisplayValue(mixed $value): ?string
    {
        $main = $this->formatDisplayMain($value);
        if ($main === null) {
            return null;
        }

        $suffix = $this->getSuffixForValue($value);

        return filled($suffix) ? $main."\u{00a0}".$suffix : $main;
    }

    public function formatBucketedDisplay(float $value): string
    {
        $numeric = is_finite($value) ? abs($value) : 88.0;
        $digits = max(2, strlen(sprintf('%.0f', floor($numeric))));
        $integer = str_repeat('8', $digits);
        $places = $this->getDecimalPlaces();
        $probe = $integer.($places !== null && $places > 0 ? '.'.str_repeat('8', $places) : '');

        return ($this->getDisplayPrefix() ?? '').($this->getLocale() === null
            ? $probe
            : $this->formatNumberForLocale((float) $probe, $places ?? 0));
    }

    public function getWidthAnchorMainText(): string
    {
        return $this->formatBucketedDisplay(88.0);
    }

    public function getWidthAnchorText(): string
    {
        $main = $this->getWidthAnchorMainText();
        $candidates = [$main];
        $suffixes = $this->getSuffixForms() ?: [$this->getDisplaySuffix() ?? ''];
        foreach ($suffixes as $suffix) {
            if (filled($suffix)) {
                $candidates[] = $main."\u{00a0}".$suffix;
            }
        }
        if ($this->isNullable()) {
            $candidates[] = $this->getNullLabel() ?? '—';
        }

        return $this->pickWidestString($candidates);
    }

    public function getInitialSizerText(mixed $state): string
    {
        $candidates = [$this->getWidthAnchorText()];
        $numeric = $this->normalizeDisplayValue($state);
        if ($numeric !== null) {
            $main = $this->formatBucketedDisplay($numeric);
            $suffix = $this->getSuffixForValue($state);
            $candidates[] = filled($suffix) ? $main."\u{00a0}".$suffix : $main;
            $candidates[] = $this->formatDisplayValue($state) ?? '';
        }

        return $this->pickWidestString($candidates);
    }

    /** @param list<string> $candidates */
    protected function pickWidestString(array $candidates): string
    {
        return array_reduce($candidates, fn (string $widest, string $candidate): string => mb_strlen($candidate) > mb_strlen($widest) ? $candidate : $widest, '');
    }

    /** @return array<string, mixed> */
    public function getStepperRuntimeConfiguration(): array
    {
        return [
            ...$this->getNumericPolicy(),
            'statePath' => $this->getStatePath(),
            'liveDebounce' => $this->isLiveDebounced() ? $this->getNormalizedLiveDebounce() : null,
            'integer' => $this->isInteger(),
            'nullable' => $this->isNullable(),
            'disabled' => $this->isDisabled(),
            'nullLabel' => $this->getNullLabel() ?? '—',
            'prefix' => $this->getDisplayPrefix(),
            'suffix' => $this->getDisplaySuffix(),
            'suffixForms' => $this->getSuffixForms(),
            'decimalPlaces' => $this->getDecimalPlaces(),
            'locale' => $this->getLocale(),
            'wheelAnimated' => $this->isWheelAnimated(),
            'widthAnchor' => $this->getWidthAnchorText(),
        ];
    }
}
