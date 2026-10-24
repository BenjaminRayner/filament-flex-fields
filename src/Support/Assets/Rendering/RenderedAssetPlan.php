<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use InvalidArgumentException;

/** Immutable browser projection. Contains public URLs, never instance/form state. */
final readonly class RenderedAssetPlan
{
    public string $json;

    public string $id;

    /** @var list<string> */
    public array $stylesheets;

    /** @var list<string> */
    public array $chunks;

    /** @var array<string, int> */
    public array $sizes;

    /** @var array<string, array{package: string, component: string, export: string, abi: 1}> */
    public array $factoryOwners;

    /** Validate a self-contained nested fragment before merging its dictionary. */
    public static function fromJson(string $json): self
    {
        if (strlen($json) > 131072) {
            throw new InvalidArgumentException('Asset plan metadata exceeds its 128 KiB budget.');
        }
        $value = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        $keys = ['format', 'component', 'stylesheets', 'chunks', 'sizes', 'factoryOwners'];
        if (! $value instanceof \stdClass || count(get_object_vars($value)) !== count($keys)
            || array_diff($keys, array_keys(get_object_vars($value))) !== []
            || $value->format !== 1 || ! is_string($value->component)
            || ! is_array($value->stylesheets) || ! is_array($value->chunks)
            || ! $value->sizes instanceof \stdClass || ! $value->factoryOwners instanceof \stdClass) {
            throw new InvalidArgumentException('Invalid serialized asset plan.');
        }
        $owners = [];
        foreach (get_object_vars($value->factoryOwners) as $url => $owner) {
            if (! $owner instanceof \stdClass) {
                throw new InvalidArgumentException('Invalid serialized asset plan factory owner.');
            }
            $owners[$url] = self::owner(get_object_vars($owner));
        }

        return new self($value->component, self::urls($value->stylesheets), self::urls($value->chunks), get_object_vars($value->sizes), $owners);
    }

    /**
     * @param  list<string>  $stylesheets
     * @param  list<string>  $chunks
     * @param  array<string, int>  $sizes
     * @param  array<string, array{package: string, component: string, export: string, abi: int}>  $factoryOwners
     */
    public function __construct(string $component, array $stylesheets, array $chunks, array $sizes = [], array $factoryOwners = [])
    {
        self::component($component);
        $stylesheets = self::urls($stylesheets);
        $chunks = self::urls($chunks);
        $resources = array_fill_keys([...$stylesheets, ...$chunks], true);
        foreach ($sizes as $url => $bytes) {
            if (! isset($resources[$url]) || ! is_int($bytes) || $bytes < 0 || $bytes > 9007199254740991) {
                throw new InvalidArgumentException('Asset plan sizes must describe declared resources with non-negative safe integers.');
            }
        }
        if (count($factoryOwners) > 128) {
            throw new InvalidArgumentException('An asset plan supports at most 128 factory owners.');
        }
        foreach ($factoryOwners as $url => $owner) {
            if (! in_array($url, $chunks, true)) {
                throw new InvalidArgumentException('Asset plan factory owners must describe declared modules.');
            }
            $factoryOwners[$url] = self::owner($owner);
        }
        ksort($sizes, SORT_STRING);
        ksort($factoryOwners, SORT_STRING);
        $this->stylesheets = $stylesheets;
        $this->chunks = $chunks;
        $this->sizes = $sizes;
        $this->factoryOwners = $factoryOwners;
        $this->json = json_encode([
            'format' => 1, 'component' => $component,
            'stylesheets' => $stylesheets, 'chunks' => $chunks,
            'sizes' => (object) $sizes, 'factoryOwners' => (object) $factoryOwners,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($this->json) > 131072) {
            throw new InvalidArgumentException('Asset plan metadata exceeds its 128 KiB budget.');
        }
        $this->id = hash('sha256', $this->json);
    }

    private static function component(string $component): void
    {
        if (strlen($component) > 96 || ! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $component)) {
            throw new InvalidArgumentException('Invalid asset plan component identity.');
        }
    }

    /** @return array{package: string, component: string, export: string, abi: 1} */
    private static function owner(mixed $owner): array
    {
        if (! is_array($owner) || count($owner) !== 4
            || ! is_string($owner['package'] ?? null) || ! is_string($owner['component'] ?? null)
            || ! is_string($owner['export'] ?? null) || ($owner['abi'] ?? null) !== 1
            || strlen($owner['package']) > 192 || ! preg_match('~^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?$~D', $owner['package'])
            || strlen($owner['export']) > 128 || ! preg_match('/^(?:default|[A-Za-z_$][\w$]*)$/D', $owner['export'])
            || in_array($owner['export'], ['__proto__', 'constructor', 'prototype'], true)) {
            throw new InvalidArgumentException('Invalid asset plan factory owner or unsupported JavaScript ABI.');
        }
        self::component($owner['component']);

        return ['package' => $owner['package'], 'component' => $owner['component'], 'export' => $owner['export'], 'abi' => 1];
    }

    /** @param array<array-key, mixed> $urls
     * @return list<string>
     */
    private static function urls(array $urls): array
    {
        if (! array_is_list($urls) || count($urls) > 512) {
            throw new InvalidArgumentException('Asset plan resources require a list of at most 512 URLs.');
        }
        foreach ($urls as $url) {
            if (! is_string($url) || strlen($url) > 4096 || preg_match('/[\s\x00-\x1f\x7f]/', $url)
                || (! str_starts_with($url, '/') && ! preg_match('~^https?://[^/]+(?:/|$)~D', $url))
                || str_starts_with($url, '//')) {
                throw new InvalidArgumentException('Asset plan resources require HTTP(S) URLs or same-origin absolute paths.');
            }
        }

        return array_values(array_unique($urls));
    }
}
