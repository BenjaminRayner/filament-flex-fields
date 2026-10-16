<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Concerns\AnimatesProgressFill;
use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasProgressAccentColor;
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress\ConfiguresProgressCircle;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress\HasProgressValue;
use Bjanczak\FilamentFlexFields\Support\Progress\ArcGeometry;
use Bjanczak\FilamentFlexFields\Support\ProgressColor;
use Filament\Schemas\Components\Component;
use InvalidArgumentException;

class ProgressCircle extends Component
{
    use AnimatesProgressFill;
    use ConfiguresProgressCircle;
    use HasControlSize;
    use HasProgressAccentColor;
    use HasProgressValue;

    protected ?string $resolvedSvgInstanceId = null;

    protected string $view = 'filament-flex-fields::schemas.components.progress-circle';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->gap(false);
        $this->columns(1);
    }

    public function getSize(): string
    {
        $size = $this->evaluate($this->size);
        $size = $size instanceof ControlSize ? $size->value : (string) $size;
        if (! in_array($size, ['xs', 'sm', 'md', 'lg'], true)) {
            throw new InvalidArgumentException("Progress circle size [{$size}] is not supported.");
        }

        return $size;
    }

    public function getProgressRatio(): float
    {
        return $this->getProgressValue()->ratio;
    }

    public function getFormattedValue(): string
    {
        $displayValue = $this->getDisplayValue();

        if ($displayValue !== null) {
            return $displayValue;
        }

        return $this->getPercentage().'%';
    }

    public function hasCenterContent(): bool
    {
        return true;
    }

    public function hasGapArc(): bool
    {
        return $this->getVariant() === 'circle' && $this->getGapAngle() > 0;
    }

    public function hasBelowLabel(): bool
    {
        return $this->getContentLayout() === 'center' && filled($this->getLabel())
            && ($this->getSize() === 'xs' || $this->getVariant() === 'semicircle' || mb_strlen($this->getLabel()) > 22);
    }

    /**
     * @return array{
     *     radius: float,
     *     strokeWidth: float,
     *     centerX: float,
     *     centerY: float,
     *     circumference: float,
     *     arcLength: float,
     *     progressLength: float,
     *     gapLength: float,
     *     rotation: float,
     *     viewBox: string,
     *     gradientId: string,
     *     trackGradientId: string,
     *     gradientX1: float,
     *     gradientY1: float,
     *     gradientX2: float,
     *     gradientY2: float,
     *     viewBoxHeight: float,
     *     semicircleFloorInsetPercent: float,
     * }
     */
    public function getSvgMetrics(): array
    {
        return $this->resolveSvgMetrics($this->getVariant(), $this->getGapAngle(), $this->getProgressRatio());
    }

    /**
     * @return array{
     *     radius: float,
     *     strokeWidth: float,
     *     centerX: float,
     *     centerY: float,
     *     circumference: float,
     *     arcLength: float,
     *     progressLength: float,
     *     gapLength: float,
     *     rotation: float,
     *     viewBox: string,
     *     gradientId: string,
     *     trackGradientId: string,
     *     gradientX1: float,
     *     gradientY1: float,
     *     gradientX2: float,
     *     gradientY2: float,
     *     viewBoxHeight: float,
     *     semicircleFloorInsetPercent: float,
     * }
     */
    private function resolveSvgMetrics(string $variant, float $gap, float $ratio): array
    {
        return [
            ...ArcGeometry::forProgress(
                $variant === 'semicircle', $gap, $ratio,
            ),
            'gradientId' => 'fff-progress-circle-gradient-'.$this->resolveSvgInstanceId(),
            'trackGradientId' => 'fff-progress-circle-track-gradient-'.$this->resolveSvgInstanceId(),
        ];
    }

    protected function resolveSvgInstanceId(): string
    {
        if ($this->resolvedSvgInstanceId !== null) {
            return $this->resolvedSvgInstanceId;
        }

        $id = $this->getId();

        if (filled($id)) {
            return $this->resolvedSvgInstanceId = hash('xxh128', (string) $id);
        }

        $key = $this->getKey(isAbsolute: true);

        if (filled($key)) {
            return $this->resolvedSvgInstanceId = substr(md5((string) $key), 0, 12);
        }

        return $this->resolvedSvgInstanceId = substr(md5(spl_object_hash($this)), 0, 12);
    }

    /** @return array<string, mixed> */
    public function getPresentation(): array
    {
        if ($this->getVariant() === 'breakdown') {
            $breakdown = $this->getBreakdown();
            $active = $breakdown->activeIndex !== null ? $breakdown->segments[$breakdown->activeIndex] : null;
            $heading = $this->getHeading();

            return [
                'interactive' => $this->isInteractive(), 'showValue' => $this->shouldShowValue(), 'breakdown' => $breakdown, 'variant' => 'breakdown', 'size' => $this->getSize(),
                'value' => $this->getDisplayValue() ?? (int) round(($active['ratio'] ?? 0) * 100).'%',
                'label' => $this->getLabel() ?? ($active['label'] ?? null),
                'heading' => $heading, 'summaryValue' => $this->getSummaryValue(),
                'changeLabel' => $this->getChangeLabel(), 'periodLabel' => $this->getPeriodLabel(),
                'shell' => $this->hasShell(), 'legend' => $this->shouldShowLegend(),
                'description' => $this->getDescription(), 'footer' => $this->getFooter(),
                'accessibleLabel' => $heading ?? $this->getLabel() ?? __('filament-flex-fields::progress.label'),
            ];
        }
        $progress = $this->getProgressValue();
        $display = $this->getDisplayValue();
        $label = $this->getLabel();
        $heading = $this->getHeading();
        $variant = $this->getVariant();
        $layout = $this->getContentLayout();
        $gap = $this->getGapAngle();

        return [
            'showValue' => $this->shouldShowValue(), 'progress' => $progress, 'value' => $display ?? $progress->percentage.'%',
            'display' => $display, 'label' => $label, 'heading' => $heading,
            'description' => $this->getDescription(), 'footer' => $this->getFooter(),
            'fraction' => $this->getFraction(), 'size' => $this->getSize(),
            'variant' => $variant, 'layout' => $layout, 'gap' => $gap,
            'paused' => $this->isPaused(), 'shell' => $this->hasShell(),
            'below' => $layout === 'center' && $label !== null && ($this->getSize() === 'xs' || $variant === 'semicircle' || mb_strlen($label) > 22),
            'metrics' => $this->resolveSvgMetrics($variant, $gap, $progress->ratio),
            'gradient' => $this->getGradientStops(),
            'trackGradient' => [$this->getTrackGradientFrom(), $this->getTrackGradientTo()],
            'accessibleLabel' => $heading ?? $label ?? __('filament-flex-fields::progress.label'),
        ];
    }

    /** @return array{0: ?string, 1: ?string} */
    public function getGradientStops(): array
    {
        $from = $this->getGradientFrom();
        $to = $this->getGradientTo();
        if ($from !== null && $to !== null) {
            return [$from, $to];
        }
        $legacy = $this->getGradientStroke();
        if ($legacy === null) {
            return [null, null];
        }
        if (! preg_match('/^linear-gradient\([^;{}<>]*\)$/i', $legacy)) {
            throw new InvalidArgumentException('gradientStroke requires a CSS linear-gradient with two color stops.');
        }
        preg_match_all('/#[0-9a-f]{6}\b|#[0-9a-f]{3}\b|(?:rgb|rgba|hsl|hsla)\([^()]+\)/i', $legacy, $matches);
        if (count($matches[0]) !== 2) {
            throw new InvalidArgumentException('Use gradientFrom/gradientTo or a linear-gradient with two hex/rgb/hsl stops.');
        }

        return [ProgressColor::normalize($matches[0][0]), ProgressColor::normalize($matches[0][1])];
    }
}
