<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\BubbleChoice;

use InvalidArgumentException;

final class OptionCatalog
{
    public const int LIMIT = 4096;

    public static function validKey(string $key): bool
    {
        return $key !== '' && strlen($key) <= 512 && preg_match('//u', $key) === 1
            && ! preg_match('/[\x00-\x1f\x7f]/', $key);
    }

    /** @param array<string|int> $disabled
     * @return array<string|int, array{label: string, description: ?string, image: ?string, imageMode: string, color: ?string, selectedColor: ?string, disabled: bool}> */
    public static function resolve(mixed $options, array $disabled): array
    {
        if (! is_array($options) || count($options) > self::LIMIT) {
            throw new InvalidArgumentException('Bubble options must be an array with at most '.self::LIMIT.' entries.');
        }
        $disabledSet = array_fill_keys(array_map(strval(...), $disabled), true);
        $result = [];
        foreach ($options as $key => $option) {
            $key = (string) $key;
            if (! self::validKey($key) || (! is_string($option) && ! is_array($option))) {
                throw new InvalidArgumentException('Bubble options require valid keys and string labels or option arrays.');
            }
            $option = is_string($option) ? ['label' => $option] : $option;
            $mode = $option['imageMode'] ?? $option['image_mode'] ?? 'background';
            if (! in_array($mode, ['background', 'icon'], true)) {
                throw new InvalidArgumentException('Bubble imageMode must be background or icon.');
            }
            $result[$key] = [
                'label' => self::text($option['label'] ?? $key, false) ?? $key,
                'description' => self::text($option['description'] ?? $option['desc'] ?? null),
                'image' => self::text($option['image'] ?? null),
                'imageMode' => $mode,
                'color' => self::text($option['color'] ?? null),
                'selectedColor' => self::text($option['selectedColor'] ?? $option['selected_color'] ?? null),
                'disabled' => (bool) ($option['disabled'] ?? false) || isset($disabledSet[$key]),
            ];
        }

        return $result;
    }

    private static function text(mixed $value, bool $nullable = true): ?string
    {
        if ($value === null || ($nullable && $value === '')) {
            return null;
        }
        if (! is_string($value) || strlen($value) > 8192 || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Bubble option text must be a valid UTF-8 string of at most 8192 bytes.');
        }

        return $value;
    }
}
