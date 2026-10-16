<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists;

use InvalidArgumentException;

final class OptionCatalog
{
    /**
     * @param  array<string|int, string|array<string, mixed>>  $options
     * @param  array<string|int, string>  $icons
     * @param  array<string|int, string>  $descriptions
     * @param  array<string|int>  $disabled
     * @return array<string|int, array{label:string,description:?string,icon:?string,disabled:bool}>
     */
    public static function normalize(array $options, array $icons = [], array $descriptions = [], array $disabled = []): array
    {
        $locked = array_fill_keys(array_map(strval(...), $disabled), true);
        $result = [];
        foreach ($options as $value => $option) {
            $key = (string) $value;
            if (is_string($option)) {
                $option = ['label' => $option];
            }
            if (! is_array($option)) {
                throw new InvalidArgumentException("Choice option [{$key}] must be text or an option array.");
            }
            $label = $option['label'] ?? $key;
            $description = $option['description'] ?? $option['desc'] ?? $descriptions[$key] ?? null;
            $icon = $option['icon'] ?? $icons[$key] ?? null;
            foreach ([$label, $description, $icon] as $text) {
                if ($text !== null && ! is_scalar($text)) {
                    throw new InvalidArgumentException("Choice option [{$key}] must contain scalar text.");
                }
            }
            $result[$key] = [
                'label' => (string) $label,
                'description' => filled($description) ? (string) $description : null,
                'icon' => filled($icon) ? (string) $icon : null,
                'disabled' => (bool) ($option['disabled'] ?? false) || isset($locked[$key]),
            ];
        }

        return $result;
    }
}
