<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

final class TextInputCounter
{
    public static function count(string $value, string $mode): int
    {
        return match ($mode) {
            'bytes' => strlen($value),
            'graphemes' => preg_match_all('/\X/u', $value) ?: 0,
            'words' => preg_match_all('/\S+/u', $value) ?: 0,
            default => mb_strlen($value),
        };
    }

    public static function label(int $count, ?int $limit, string $display, ?string $template = null, ?string $locale = null): string
    {
        $formatter = $locale !== null ? new \NumberFormatter($locale, \NumberFormatter::DECIMAL) : null;
        $format = static fn (?int $number): string => $number === null ? '∞' : ($formatter !== null ? (string) $formatter->format($number) : (string) $number);
        $remaining = $limit === null ? null : $limit - $count;
        if ($template !== null) {
            return strtr($template, ['{count}' => $format($count), '{limit}' => $format($limit), '{remaining}' => $format($remaining)]);
        }
        if ($limit === null) {
            return $format($count);
        }

        return match ($display) {
            'remaining' => $format($remaining),
            'both' => $format($count).'/'.$format($limit).' · '.$format($remaining),
            default => $format($count).'/'.$format($limit),
        };
    }
}
