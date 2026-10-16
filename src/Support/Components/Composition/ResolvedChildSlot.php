<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

/** Registry-revision metadata only; never a child instance, callback or user state. */
final readonly class ResolvedChildSlot
{
    public function __construct(public ChildSlot $slot, public ChildComponentContract $contract) {}
}
