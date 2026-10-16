<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use InvalidArgumentException;

/** Application-owned selection of two exact installations, never last-provider-wins. */
final readonly class ComponentPackageSelection
{
    public function __construct(public ComponentPackage $preferred, public ComponentPackage $replaced)
    {
        if ($preferred->namespace !== $replaced->namespace
            || $preferred->assets->manifest->package === $replaced->assets->manifest->package) {
            throw new InvalidArgumentException('Package selection requires two different package identities in the same namespace. Composer owns upgrades of one package.');
        }
    }

    /** @return array{namespace: string, preferred: array<string, int|string>, replaced: array<string, int|string>} */
    public function export(): array
    {
        return ['namespace' => $this->preferred->namespace,
            'preferred' => $this->preferred->export(), 'replaced' => $this->replaced->export()];
    }
}
