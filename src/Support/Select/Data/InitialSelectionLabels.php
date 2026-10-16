<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Closure;

/** Rich bootstrap/chip labels retain selection order without N×selection traversal. */
final class InitialSelectionLabels
{
    /**
     * @param  list<mixed>  $values
     * @param  Closure(string|int, array<string, mixed>, bool): string  $renderRich
     * @return list<array{value: string, label: string, triggerLabel: string}>
     */
    public function project(array $values, ?OptionLabelIndex $index, Closure $renderRich): array
    {
        $entries = [];
        foreach ($values as $candidate) {
            $value = OptionValue::normalize($candidate);
            if ($value === null) {
                continue;
            }
            $label = $index?->find($value);
            $text = is_string($label) ? $label : (string) $value;
            $entries[] = [
                'value' => (string) $value,
                'label' => is_array($label) ? $renderRich($value, $label, false) : $text,
                'triggerLabel' => is_array($label) ? $renderRich($value, $label, true) : $text,
            ];
        }

        return $entries;
    }
}
