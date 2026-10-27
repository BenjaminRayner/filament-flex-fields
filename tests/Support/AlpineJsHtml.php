<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedPortalTemplate;
use DOMDocument;
use DOMElement;
use DOMXPath;
use LogicException;

/**
 * Filament / Illuminate Js::from() (Laravel 13.31+ / Filament 5.8+) embeds Alpine
 * config as JSON.parse('{\u0022key\u0022:…}'). Older stacks used bare `key: value`.
 * Current Select controls keep typed configuration in an owner-local inert template.
 * Inspect its JSON without executing Alpine, preserving legacy x-data coverage.
 */
final class AlpineJsHtml
{
    public static function containsFlag(string $html, string $key, bool $value): bool
    {
        $jsonValue = $value ? 'true' : 'false';
        $legacy = "{$key}: {$jsonValue}";
        $escaped = '\\u0022'.$key.'\\u0022:'.$jsonValue;

        return self::containsConfiguredValue($html, $key, $value)
            || str_contains(self::legacyConfiguration($html), $legacy)
            || str_contains(self::legacyConfiguration($html), $escaped);
    }

    public static function containsString(string $html, string $key, string $value): bool
    {
        if (self::containsConfiguredValue($html, $key, $value)) {
            return true;
        }
        $html = self::legacyConfiguration($html);
        $legacyPatterns = [
            "/{$key}[\"']?\\s*:\\s*[\"']".preg_quote($value, '/')."[\"']/",
            '/\\\\u0022'.preg_quote($key, '/').'\\\\u0022:\\\\u0022'.preg_quote($value, '/').'\\\\u0022/',
        ];

        foreach ($legacyPatterns as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                return true;
            }
        }

        $escaped = '\\u0022'.$key.'\\u0022:\\u0022'.$value.'\\u0022';

        return str_contains($html, $escaped);
    }

    public static function containsEmptyArray(string $html, string $key): bool
    {
        $legacy = "{$key}: []";
        $escaped = '\\u0022'.$key.'\\u0022:[]';

        return self::containsConfiguredValue($html, $key, [])
            || str_contains(self::legacyConfiguration($html), $legacy)
            || str_contains(self::legacyConfiguration($html), $escaped);
    }

    /** @return array<string, mixed> */
    public static function configurationFor(string $html, string $statePath): array
    {
        $matches = array_values(array_filter(self::selectConfigurations($html),
            static fn (array $configuration): bool => ($configuration['statePath'] ?? null) === $statePath));
        if (count($matches) !== 1) {
            throw new LogicException('Expected exactly one Select configuration for '.$statePath);
        }

        return $matches[0];
    }

    /** Include verified compiled portal definitions in markup assertions. */
    public static function portalMarkup(string $html): string
    {
        $document = self::document($html);
        foreach ((new DOMXPath($document))->query('//template[@data-fff-portal-template-definition]') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $portal = RenderedPortalTemplate::fromJson($node->textContent);
            if ($portal->id !== $node->getAttribute('data-fff-portal-template-definition')) {
                throw new LogicException('Portal definition identity does not match its markup.');
            }
            $html .= "\n".$portal->html;
        }

        return $html;
    }

    private static function containsConfiguredValue(string $html, string $key, mixed $value): bool
    {
        foreach (self::selectConfigurations($html) as $configuration) {
            if (array_key_exists($key, $configuration) && $configuration[$key] === $value) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private static function selectConfigurations(string $html): array
    {
        $configurations = [];
        $document = self::document($html);
        foreach ((new DOMXPath($document))->query('//template[@data-fff-select-configuration]') as $node) {
            $configuration = json_decode($node->textContent, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($configuration) || array_is_list($configuration)) {
                throw new LogicException('Expected a Select configuration object.');
            }
            $configurations[] = $configuration;
        }

        return $configurations;
    }

    private static function legacyConfiguration(string $html): string
    {
        $values = [];
        foreach ((new DOMXPath(self::document($html)))->query('//@x-data') as $attribute) {
            $values[] = $attribute->nodeValue;
        }

        return implode("\n", $values);
    }

    private static function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $document;
    }
}
