<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\SocialLinks;

use Bjanczak\FilamentFlexFields\Data\SocialPlatform;
use InvalidArgumentException;

final readonly class SocialPlatformDefinition
{
    /**
     * @param  list<string>  $hosts
     */
    public function __construct(
        public string $value,
        public string $label,
        public string $placeholder,
        public array $hosts = [],
        public ?string $iconSvg = null,
        public ?string $icon = null,
    ) {}

    public static function fromEnum(SocialPlatform $platform): self
    {
        return new self(
            value: $platform->value,
            label: $platform->label(),
            placeholder: $platform->placeholder(),
            hosts: $platform->hostPatterns(),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        if (! is_string($attributes['value'] ?? null) || ! preg_match('/^[a-z][a-z0-9_-]{0,127}$/D', $attributes['value'])) {
            throw new InvalidArgumentException('Custom platform value must be a lowercase slug.');
        }
        $value = $attributes['value'];

        foreach (['label', 'placeholder', 'icon', 'iconSvg'] as $key) {
            if (isset($attributes[$key]) && ! is_string($attributes[$key])) {
                throw new InvalidArgumentException("Custom platform [{$key}] must be text.");
            }
        }
        $label = trim((string) ($attributes['label'] ?? $value));
        $placeholder = trim((string) ($attributes['placeholder'] ?? 'https://'));

        $hosts = $attributes['hosts'] ?? [];

        if (! is_array($hosts) || ! array_is_list($hosts) || count($hosts) > 128) {
            throw new InvalidArgumentException('Platform hosts must be a list.');
        }

        $hosts = array_values(array_filter(array_map(
            function (mixed $host): string {
                if (! is_string($host) || ! preg_match('/^[a-z0-9.-]+$/iD', $host)) {
                    throw new InvalidArgumentException('Platform hosts must be domain names without schemes or paths.');
                }

                return strtolower(trim($host));
            },
            $hosts,
        ), fn (string $host): bool => $host !== ''));

        $iconSvg = isset($attributes['iconSvg']) && is_string($attributes['iconSvg'])
            ? $attributes['iconSvg']
            : null;

        return new self(
            value: $value,
            label: $label !== '' ? $label : $value,
            placeholder: $placeholder !== '' ? $placeholder : 'https://',
            hosts: $hosts,
            iconSvg: $iconSvg,
            icon: isset($attributes['icon']) && is_string($attributes['icon']) ? $attributes['icon'] : null,
        );
    }

    public function iconName(): string
    {
        return $this->icon ?? SocialPlatform::tryFrom($this->value)?->brandIcon()
            ?? ($this->value === 'mastodon' ? 'hugeicons-mastodon' : 'hugeicons-globe');
    }

    /**
     * @return array{value: string, label: string, placeholder: string, brand: string, hosts: list<string>}
     */
    public function toAlpineArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'brand' => $this->value,
            'hosts' => $this->hosts,
        ];
    }
}
