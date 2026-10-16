<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

/** Shared cache records are validated before entering typed catalog readers. */
final class IconCatalogPayload
{
    public static function catalog(mixed $data): bool
    {
        if (! is_array($data) || count($data) > 128) {
            return false;
        }
        foreach ($data as $name => $set) {
            if (! is_string($name) || ! is_array($set) || ! self::text($set['prefix'] ?? null)
                || ! self::text($set['label'] ?? null) || ! is_array($set['icons'] ?? null)
                || ! array_is_list($set['icons']) || count($set['icons']) > 65536) {
                return false;
            }
            foreach ($set['icons'] as $icon) {
                if (! self::text($icon)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function index(mixed $data): bool
    {
        if (! is_array($data) || count($data) !== 4 || ! self::entries($data['entries'] ?? null)
            || ! is_array($data['entriesBySet'] ?? null) || count($data['entriesBySet']) > 128
            || ! is_array($data['allowedLookup'] ?? null) || count($data['allowedLookup']) > 65536
            || ! is_array($data['setSummaries'] ?? null) || ! array_is_list($data['setSummaries'])
            || count($data['setSummaries']) > 128) {
            return false;
        }
        foreach ($data['entriesBySet'] as $set => $entries) {
            if (! is_string($set) || ! self::entries($entries)) {
                return false;
            }
        }
        foreach ($data['allowedLookup'] as $icon => $allowed) {
            if (! self::text($icon) || $allowed !== true) {
                return false;
            }
        }
        foreach ($data['setSummaries'] as $summary) {
            if (! is_array($summary) || ! self::text($summary['key'] ?? null)
                || ! self::text($summary['prefix'] ?? null) || ! self::text($summary['label'] ?? null)
                || ! is_int($summary['count'] ?? null) || $summary['count'] < 0 || $summary['count'] > 65536) {
                return false;
            }
        }

        return true;
    }

    private static function entries(mixed $entries): bool
    {
        if (! is_array($entries) || ! array_is_list($entries) || count($entries) > 65536) {
            return false;
        }
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                return false;
            }
            foreach (['name', 'label', 'set', 'nameLower', 'labelLower'] as $member) {
                if (! self::text($entry[$member] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function text(mixed $value): bool
    {
        return is_string($value) && $value !== '' && strlen($value) <= 512 && ! str_contains($value, "\0");
    }
}
