<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TextInput;

use Filament\Schemas\Schema;

/** Native child discovery and validation, with state beside the owning scalar input. */
final class AssociatedInputSchema extends Schema
{
    public function getStatePath(bool $isAbsolute = true): ?string
    {
        return $isAbsolute
            ? $this->getParentComponent()?->getContainer()->getStatePath()
            : parent::getStatePath(false);
    }
}
