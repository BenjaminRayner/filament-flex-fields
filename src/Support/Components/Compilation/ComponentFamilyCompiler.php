<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use InvalidArgumentException;

/** Validate all family surfaces at the same commit barrier as their dependency graph. */
final class ComponentFamilyCompiler
{
    /**
     * @param  array<string, ComponentDefinition>  $definitions
     * @param  array<string, AssetPackage>  $bindings
     */
    public function compile(array $definitions, array $bindings): CompiledComponentFamilies
    {
        $packages = [];
        $families = [];
        $surfaces = [];
        $namespaces = [];
        $schemaBytes = 0;
        foreach ($definitions as $id => $definition) {
            $surface = $definition->surface;
            if ($surface === null) {
                continue;
            }
            $family = $surface->family;
            $package = $family->package;
            $name = $package->assets->manifest->package;
            if (! isset($bindings[$id]) || ! $package->assets->sameInstallationAs($bindings[$id])) {
                throw new InvalidArgumentException('Family surface requires its declared asset installation: '.$family->id.' -> '.$id);
            }
            if (isset($packages[$name]) && ! $packages[$name]->sameContractAs($package)) {
                throw new InvalidArgumentException('Conflicting semantic package contract: '.$name);
            }
            if (! isset($packages[$name])) {
                foreach ($namespaces as $namespace => $owner) {
                    if ($namespace === $package->namespace || str_starts_with($namespace, $package->namespace.'-')
                        || str_starts_with($package->namespace, $namespace.'-')) {
                        throw new InvalidArgumentException('Overlapping component namespaces: '.$owner.' and '.$name);
                    }
                }
                $namespaces[$package->namespace] = $name;
                $packages[$name] = $package;
            }
            if (isset($families[$family->id]) && ! $families[$family->id]->sameContractAs($family)) {
                throw new InvalidArgumentException('Conflicting family contract: '.$family->id);
            }
            if (! isset($families[$family->id])) {
                $schemaBytes += strlen(json_encode($family->schema?->export(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
                if ($schemaBytes > 4194304) {
                    throw new InvalidArgumentException('Compiled family schemas exceed their metadata budget.');
                }
            }
            $families[$family->id] = $family;
            $surfaces[$id] = $surface;
        }
        foreach ($families as $family) {
            foreach ($family->surfaces as $kind => $id) {
                $surface = $surfaces[$id] ?? null;
                if ($surface === null || $surface->kind->value !== $kind || ! $surface->family->sameContractAs($family)) {
                    throw new InvalidArgumentException('Missing or mismatched family surface: '.$family->id.' -> '.$kind.' -> '.$id);
                }
            }
        }
        foreach ($definitions as $id => $definition) {
            foreach ($namespaces as $namespace => $owner) {
                if (str_starts_with($id, $namespace.'-')
                    && (! isset($bindings[$id]) || $bindings[$id]->manifest->package !== $owner)) {
                    throw new InvalidArgumentException('Component occupies another package namespace: '.$owner.' -> '.$id);
                }
            }
            if (isset($bindings[$id]) && isset($packages[$bindings[$id]->manifest->package])) {
                $packages[$bindings[$id]->manifest->package]->assertOwns($id);
            }
        }

        return new CompiledComponentFamilies($packages, $families, $surfaces);
    }
}
