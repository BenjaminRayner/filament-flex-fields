<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Compilation;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\PackageArtifactReference;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use InvalidArgumentException;

/** Compile roots and shared modules before making any registration visible. */
final class PackageAssetCompiler
{
    /**
     * @param  array<string, ComponentDefinition>  $definitions
     * @param  array<string, AssetPackage>  $bindings
     * @param  array<string, list<string>>|ComponentProjections  $componentStyles
     */
    public function compile(array $definitions, array $bindings, array|ComponentProjections $componentStyles): CompiledPackageAssets
    {
        (new PackageAssetBudget)->validate($bindings);
        $packages = [];
        $entries = [];
        $styles = [];
        $helpers = [];
        $chunks = [];
        $eager = [];
        $sourceOwners = [];
        foreach ($bindings as $component => $package) {
            if (! $package instanceof AssetPackage || ! isset($definitions[$component])) {
                throw new InvalidArgumentException('Manifest binding requires a registered component and typed package.');
            }
            $name = $package->manifest->package;
            if (isset($packages[$name]) && ! $packages[$name]->sameInstallationAs($package)) {
                throw new InvalidArgumentException('A boot snapshot cannot contain conflicting installations of '.$name);
            }
            if (! isset($packages[$name])) {
                foreach ($package->manifest->artifacts as $id => $artifact) {
                    $source = $package->sourcePath($id); // Existence/containment, not byte hashing on HTTP boot.
                    if (isset($sourceOwners[$source])) {
                        throw new InvalidArgumentException('A source artifact cannot have multiple package owners: '.$name.' -> '.$id);
                    }
                    $sourceOwners[$source] = $name;
                    if ($artifact->type === ArtifactKind::Module) {
                        $chunks[$package->moduleIdentity($id).'.js'] = new PackageArtifactReference($package, $id);
                    }
                }
                $this->assertPublicationOwnership($definitions, $package);
                $packages[$name] = $package;
            }
            $definition = $definitions[$component];
            $manifest = $package->manifest;
            foreach ($definition->styles as $style) {
                $artifact = $manifest->styles[$style] ?? throw new InvalidArgumentException('Missing manifest stylesheet: '.$name.' -> '.$component.' -> '.$style);
                $styles[$style] = new PackageArtifactReference($package, $artifact);
            }
            foreach ($definition->ssrScripts as $helper) {
                $artifact = $manifest->helpers[$helper] ?? throw new InvalidArgumentException('Missing manifest SSR helper: '.$name.' -> '.$component.' -> '.$helper);
                $reference = new PackageArtifactReference($package, $artifact);
                if (isset($helpers[$helper]) && (! $helpers[$helper]->package->sameInstallationAs($package) || $helpers[$helper]->id !== $artifact)) {
                    throw new InvalidArgumentException('Multiple package owners for SSR helper: '.$helper);
                }
                $helpers[$helper] = $reference;
            }
            $eager[$component] = [];
            if ($definition->entry === null) {
                continue;
            }
            $artifact = $manifest->entries[$definition->entry] ?? throw new InvalidArgumentException('Missing manifest entry: '.$name.' -> '.$component.' -> '.$definition->entry);
            $entries[$definition->entry] = new PackageArtifactReference($package, $artifact);
            foreach ($manifest->eagerArtifactsFor($definition->entry) as $id) {
                if ($manifest->artifact($id)->type === ArtifactKind::Module) {
                    $eager[$component][] = $package->moduleIdentity($id).'.js';
                }
            }
        }
        // Every eager CSS node must be owned in this component's dependency closure.
        foreach ($bindings as $component => $package) {
            $entry = $definitions[$component]->entry;
            if ($entry === null) {
                continue;
            }
            $owned = [];
            foreach ($componentStyles instanceof ComponentProjections ? $componentStyles->stylesFor($component) : $componentStyles[$component] as $style) {
                $reference = $styles[$style] ?? null;
                if ($reference !== null && $reference->package->sameInstallationAs($package)) {
                    $owned[$reference->id] = true;
                }
            }
            // CSS @import dependencies are covered by their owning stylesheet;
            // do not force a second link that would apply those rules twice.
            $queue = array_keys($owned);
            for ($index = 0; $index < count($queue); $index++) {
                foreach ($package->manifest->artifact($queue[$index])->imports as $dependency) {
                    if (! isset($owned[$dependency])) {
                        $owned[$dependency] = true;
                        $queue[] = $dependency;
                    }
                }
            }
            foreach ($package->manifest->eagerArtifactsFor($entry) as $id) {
                if ($package->manifest->artifact($id)->type === ArtifactKind::Stylesheet && ! isset($owned[$id])) {
                    throw new InvalidArgumentException('Eager CSS lacks a declared component owner: '.$package->manifest->package.' -> '.$component.' -> '.$id);
                }
            }
        }

        return new CompiledPackageAssets($bindings, $packages, $entries, $chunks, $styles, $helpers, $eager);
    }

    /** @param array<string, ComponentDefinition> $definitions */
    private function assertPublicationOwnership(array $definitions, AssetPackage $package): void
    {
        $directory = $package->publicDirectory();
        foreach ($definitions as $definition) {
            foreach ($definition->publications as $publication) {
                $target = $publication->publicDirectory;
                if ($target === $directory || str_starts_with($target, $directory.'/') || str_starts_with($directory, $target.'/')) {
                    throw new InvalidArgumentException('Legacy resource overlaps a manifest package release: '.$target);
                }
            }
        }
    }
}
