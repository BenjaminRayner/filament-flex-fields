<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CoverCard\Presentation;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\CoverCard;
use Filament\Actions\Action;

final readonly class CoverCardRenderState
{
    /**
     * @param  list<string>  $styles
     * @param  list<string>  $backgroundStyles
     */
    public function __construct(
        public array $styles,
        public array $backgroundStyles,
        public string $tone,
        public string $radius,
        public bool $fullWidth,
        public bool $fullWidthOnMobile,
        public ?string $topTitle,
        public ?string $topDescription,
        public ?string $footerTitle,
        public ?string $footerDescription,
        public ?Action $footerAction,
        public bool $contentOverlays,
        public ?string $topOverlay,
        public ?string $footerOverlay,
    ) {}

    public static function from(CoverCard $card): self
    {
        $styles = [];
        $ratio = $card->getAspectRatioStyle();
        $width = $card->getContentMaxWidth();
        $fullWidth = $card->isFullWidth();
        if ($ratio !== null) {
            $styles[] = '--fff-cover-card-aspect-ratio: '.$ratio;
        }
        if ($width !== null && ! $fullWidth) {
            $styles[] = '--fff-cover-card-max-width: '.$width;
        }

        return new self(
            styles: $styles,
            backgroundStyles: $card->getBackgroundStyles(),
            tone: $card->getTone(),
            radius: $card->getRadius(),
            fullWidth: $fullWidth,
            fullWidthOnMobile: $card->isFullWidthOnMobile(),
            topTitle: $card->getTopTitle(),
            topDescription: $card->getTopDescription(),
            footerTitle: $card->getFooterTitle(),
            footerDescription: $card->getFooterDescription(),
            footerAction: $card->getFooterAction(),
            contentOverlays: $card->hasContentOverlays(),
            topOverlay: $card->getCustomTopOverlayGradient(),
            footerOverlay: $card->getCustomFooterOverlayGradient(),
        );
    }

    public function hasTop(): bool
    {
        return $this->topTitle !== null || $this->topDescription !== null;
    }

    public function hasFooterCopy(): bool
    {
        return $this->footerTitle !== null || $this->footerDescription !== null;
    }

    public function hasFooter(): bool
    {
        return $this->hasFooterCopy() || $this->footerAction !== null;
    }
}
