<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Tags;

use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use InvalidArgumentException;

/** Storage normalization is independent of Blade, Livewire and menu data. */
final class TagState
{
    /** @return list<string> */
    public static function normalize(mixed $state, ?string $separator = null): array
    {
        if ($state === null || $state === '') {
            return [];
        }
        if (is_string($state)) {
            self::assertText($state, 1048576);
            if ($separator === null || $separator === '') {
                return [];
            }
            $state = explode($separator, $state);
        }
        if (! is_array($state) || count($state) > 10000) {
            throw new InvalidArgumentException('Tags require a bounded array or separated text.');
        }
        $tags = [];
        $bytes = 0;
        foreach ($state as $value) {
            $text = trim(OptionText::required($value, 'tag'));
            self::assertText($text, 65536);
            $bytes += strlen($text);
            if ($bytes > 1048576) {
                throw new InvalidArgumentException('Tags exceed their byte budget.');
            }
            if ($text !== '') {
                $tags[] = $text;
            }
        }

        return $tags;
    }

    public static function identity(string $tag, bool $insensitive): string
    {
        return $insensitive ? mb_strtolower($tag, 'UTF-8') : $tag;
    }

    private static function assertText(string $text, int $limit): void
    {
        if (strlen($text) > $limit || ! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('Tags require valid UTF-8 within the text budget.');
        }
    }
}
