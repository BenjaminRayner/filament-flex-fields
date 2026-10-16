<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CreditCard;

use Bjanczak\FilamentFlexFields\Support\Translations;

final class CardValues
{
    /** @return array{number:string,name:string,expiry:string,cvv:string} */
    public static function preview(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $text = static fn (string $key): string => is_string($value[$key] ?? null) ? $value[$key] : '';
        $expiry = substr(preg_replace('/[^0-9]/', '', $text('expiry')) ?? '', 0, 4);

        return ['number' => substr(preg_replace('/[^0-9]/', '', $text('number')) ?? '', 0, 19),
            'name' => trim(mb_substr($text('name'), 0, 200)),
            'expiry' => strlen($expiry) > 2 ? substr($expiry, 0, 2).'/'.substr($expiry, 2) : $expiry,
            'cvv' => substr(preg_replace('/[^0-9]/', '', $text('cvv')) ?? '', 0, 4)];
    }

    public static function acceptsShape(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_array($value) || array_diff(array_keys($value), ['number', 'name', 'expiry', 'cvv']) !== []) {
            return false;
        }
        foreach ($value as $key => $text) {
            if (! is_string($text) || preg_match('/[\x00-\x1f\x7f]/', $text)) {
                return false;
            }
            if (strlen($text) > match ($key) {
                'number' => 32, 'name' => 800, 'expiry' => 5, 'cvv' => 4, default => 0
            }) {
                return false;
            }
        }

        return true;
    }

    public static function acceptsFormatting(mixed $value): bool
    {
        if (! self::acceptsShape($value)) {
            return false;
        }
        if ($value === null) {
            return true;
        }

        return preg_match('/^[0-9 -]*$/D', $value['number'] ?? '') === 1
            && preg_match('/^[0-9]*$/D', $value['cvv'] ?? '') === 1
            && preg_match('/^(?:[0-9]{0,4}|[0-9]{2}\/[0-9]{0,2})$/D', $value['expiry'] ?? '') === 1
            && mb_strlen($value['name'] ?? '') <= 200
            && strlen(str_replace([' ', '-'], '', $value['number'] ?? '')) <= 19;
    }

    public static function passesLuhn(string $number): bool
    {
        if (! preg_match('/^[0-9 -]+$/D', $number)) {
            return false;
        }
        $digits = str_replace([' ', '-'], '', $number);
        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return false;
        }
        $sum = 0;
        for ($i = strlen($digits) - 1, $double = false; $i >= 0; $i--, $double = ! $double) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    public static function expiryError(string $expiry): ?string
    {
        if ($expiry === '') {
            return null;
        }
        if (! preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/D', $expiry, $matches)) {
            return Translations::get('filament-flex-fields::default.validation.credit_card.invalid_expiry');
        }

        return (2000 + (int) $matches[2]) * 100 + (int) $matches[1] < (int) now()->format('Ym')
            ? Translations::get('filament-flex-fields::default.validation.credit_card.expired') : null;
    }
}
