<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Configuration;

use Bjanczak\FilamentFlexFields\Support\DateTime\DateTimeAlpineConfiguration;
use Bjanczak\FilamentFlexFields\Support\DateTime\Values\CivilDateCodec;

/** Display format owns segment order, literals and placeholders on both renders. */
final class CivilDateSegments
{
    /** @return list<array{type: string, token: string, placeholder: string, min: int, max: int, digits: int, label: string}> */
    public static function pattern(string $format): array
    {
        $result = [];
        $escaped = false;
        foreach (str_split($format) as $token) {
            if (! $escaped && $token === '\\') {
                $escaped = true;

                continue;
            }
            $type = $escaped ? 'literal' : match ($token) {
                'Y', 'y' => 'year', 'm', 'n', 'M', 'F' => 'month', 'd', 'j' => 'day',
                'H', 'G', 'h', 'g' => 'hour', 'i' => 'minute', 's' => 'second', 'A', 'a' => 'period',
                default => 'literal',
            };
            $escaped = false;
            $placeholder = match ($type) {
                'year' => $token === 'y' ? 'yy' : 'yyyy',
                'month' => in_array($token, ['M', 'F'], true) ? 'mmm' : 'mm',
                'day' => 'dd', 'hour' => 'hh', 'minute' => 'mm', 'second' => 'ss', 'period' => 'AM',
                default => $token,
            };
            $min = in_array($type, ['month', 'day'], true) || in_array($token, ['Y', 'h', 'g'], true) ? 1 : 0;
            $max = match ($type) {
                'year' => $token === 'y' ? 99 : 9999, 'month' => 12, 'day' => 31,
                'hour' => in_array($token, ['h', 'g'], true) ? 12 : 23,
                'minute', 'second' => 59, 'period' => 1, default => 0,
            };
            $label = match ($type) {
                'day', 'month', 'year' => __("filament-flex-fields::date-time.{$type}"),
                'hour', 'minute', 'second' => __("filament-flex-fields::default.date_time.{$type}"),
                'period' => DateTimeAlpineConfiguration::labels()['am'].'/'.DateTimeAlpineConfiguration::labels()['pm'],
                default => '',
            };
            if (! is_string($label)) {
                throw new \LogicException('Date segment labels must be strings.');
            }
            $result[] = ['type' => $type, 'token' => $token, 'placeholder' => $placeholder,
                'min' => $min, 'max' => $max, 'digits' => $token === 'Y' ? 4 : 2, 'label' => $label];
        }

        return $result;
    }

    /** @param list<array{type: string, token: string, placeholder: string, min: int, max: int, digits: int, label: string}> $pattern
     * @return list<array{value: ?int, text: string}>
     */
    public static function initial(array $pattern, CivilDateCodec $codec, mixed $value): array
    {
        $date = $codec->parse($value);
        $date?->locale($codec->locale);
        $result = [];
        foreach ($pattern as $segment) {
            $number = $date === null ? null : match ($segment['type']) {
                'year' => $segment['token'] === 'y' ? $date->year % 100 : $date->year,
                'month' => $date->month, 'day' => $date->day,
                'hour' => in_array($segment['token'], ['h', 'g'], true) ? ($date->hour % 12 ?: 12) : $date->hour,
                'minute' => $date->minute, 'second' => $date->second,
                'period' => $date->hour >= 12 ? 1 : 0, default => null,
            };
            $result[] = ['value' => $number, 'text' => $segment['type'] === 'literal' ? $segment['token'] : ($date?->translatedFormat($segment['token']) ?? $segment['placeholder'])];
        }

        return $result;
    }
}
