<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use InvalidArgumentException;

/** Public presentation options only; values and state paths remain per owner. */
final readonly class RenderedControlConfiguration
{
    public string $id;

    public string $json;

    /** @param array<string, mixed> $options */
    public function __construct(public string $component, public array $options)
    {
        if (! in_array($component, ['flex-textarea', 'select-catalog', 'phone-catalog', 'select-configuration'], true) || array_intersect(['statePath', 'initialState', 'configurationId'], array_keys($options)) !== []) {
            throw new InvalidArgumentException('Expected owner-independent control presentation options.');
        }
        $this->json = json_encode(['format' => 1, 'kind' => 'control-configuration', 'component' => $component, 'options' => $options],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        if (strlen($this->json) > self::maximumBytes($component)) {
            throw new InvalidArgumentException('Control configuration exceeds its metadata budget.');
        }
        $this->id = hash('sha256', $this->json);
    }

    public static function maximumBytes(string $component): int
    {
        return in_array($component, ['select-catalog', 'phone-catalog', 'select-configuration'], true) ? 4 * 1024 * 1024 : 65536;
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 4 * 1024 * 1024) {
            throw new InvalidArgumentException('Control configuration exceeds its metadata budget.');
        }
        $value = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_keys($value) !== ['format', 'kind', 'component', 'options']
            || $value['format'] !== 1 || $value['kind'] !== 'control-configuration'
            || ! is_string($value['component']) || ! is_array($value['options'])) {
            throw new InvalidArgumentException('Invalid control configuration definition.');
        }

        return new self($value['component'], $value['options']);
    }
}
