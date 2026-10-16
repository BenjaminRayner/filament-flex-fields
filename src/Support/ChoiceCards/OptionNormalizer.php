<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceCards;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\OptionCatalog;
use InvalidArgumentException;

final class OptionNormalizer
{
    /**
     * @param  array<string|int,string|array<string,mixed>>  $options
     * @param  array<string|int>  $disabled
     * @return array<string|int,array{label:string,description:?string,price:?string,price_suffix:?string,meta:?string,icon:?string,badge:?string,badge_color:string,disabled:bool}>
     */
    public static function card(array $options, array $disabled): array
    {
        $result = [];
        foreach (OptionCatalog::normalize($options, disabled: $disabled) as $key => $base) {
            $raw = is_array($options[$key]) ? $options[$key] : [];
            $result[$key] = [...$base,
                'price' => self::text($raw['price'] ?? $raw['value'] ?? null),
                'price_suffix' => self::text($raw['price_suffix'] ?? $raw['suffix'] ?? null),
                'meta' => self::text($raw['meta'] ?? null),
                'badge' => self::text($raw['badge'] ?? null),
                'badge_color' => self::text($raw['badge_color'] ?? 'success') ?? 'success',
            ];
        }

        return $result;
    }

    /**
     * @param  array<string|int,string|array<string,mixed>>  $options
     * @param  array<string|int>  $disabled
     * @return array<string|int,array{label:string,image:?string,alt:string,disabled:bool}>
     */
    public static function image(array $options, array $disabled): array
    {
        $result = [];
        foreach (OptionCatalog::normalize($options, disabled: $disabled) as $key => $base) {
            $raw = is_array($options[$key]) ? $options[$key] : [];
            $result[$key] = ['label' => $base['label'], 'disabled' => $base['disabled'],
                'image' => self::text($raw['image'] ?? null),
                'alt' => self::text($raw['alt'] ?? null) ?? $base['label'],
            ];
        }

        return $result;
    }

    private static function text(mixed $value): ?string
    {
        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException('Choice card text must be scalar.');
        }

        return filled($value) ? (string) $value : null;
    }
}
