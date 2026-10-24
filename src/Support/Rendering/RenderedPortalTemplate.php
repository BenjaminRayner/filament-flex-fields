<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use InvalidArgumentException;

/** Trusted compiled portal presentation. Never use for user HTML or instance data. */
final readonly class RenderedPortalTemplate
{
    public string $id;

    public string $json;

    public function __construct(public string $html)
    {
        if (strlen($html) > 131072
            || ! preg_match('~\A\s*<template x-teleport="body">\s*<div\b~s', $html)
            || ! str_ends_with(trim($html), '</template>')) {
            throw new InvalidArgumentException('Expected bounded compiled portal markup.');
        }
        $this->json = json_encode(['format' => 1, 'kind' => 'alpine-portal', 'html' => $html],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $this->id = hash('sha256', $this->json);
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 524288) {
            throw new InvalidArgumentException('Portal template exceeds its metadata budget.');
        }
        $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($value) || array_keys($value) !== ['format', 'kind', 'html']
            || $value['format'] !== 1 || $value['kind'] !== 'alpine-portal' || ! is_string($value['html'])) {
            throw new InvalidArgumentException('Invalid portal template definition.');
        }

        return new self($value['html']);
    }
}
