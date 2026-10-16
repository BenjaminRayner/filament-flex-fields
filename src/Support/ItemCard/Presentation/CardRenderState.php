<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\ItemCard;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

final readonly class CardRenderState
{
    public function __construct(
        public string|Htmlable|null $heading,
        public string|Htmlable|null $description,
        public string|BackedEnum|Htmlable|null $icon,
        public ?string $image,
        public string $imageShape,
        public string $imageAlt,
        public string $variant,
        public string $context,
        public bool $pressable,
        public ?string $url,
        public bool $newTab,
        public ?Action $action,
        public string|BackedEnum|Htmlable|null $chevron,
        public ?Schema $childSchema,
    ) {}

    public static function from(ItemCard $card): self
    {
        $heading = $card->getHeading();
        $image = $card->getImage();
        $url = $card->getUrl();
        $childSchema = $card->getChildSchema();
        $pressable = $card->resolvePressable($url, ($childSchema?->getComponents() ?? []) !== []);

        return new self(
            heading: $heading,
            description: $card->getDescription(),
            icon: $image === null ? $card->getConfiguredIcon() : null,
            image: $image,
            imageShape: $card->getImageShape(),
            imageAlt: $card->getConfiguredImageAlt() ?? PlainText::from($heading),
            variant: $card->getVariant(),
            context: $card->getContext(),
            pressable: $pressable,
            url: $url,
            newTab: $card->shouldOpenUrlInNewTab(),
            action: $pressable ? $card->getPressableAction() : null,
            chevron: $card->hasChevron() ? $card->getChevronIcon() : null,
            childSchema: $childSchema,
        );
    }

    public function tag(): string
    {
        return $this->pressable ? ($this->url !== null ? 'a' : 'button') : 'div';
    }
}
