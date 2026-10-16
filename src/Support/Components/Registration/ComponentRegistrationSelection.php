<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use InvalidArgumentException;

/** Resolve complete alternative packages before any graph or asset registration is committed. */
final class ComponentRegistrationSelection
{
    /**
     * @param  list<ComponentRegistration>  $members
     * @param  array<string, ComponentPackageSelection>  $selections
     * @return list<ComponentRegistration>
     */
    public function resolve(array $members, array $selections): array
    {
        if ($selections === []) {
            return $members;
        }
        $packages = [];
        foreach ($members as $member) {
            if ($member->assetPackage !== null) {
                $packages[$member->assetPackage->manifest->package][] = $member;
            }
        }
        $excluded = [];
        foreach ($selections as $selection) {
            $preferred = $this->candidate($selection->preferred, $packages);
            $replaced = $this->candidate($selection->replaced, $packages);
            foreach ($replaced as $id => $member) {
                $replacement = $preferred[$id] ?? throw new InvalidArgumentException('Package selection cannot drop a registered component: '.$id);
                if ($replacement->definition->kind !== $member->definition->kind
                    || $replacement->definition->surface?->family->id !== $member->definition->surface?->family->id) {
                    throw new InvalidArgumentException('Package selection cannot change component kind or logical family: '.$id);
                }
            }
            $excluded[$selection->replaced->assets->manifest->package] = true;
        }

        return array_values(array_filter($members, static fn (ComponentRegistration $member): bool => $member->assetPackage === null
            || ! isset($excluded[$member->assetPackage->manifest->package])));
    }

    /**
     * @param  array<string, list<ComponentRegistration>>  $packages
     * @return array<string, ComponentRegistration>
     */
    private function candidate(ComponentPackage $expected, array $packages): array
    {
        $name = $expected->assets->manifest->package;
        $members = $packages[$name] ?? throw new InvalidArgumentException('Explicit package selection requires both collected packages: '.$name);
        $result = [];
        $semantic = false;
        $families = [];
        foreach ($members as $member) {
            if ($member->assetPackage === null || ! $expected->assets->sameInstallationAs($member->assetPackage)) {
                throw new InvalidArgumentException('Collected package differs from its selected installation: '.$name);
            }
            $definition = $member->definition;
            $expected->assertOwns($definition->id);
            if (isset($result[$definition->id])) {
                throw new InvalidArgumentException('Package selection cannot hide duplicate registrations inside one package: '.$definition->id);
            }
            if ($definition->surface !== null) {
                $family = $definition->surface->family;
                if (! $expected->sameContractAs($family->package)) {
                    throw new InvalidArgumentException('Collected semantic package differs from its selected contract: '.$name);
                }
                if (isset($families[$family->id]) && ! $families[$family->id]->sameContractAs($family)) {
                    throw new InvalidArgumentException('Package selection cannot hide a conflicting family contract: '.$family->id);
                }
                $families[$family->id] = $family;
                $semantic = true;
            }
            $result[$definition->id] = $member;
        }
        if (! $semantic) {
            throw new InvalidArgumentException('Package selection requires semantic family registrations: '.$name);
        }
        foreach ($families as $family) {
            foreach ($family->surfaces as $kind => $id) {
                $surface = ($result[$id] ?? null)?->definition->surface;
                if ($surface === null || $surface->kind->value !== $kind || ! $surface->family->sameContractAs($family)) {
                    throw new InvalidArgumentException('Package selection requires complete candidate families: '.$family->id.' -> '.$id);
                }
            }
        }

        return $result;
    }
}
