<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCardGroup;
use Closure;
use InvalidArgumentException;

trait HasCardAppearance
{
    protected string|Closure $variant = 'default';

    protected string|Closure $context = 'auto';

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function context(string|Closure $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function standalone(): static
    {
        return $this->context('standalone');
    }

    public function inGroup(): static
    {
        return $this->context('group');
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['default', 'secondary', 'tertiary', 'outline', 'transparent'], true)) {
            throw new InvalidArgumentException("Item card variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function getContext(): string
    {
        $context = (string) $this->evaluate($this->context);

        if ($context === 'auto') {
            return $this->getParentItemCardGroup() !== null ? 'group' : 'standalone';
        }

        if (! in_array($context, ['group', 'standalone'], true)) {
            throw new InvalidArgumentException("Item card context [{$context}] is not supported.");
        }

        return $context;
    }

    protected function getParentItemCardGroup(): ?ItemCardGroup
    {
        if (! isset($this->container)) {
            return null;
        }

        $parent = $this->getContainer()->getParentComponent();

        return $parent instanceof ItemCardGroup ? $parent : null;
    }
}
