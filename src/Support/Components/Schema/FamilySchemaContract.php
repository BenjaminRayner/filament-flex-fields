<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use InvalidArgumentException;
use ReflectionClass;

/** Frozen schema metadata, with fresh request-local adapter construction. */
final readonly class FamilySchemaContract
{
    /** @var array<string, mixed> */
    public array $packageDefaults;

    /**
     * @param  class-string<FamilySchemaAdapter>  $adapter
     * @param  array<string, mixed>  $packageDefaults
     */
    public function __construct(public string $adapter, public ConfigurationSchema $configuration, array $packageDefaults = [])
    {
        if (strlen($adapter) > 512 || ! is_a($adapter, FamilySchemaAdapter::class, true) || ! (new ReflectionClass($adapter))->isInstantiable()) {
            throw new InvalidArgumentException('Family schema requires an instantiable typed adapter.');
        }
        $configuration->validateLayer($packageDefaults);
        $this->packageDefaults = ConfigurationSchema::copyArray($packageDefaults);
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return ['adapter' => $this->adapter, 'configuration' => $this->configuration->export(), 'packageDefaults' => ConfigurationSchema::canonicalData($this->packageDefaults)];
    }
}
