<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Values;

use Carbon\CarbonInterface;
use DateTimeImmutable;

final readonly class ClockCodec
{
    public function __construct(public string $storageFormat, public string $displayFormat) {}

    public function canonical(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s');
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        foreach (array_unique([$this->storageFormat, $this->displayFormat, 'H:i:s', 'H:i', 'g:i A', 'h:i A', 'g:i:s A', 'h:i:s A']) as $format) {
            $clock = $this->parseFormat(trim($value), $format);
            if ($clock !== null) {
                return $clock;
            }
        }

        return null;
    }

    private function parseFormat(string $value, string $format): ?string
    {
        $tokens = ['H' => '(\\d{1,2})', 'G' => '(\\d{1,2})', 'h' => '(\\d{1,2})', 'g' => '(\\d{1,2})', 'i' => '(\\d{1,2})', 's' => '(\\d{1,2})', 'A' => '(AM|PM)', 'a' => '(am|pm)'];
        $pattern = '';
        $parts = [];
        $escaped = false;
        foreach (str_split($format) as $token) {
            if ($escaped) {
                $pattern .= preg_quote($token, '~');
                $escaped = false;
            } elseif ($token === '\\') {
                $escaped = true;
            } elseif (isset($tokens[$token])) {
                $pattern .= $tokens[$token];
                $parts[] = $token;
            } else {
                $pattern .= preg_quote($token, '~');
            }
        }
        if (! preg_match('~^'.$pattern.'$~D', $value, $matches)) {
            return null;
        }
        $fields = [];
        foreach ($parts as $index => $token) {
            $fields[$token] = $matches[$index + 1];
        }
        $rawHour = $fields['H'] ?? $fields['G'] ?? $fields['h'] ?? $fields['g'] ?? null;
        if ($rawHour === null) {
            return null;
        }
        $hour = (int) $rawHour;
        $minute = (int) ($fields['i'] ?? 0);
        $second = (int) ($fields['s'] ?? 0);
        if (isset($fields['h']) || isset($fields['g'])) {
            $period = $fields['A'] ?? $fields['a'] ?? null;
            if ($hour < 1 || $hour > 12 || $period === null) {
                return null;
            }
            $hour = $hour % 12 + (strtolower($period) === 'pm' ? 12 : 0);
        }

        return $hour >= 0 && $hour <= 23 && $minute <= 59 && $second <= 59 ? sprintf('%02d:%02d:%02d', $hour, $minute, $second) : null;
    }

    public function format(mixed $value, string $format): string
    {
        $canonical = $this->canonical($value);
        if ($canonical === null) {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!H:i:s', $canonical, new \DateTimeZone('UTC'));

        return $date === false ? '' : $date->format($format);
    }

    public function normalize(mixed $value): ?string
    {
        return $this->canonical($value) === null ? null : $this->format($value, $this->storageFormat);
    }
}
