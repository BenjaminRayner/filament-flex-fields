<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ManifestIdentity;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use InvalidArgumentException;

/** Saved data selects an installed family identity, never a PHP class or callback. */
final readonly class FamilySchemaDefinition
{
    /** @var array<string, mixed> */
    public array $config;

    public const DATA_KEYS = ['family', 'slug', 'label', 'config', 'configurationVersion', 'valueVersion',
        'isRequired', 'isActive', 'isVisible', 'isDisabled', 'isReadOnly'];

    /** @param array<string, mixed> $config */
    public function __construct(
        public string $family,
        public string $slug,
        public string $label,
        array $config = [],
        public int $configurationVersion = 1,
        public int $valueVersion = 1,
        public bool $isRequired = false,
        public bool $isActive = true,
        public bool $isVisible = true,
        public bool $isDisabled = false,
        public bool $isReadOnly = false,
    ) {
        ManifestIdentity::assert($family);
        if (preg_match('/^[a-z][a-z0-9_]{0,95}$/D', $slug) !== 1 || ! mb_check_encoding($label, 'UTF-8')
            || trim($label) === '' || strlen($label) > 1024 || $configurationVersion < 1 || $configurationVersion > 65535
            || $valueVersion < 1 || $valueVersion > 65535) {
            throw new InvalidArgumentException('Invalid family schema identity, label or version.');
        }
        if ($config !== [] && array_is_list($config)) {
            throw new InvalidArgumentException('Family schema configuration must be a map.');
        }
        $this->config = ConfigurationSchema::copyArray($config);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (array_diff(array_keys($data), self::DATA_KEYS) !== [] || ! is_string($data['family'] ?? null)
            || ! is_string($data['slug'] ?? null) || ! is_string($data['label'] ?? null)) {
            throw new InvalidArgumentException('Invalid family schema keys or identity types.');
        }
        foreach (['configurationVersion', 'valueVersion'] as $version) {
            if (array_key_exists($version, $data) && ! is_int($data[$version])) {
                throw new InvalidArgumentException('Family schema versions must be integers.');
            }
        }
        foreach (['isRequired', 'isActive', 'isVisible', 'isDisabled', 'isReadOnly'] as $flag) {
            if (array_key_exists($flag, $data) && ! is_bool($data[$flag])) {
                throw new InvalidArgumentException('Family schema flags must be booleans.');
            }
        }
        if (array_key_exists('config', $data) && ! is_array($data['config'])) {
            throw new InvalidArgumentException('Family schema configuration must be a map.');
        }

        return new self(...$data);
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return get_object_vars($this);
    }
}
