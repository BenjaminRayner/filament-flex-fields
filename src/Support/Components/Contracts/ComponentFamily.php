<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Contracts;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaContract;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** One logical component, with independently loadable optional surface kinds. */
final readonly class ComponentFamily
{
    /** @var array<string, string> */
    public array $surfaces;

    /** @param array<string, string> $surfaces Surface kind => registered component identity. */
    public function __construct(
        public ComponentPackage $package,
        public string $id,
        array $surfaces,
        public int $configurationVersion = 1,
        public int $valueVersion = 1,
        public ?FamilySchemaContract $schema = null,
    ) {
        $this->surfaces = ArraySnapshot::copy($surfaces);

        $package->assertOwns($id);
        if ($configurationVersion < 1 || $configurationVersion > 65535 || $valueVersion < 1 || $valueVersion > 65535
            || $surfaces === [] || array_is_list($surfaces) || count($surfaces) > 5) {
            throw new InvalidArgumentException('Family needs bounded independent configuration/value versions and named surfaces.');
        }
        foreach ($surfaces as $kind => $component) {
            if (! is_string($kind) || ! is_string($component) || ! in_array(ComponentKind::tryFrom($kind),
                [ComponentKind::Field, ComponentKind::Column, ComponentKind::Entry, ComponentKind::Display, ComponentKind::Action], true)) {
                throw new InvalidArgumentException('Family surface must be field, column, entry, display or action.');
            }
            $package->assertOwns($component);
        }
        if (count(array_unique($surfaces, SORT_STRING)) !== count($surfaces)) {
            throw new InvalidArgumentException('Different family surfaces require independent component identities.');
        }
    }

    public function sameContractAs(self $other): bool
    {
        return $this->id === $other->id && $this->package->sameContractAs($other->package)
            && $this->surfaces == $other->surfaces && $this->configurationVersion === $other->configurationVersion
            && $this->valueVersion === $other->valueVersion && $this->schema?->export() === $other->schema?->export();
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        $surfaces = $this->surfaces;
        ksort($surfaces, SORT_STRING);

        return ['id' => $this->id, 'package' => $this->package->assets->manifest->package,
            'configurationVersion' => $this->configurationVersion, 'valueVersion' => $this->valueVersion,
            'surfaces' => $surfaces, 'schema' => $this->schema?->export()];
    }
}
