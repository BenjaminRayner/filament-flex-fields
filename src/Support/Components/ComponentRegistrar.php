<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentPackageSelection;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentRegistrationCollector;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\RegistrationMetadata;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Support\Facades\FilamentAsset;
use InvalidArgumentException;

/** Register an extension during provider boot, before rendering any component. */
final class ComponentRegistrar
{
    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly ComponentRegistrationCollector $collector = new ComponentRegistrationCollector,
    ) {}

    /** Declare an application decision before the collection barrier; never mutate a live graph. */
    public function selectPackage(ComponentPackageSelection $selection): void
    {
        $this->registry->assertMutable();
        $this->collector->selectPackage($selection);
    }

    /** @return list<array{namespace: string, preferred: array<string, int|string>, replaced: array<string, int|string>}> */
    public function appliedPackageSelections(): array
    {
        return $this->collector->appliedSelections();
    }

    /** @param array<string, string> $styles Local built CSS files keyed by their declared identity. */
    public function register(ComponentDefinition $definition, array $styles = [], ?string $entryPath = null, ?AssetPackage $assetPackage = null, ?RegistrationMetadata $metadata = null): void
    {
        $this->registerFamily([new ComponentRegistration($definition, $styles, $entryPath, $assetPackage, $metadata)]);
    }

    /** @param list<ComponentDefinition> $definitions
     * @param  array<string, RegistrationMetadata>  $metadata
     */
    public function collectPackage(AssetPackage $package, array $definitions, array $metadata = []): void
    {
        if (! array_is_list($definitions) || $definitions === [] || count($definitions) > 128) {
            throw new InvalidArgumentException('A package collection needs 1–128 typed component definitions per call.');
        }
        if (count($metadata) > 128) {
            throw new InvalidArgumentException('Package metadata exceeds the per-collection definition budget.');
        }
        foreach ($metadata as $value) {
            if (! $value instanceof RegistrationMetadata) {
                throw new InvalidArgumentException('Package metadata requires typed registration metadata.');
            }
        }
        $members = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof ComponentDefinition) {
                throw new InvalidArgumentException('A package collection requires typed component definitions.');
            }
            $members[] = new ComponentRegistration($definition, assetPackage: $package, metadata: $metadata[$definition->id] ?? null);
            unset($metadata[$definition->id]);
        }
        if ($metadata !== []) {
            throw new InvalidArgumentException('Package metadata must identify a definition in this collection.');
        }
        $this->collectFamily($members);
    }

    /** @param list<ComponentDefinition> $definitions Declared surfaces and shared primitives.
     * @param  array<string, RegistrationMetadata>  $metadata
     */
    public function collectComponentFamily(ComponentFamily $family, array $definitions, array $metadata = []): void
    {
        $surfaces = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof ComponentDefinition) {
                throw new InvalidArgumentException('Component family requires typed definitions.');
            }
            $family->package->assertOwns($definition->id);
            if ($definition->surface !== null && ! $definition->surface->family->sameContractAs($family)) {
                throw new InvalidArgumentException('Definition belongs to another family: '.$definition->id);
            }
            if ($definition->surface !== null) {
                $surfaces[$definition->id] = true;
            }
        }
        foreach ($family->surfaces as $id) {
            if (! isset($surfaces[$id])) {
                throw new InvalidArgumentException('Family collection must include its declared surface: '.$id);
            }
        }
        $this->collectPackage($family->package->assets, $definitions, $metadata);
    }

    /**
     * Register related fields, columns, entries and shared resources in one validated operation.
     * Dependencies may refer to later members. Validation failures leave the live registry unchanged.
     *
     * @param  list<ComponentRegistration>  $members
     */
    public function registerFamily(array $members): void
    {
        $this->registry->assertMutable();
        $this->validateMembers($members);
        $this->commitMembers($members);
    }

    /**
     * Collect provider-order-independent families. Their complete graph and asset
     * ownership are committed together at the application boot barrier.
     *
     * @param  list<ComponentRegistration>  $members
     */
    public function collectFamily(array $members): void
    {
        $this->registry->assertMutable();
        $this->validateMembers($members);
        foreach ($members as $member) {
            $this->validateAssets($member);
        }
        $this->collector->collect($members);
    }

    /** Called once by bootstrap; failure keeps pending families available for diagnosis. */
    public function completeCollection(): void
    {
        $this->registry->assertMutable();
        $members = $this->collector->members();
        if ($members !== []) {
            $this->commitMembers($members);
        }
        $this->collector->seal();
    }

    /** @param list<ComponentRegistration> $members */
    private function validateMembers(array $members): void
    {
        if (! array_is_list($members) || $members === [] || count($members) > 128) {
            throw new InvalidArgumentException('A component family needs 1–128 typed registrations.');
        }
        foreach ($members as $member) {
            if (! $member instanceof ComponentRegistration) {
                throw new InvalidArgumentException('Component families require typed registrations.');
            }
        }
    }

    /** @param list<ComponentRegistration> $members */
    private function commitMembers(array $members): void
    {
        $registeredPaths = [];
        $registeredStyles = [];
        foreach (FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]) as $asset) {
            $registeredStyles[$asset->getId()] = true;
            if (($path = $asset->getPath()) !== null && ($canonical = realpath($path)) !== false) {
                $registeredPaths[$canonical] = true;
            }
        }
        $registeredEntries = [];
        foreach ([...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME]), ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME], withCore: false)] as $asset) {
            $registeredEntries[$asset->getId()] = true;
            if (($path = $asset->getPath()) !== null && ($canonical = realpath($path)) !== false) {
                $registeredPaths[$canonical] = true;
            }
        }
        foreach ($members as $member) {
            if (! $member instanceof ComponentRegistration) {
                throw new InvalidArgumentException('Component families require typed registrations.');
            }
            $this->validateAssets($member);
            foreach ([...array_values($member->styles), ...($member->entryPath === null ? [] : [$member->entryPath])] as $path) {
                $canonical = realpath($path);
                if ($canonical === false || isset($registeredPaths[$canonical])) {
                    throw new InvalidArgumentException('Reuse a component dependency instead of registering the same asset file again: '.$path);
                }
                $registeredPaths[$canonical] = true;
            }
            foreach ($member->definition->styles as $style) {
                $identity = FlexFieldAssets::stylesheetId($style);
                if (isset($registeredStyles[$identity])) {
                    throw new InvalidArgumentException('A registered Filament stylesheet identity cannot be reused: '.$identity);
                }
                $registeredStyles[$identity] = true;
            }
            if ($member->definition->entry !== null) {
                if (isset($registeredEntries[$member->definition->entry])) {
                    throw new InvalidArgumentException('A registered Filament JS identity cannot be reused: '.$member->definition->entry);
                }
                $registeredEntries[$member->definition->entry] = true;
            }
        }
        $bindings = [];
        $metadata = [];
        foreach ($members as $member) {
            if ($member->metadata !== null) {
                $metadata[$member->definition->id] = $member->metadata;
            }
            if ($member->assetPackage !== null) {
                $bindings[$member->definition->id] = $member->assetPackage;
            } else {
                $this->validateLegacyHelpers($member->definition);
            }
        }
        $prepared = $this->registry->prepareBatch(array_map(static fn (ComponentRegistration $member): ComponentDefinition => $member->definition, $members), $bindings, $metadata);
        foreach ($prepared->graph->packageAssets->packages as $package) {
            foreach ($package->manifest->artifacts as $id => $artifact) {
                if (isset($registeredPaths[$package->sourcePath($id)])) {
                    throw new InvalidArgumentException('A manifest artifact cannot also be registered as a legacy Filament asset: '.$artifact->path);
                }
            }
        }
        foreach ($prepared->graph->packageAssets->helpers as $id => $reference) {
            if (isset($registeredEntries[$id])) {
                throw new InvalidArgumentException('A manifest helper cannot reuse a registered Filament JS identity: '.$id);
            }
        }
        $assets = [];
        foreach ($members as $member) {
            if ($member->assetPackage !== null) {
                continue; // Immutable releases are published by FLEX, not Filament's per-file copier.
            }
            foreach ($member->styles as $id => $path) {
                $assets[] = FlexFieldsCss::make(FlexFieldAssets::stylesheetId($id), $path)->loadedOnRequest();
            }
            if ($member->entryPath !== null && $member->definition->entry !== null) {
                $assets[] = FlexFieldsAlpineComponent::make($member->definition->entry, $member->entryPath);
            }
        }
        FilamentAsset::register($assets, FilamentFlexFieldsPlugin::PACKAGE_NAME);
        $this->registry->commit($prepared);
    }

    private function validateAssets(ComponentRegistration $member): void
    {
        $definition = $member->definition;
        $styles = $member->styles;
        $entryPath = $member->entryPath;
        if ($definition->catalog?->official || $definition->catalog?->author === 'FLEX') {
            throw new InvalidArgumentException('Official FLEX identity is reserved for built-in components.');
        }
        if (FlexFieldAssets::resolveCanonicalComponent($definition->id) !== $definition->id) {
            throw new InvalidArgumentException('A built-in component alias cannot be reused by an extension.');
        }
        if ($definition->entry !== null && in_array($definition->entry.'.js', FlexFieldAssets::alpineSharedChunkNames(), true)) {
            throw new InvalidArgumentException('Built-in shared JS chunk identities are reserved.');
        }
        if ($member->assetPackage !== null) {
            if ($styles !== [] || $entryPath !== null || $definition->publications !== []) {
                throw new InvalidArgumentException('Manifest registrations cannot mix manual asset files or legacy publication directories.');
            }
            $manifest = $member->assetPackage->manifest;
            foreach ($definition->styles as $style) {
                $id = $manifest->styles[$style] ?? throw new InvalidArgumentException('Missing manifest stylesheet: '.$style);
                $member->assetPackage->sourcePath($id);
            }
            if ($definition->entry !== null) {
                $id = $manifest->entries[$definition->entry] ?? throw new InvalidArgumentException('Missing manifest module entry: '.$definition->entry);
                $member->assetPackage->sourcePath($id);
            }
            foreach ($definition->ssrScripts as $helper) {
                $id = $manifest->helpers[$helper] ?? throw new InvalidArgumentException('Missing manifest SSR helper: '.$helper);
                $member->assetPackage->sourcePath($id);
            }
        }
        $expected = $member->assetPackage === null ? $definition->styles : [];
        $actual = array_keys($styles);
        sort($expected);
        sort($actual);
        if ($expected !== $actual || ($member->assetPackage === null && (($definition->entry !== null) !== ($entryPath !== null)))) {
            throw new InvalidArgumentException('Every owned style and declared entry needs exactly one asset file.');
        }
        foreach ([...array_values($styles), ...($entryPath === null ? [] : [$entryPath])] as $path) {
            if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
                throw new InvalidArgumentException('Custom component assets must be readable local build outputs.');
            }
        }
        foreach ($definition->styles as $style) {
            if (in_array(FlexFieldAssets::stylesheetId($style), [FlexFieldAssets::CORE_STYLESHEET_ID, FlexFieldAssets::PLAYGROUND_STYLESHEET_ID], true)) {
                throw new InvalidArgumentException('Global FLEX stylesheet identities are reserved.');
            }
        }
    }

    private function validateLegacyHelpers(ComponentDefinition $definition): void
    {
        if ($definition->ssrScripts === []) {
            return;
        }
        $scripts = [];
        foreach (FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME], withCore: false) as $asset) {
            $scripts[$asset->getId()] = $asset;
        }
        foreach ($definition->ssrScripts as $helper) {
            $asset = $scripts[$helper] ?? throw new InvalidArgumentException('Missing registered SSR helper: '.$definition->id.' -> '.$helper);
            $path = $asset->getPath();
            if (! $asset->isRemote() && ($path === null || ! is_file($path) || ! is_readable($path))) {
                throw new InvalidArgumentException('Unreadable registered SSR helper: '.$definition->id.' -> '.$helper);
            }
        }
    }
}
