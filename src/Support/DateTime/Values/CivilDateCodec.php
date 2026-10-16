<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\DateTime\Values;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;

final readonly class CivilDateCodec
{
    public function __construct(public string $storageFormat, public string $displayFormat, public string $locale = 'en', public string $precision = 'day') {}

    public function parse(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return $value->year >= 1 && $value->year <= 9999 ? $this->atPrecision(CarbonImmutable::instance($value)) : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        foreach (array_unique([$this->storageFormat, 'Y-m-d', 'Y-m-d\\TH:i:s', 'Y-m-d H:i:s', $this->displayFormat, 'm/d/Y']) as $format) {
            try {
                $input = trim($value);
                if (str_contains($format, 'M') || str_contains($format, 'F')) {
                    $months = [];
                    for ($month = 1; $month <= 12; $month++) {
                        $sample = new CarbonImmutable(sprintf('2000-%02d-01', $month), 'UTC');
                        $sample->locale($this->locale);
                        foreach (['M', 'F'] as $token) {
                            $months[$sample->translatedFormat($token)] = $sample->format($token);
                        }
                    }
                    $input = strtr($input, $months);
                }
                $date = DateTimeImmutable::createFromFormat('!'.$format, $input, new \DateTimeZone('UTC'));
                $errors = DateTimeImmutable::getLastErrors();

                if ($date === false || (int) $date->format('Y') < 1 || (int) $date->format('Y') > 9999 || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                    continue;
                }

                return $this->atPrecision(CarbonImmutable::instance($date));
            } catch (\ValueError) {
                continue;
            }
        }

        return null;
    }

    private function atPrecision(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this->precision) {
            'month' => $date->startOfMonth(),
            'year' => $date->startOfYear(),
            'localDateTime' => $date,
            default => $date->startOfDay(),
        };
    }

    public function normalize(mixed $value): ?string
    {
        return $this->parse($value)?->format($this->storageFormat);
    }

    public function canonical(mixed $value): ?string
    {
        return $this->parse($value)?->format($this->precision === 'localDateTime' ? 'Y-m-d\\TH:i:s' : 'Y-m-d');
    }

    public function display(mixed $value): string
    {
        $date = $this->parse($value);
        if ($date === null) {
            return '';
        }
        $date->locale($this->locale);

        return $date->translatedFormat($this->displayFormat);
    }
}
