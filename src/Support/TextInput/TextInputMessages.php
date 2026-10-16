<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

use UnexpectedValueException;

final class TextInputMessages
{
    /** @param array<string, scalar> $replace */
    public static function get(string $key, array $replace = []): string
    {
        $text = __('filament-flex-fields::text-input.'.$key, $replace);
        if (! is_string($text)) {
            throw new UnexpectedValueException('TextInput message must resolve to a string: '.$key);
        }

        return $text;
    }
}
