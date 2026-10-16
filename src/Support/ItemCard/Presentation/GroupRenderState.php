<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCardGroup;
use Illuminate\Contracts\Support\Htmlable;

final readonly class GroupRenderState
{
    public function __construct(
        public string|Htmlable|null $heading,
        public string|Htmlable|null $description,
        public string $layout,
        public string $variant,
        public bool $divided,
        public bool $outsideHeader,
    ) {}

    public static function from(ItemCardGroup $group): self
    {
        return new self($group->getHeading(), $group->getDescription(), $group->getLayout(), $group->getVariant(), $group->isDivided(), $group->getHeaderStyle() === 'outside');
    }

    /** @return array<int|string, string|bool> */
    public function surfaceClasses(): array
    {
        return ['item-card-group', 'item-card-group--'.$this->layout, 'item-card-group--'.$this->variant, 'item-card-group--divided' => $this->divided];
    }

    /** @return array<int|string, string|bool> */
    public function rootClasses(): array
    {
        return $this->outsideHeader ? ['item-card-group-host', 'item-card-group--'.$this->variant] : $this->surfaceClasses();
    }
}
