<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistration;
use InvalidArgumentException;
use LogicException;

/** Boot-only pending families; no files or live definitions change on collection. */
final class ComponentRegistrationCollector
{
    public const MAX_MEMBERS = 8192;

    /** @var list<ComponentRegistration> */
    private array $members = [];

    private bool $sealed = false;

    /** @var array<string, ComponentPackageSelection> */
    private array $selections = [];

    /** @var list<array{namespace: string, preferred: array<string, int|string>, replaced: array<string, int|string>}> */
    private array $appliedSelections = [];

    /** @param list<ComponentRegistration> $members */
    public function collect(array $members): void
    {
        if ($this->sealed) {
            throw new LogicException('Component registration collection is closed.');
        }
        if (! array_is_list($members) || count($members) > self::MAX_MEMBERS - count($this->members)) {
            throw new InvalidArgumentException('Pending component registrations exceed the bounded collection budget.');
        }
        foreach ($members as $member) {
            if (! $member instanceof ComponentRegistration) {
                throw new InvalidArgumentException('Component collection requires typed registrations.');
            }
        }
        foreach ($members as $member) {
            $this->members[] = $member;
        }
    }

    public function selectPackage(ComponentPackageSelection $selection): void
    {
        if ($this->sealed) {
            throw new LogicException('Component registration collection is closed.');
        }
        $namespace = $selection->preferred->namespace;
        if (isset($this->selections[$namespace]) || count($this->selections) >= 128) {
            throw new InvalidArgumentException('Declare at most one package selection per namespace and at most 128 selections.');
        }
        $this->selections[$namespace] = $selection;
    }

    /** @return list<ComponentRegistration> */
    public function members(): array
    {
        return (new ComponentRegistrationSelection)->resolve($this->members, $this->selections);
    }

    public function seal(): void
    {
        if ($this->sealed) {
            return;
        }
        $selections = $this->selections;
        ksort($selections, SORT_STRING);
        $this->appliedSelections = array_values(array_map(static fn (ComponentPackageSelection $selection): array => $selection->export(), $selections));
        $this->members = [];
        $this->selections = [];
        $this->sealed = true;
    }

    /** @return list<array{namespace: string, preferred: array<string, int|string>, replaced: array<string, int|string>}> */
    public function appliedSelections(): array
    {
        return $this->appliedSelections;
    }
}
