<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;

/** Immutable semantic index, separate from artifact and value serialization formats. */
final readonly class CompiledComponentFamilies
{
    /** @var array<string, ComponentPackage> */
    public array $packages;

    /** @var array<string, ComponentFamily> */
    public array $families;

    /** @var array<string, ComponentSurface> */
    public array $surfaces;

    /**
     * @param  array<string, ComponentPackage>  $packages
     * @param  array<string, ComponentFamily>  $families
     * @param  array<string, ComponentSurface>  $surfaces
     */
    public function __construct(array $packages = [], array $families = [], array $surfaces = [])
    {
        $this->packages = ArraySnapshot::copy($packages);
        $this->families = ArraySnapshot::copy($families);
        $this->surfaces = ArraySnapshot::copy($surfaces);
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        $result = [];
        foreach (['packages', 'families', 'surfaces'] as $type) {
            $records = [];
            foreach ($this->{$type} as $id => $contract) {
                $records[$id] = $contract->export();
            }
            ksort($records, SORT_STRING);
            $result[$type] = $records;
        }

        return $result;
    }
}
