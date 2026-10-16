<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\DualListbox;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait PresentsDualListbox
{
    /** @return array<string, array{label: string, description: ?string, disabled: bool}> */
    public function getNormalizedOptions(): array
    {
        $dynamic = $this->options instanceof Closure || $this->disabledOptions instanceof Closure;
        if (! $dynamic && $this->normalizedOptionsCache !== null) {
            return $this->normalizedOptionsCache;
        }
        $source = $this->evaluate($this->options);
        if (! is_array($source) || count($source) > 50000) {
            throw new InvalidArgumentException('Options must be an array with at most 50000 entries.');
        }
        $disabled = array_fill_keys(array_map(strval(...), $this->getDisabledOptions()), true);
        $normalized = [];
        foreach ($source as $value => $option) {
            $key = (string) $value;
            if (strlen($key) > 512 || preg_match('/[\x00-\x1f\x7f]/', $key) || preg_match('//u', $key) !== 1) {
                throw new InvalidArgumentException('Option keys must be bounded valid text.');
            }
            if (is_string($option)) {
                $option = ['label' => $option];
            }
            if (! is_array($option) || ! is_string($option['label'] ?? $key)
                || (isset($option['description']) && ! is_string($option['description']))
                || (isset($option['disabled']) && ! is_bool($option['disabled']))) {
                throw new InvalidArgumentException('Invalid dual listbox option definition.');
            }
            $label = $option['label'] ?? $key;
            $description = $option['description'] ?? null;
            if (strlen($label) > 4096 || ($description !== null && strlen($description) > 8192)
                || preg_match('//u', $label) !== 1 || ($description !== null && preg_match('//u', $description) !== 1)) {
                throw new InvalidArgumentException('Option labels and descriptions must be bounded UTF-8 text.');
            }
            $normalized[$key] = ['label' => $label, 'description' => $description, 'disabled' => ($option['disabled'] ?? false) || isset($disabled[$key])];
        }
        if (! $dynamic) {
            $this->normalizedOptionsCache = $normalized;
        }

        return $normalized;
    }

    /** @return list<string|int> */
    public function getDisabledOptions(): array
    {
        $keys = $this->evaluate($this->disabledOptions);
        if (! is_array($keys) || ! array_is_list($keys)) {
            throw new InvalidArgumentException('Disabled option keys must be a list.');
        }
        foreach ($keys as $key) {
            if (! is_string($key) && ! is_int($key)) {
                throw new InvalidArgumentException('Disabled keys must be strings or integers.');
            }
        }

        return $keys;
    }

    /** @return list<array{value: string, label: string, description?: ?string, disabled?: bool}> */
    public function getOptionsForJs(): array
    {
        $options = $this->getNormalizedOptions();

        return $this->formatOptionsForJs($options, lean: count($options) > self::LARGE_OPTIONS_THRESHOLD);
    }

    /** @return list<array{value: string, label: string, description?: ?string, disabled?: bool}> */
    public function getInitialOptionsForJs(): array
    {
        return $this->hasDeferredOptions() ? [] : $this->getOptionsForJs();
    }

    public function hasDeferredOptions(): bool
    {
        return count($this->getNormalizedOptions()) > self::LARGE_OPTIONS_THRESHOLD;
    }

    /** @param array<string|int, array{label: string, description: ?string, disabled: bool}> $options
     * @return list<array{value: string, label: string, description?: ?string, disabled?: bool}> */
    public function formatOptionsForJs(array $options, bool $lean = false): array
    {
        $result = [];
        foreach ($options as $value => $option) {
            $entry = ['value' => (string) $value, 'label' => $option['label']];
            if (! $lean || $option['description'] !== null) {
                $entry['description'] = $option['description'];
            }
            if (! $lean || $option['disabled']) {
                $entry['disabled'] = $option['disabled'];
            }
            $result[] = $entry;
        }

        return $result;
    }

    /** Presentation-only recovery; submitted values use the strict state cast.
     * @param array<int|string, mixed> $state
     * @return list<string> */
    public function normalizeState(array $state): array
    {
        $options = $this->getNormalizedOptions();
        $result = [];
        foreach ($state as $value) {
            if ((! is_string($value) && ! is_int($value)) || ! isset($options[(string) $value]) || isset($result[(string) $value])) {
                continue;
            }
            $result[(string) $value] = (string) $value;
        }

        return array_values($result);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-dual-listbox-field', 'fff-dual-listbox-field--'.$this->getSize(), 'fff-rounding-'.$this->getRounding(), 'fff-dual-listbox-field--'.$this->getVariant()];
    }

    /** @return list<string> */
    public function getIconIdentity(): array
    {
        return array_map(fn ($icon): string => $icon instanceof Htmlable ? $icon->toHtml() : ($icon instanceof BackedEnum ? (string) $icon->value : $icon), [
            $this->getSearchIcon(), $this->getMoveAllRightIcon(), $this->getMoveRightIcon(), $this->getSwapIcon(),
            $this->getMoveLeftIcon(), $this->getMoveAllLeftIcon(), $this->getMoveUpIcon(), $this->getMoveDownIcon(),
        ]);
    }

    /** @return array<string, mixed> */
    public function getRuntimeConfiguration(): array
    {
        $min = $this->getMinItems();
        $max = $this->getMaxItems();
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('minItems cannot exceed maxItems.');
        }

        return [
            'options' => $this->getOptionsForJs(), 'searchable' => $this->isSearchable(),
            'reorderable' => $this->isReorderable(), 'moveOnDoubleClick' => $this->isMoveOnDoubleClick(),
            'disabled' => $this->isDisabled(), 'readOnly' => $this->isReadOnly(),
            'minItems' => $min, 'maxItems' => $max, 'virtualThreshold' => $this->getVirtualScrollThreshold(),
            'moveUpLabel' => Translations::get('filament-flex-fields::default.dual_listbox.move_up'),
            'moveDownLabel' => Translations::get('filament-flex-fields::default.dual_listbox.move_down'),
            'labels' => array_combine(
                ['available', 'selected', 'empty_available', 'empty_selected', 'selection_count', 'moved_count', 'limit_reached', 'keyboard_hint', 'touch_hint'],
                array_map(fn (string $key): string => Translations::get('filament-flex-fields::default.dual_listbox.'.$key), ['available', 'selected', 'empty_available', 'empty_selected', 'selection_count', 'moved_count', 'limit_reached', 'keyboard_hint', 'touch_hint']),
            ),
        ];
    }
}
