<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Concerns\AnimatesProgressFill;
use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasProgressAccentColor;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress\ConfiguresProgressBar;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\Progress\HasProgressValue;
use Bjanczak\FilamentFlexFields\Support\Progress\ProgressValue;
use Bjanczak\FilamentFlexFields\Support\ProgressColor;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

class ProgressBar extends Component
{
    use AnimatesProgressFill;
    use ConfiguresProgressBar;
    use HasControlSize;
    use HasProgressAccentColor;
    use HasProgressValue;

    protected string $view = 'filament-flex-fields::schemas.components.progress-bar';

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

    public function hasSegments(): bool
    {
        return filled($this->getNormalizedSegments());
    }

    public function hasSegmentLabels(): bool
    {
        return collect($this->getNormalizedSegments())
            ->contains(fn (array $segment): bool => filled($segment['label'] ?? null)
                || filled($segment['description'] ?? null)
                || filled($segment['icon'] ?? null));
    }

    public function hasSegmentIcons(): bool
    {
        return collect($this->getNormalizedSegments())
            ->contains(fn (array $segment): bool => filled($segment['icon'] ?? null));
    }

    /**
     * @return list<array{label: ?string, description: ?string, icon: string|BackedEnum|Htmlable|null, state: string}>
     */
    public function getNormalizedSegments(): array
    {
        $segments = $this->evaluate($this->segments);

        if (! is_array($segments) || $segments === []) {
            return [];
        }

        $activeIndex = $this->resolveActiveSegmentIndex(count($segments));
        $normalized = [];

        foreach (array_values($segments) as $index => $segment) {
            if (is_string($segment)) {
                $normalized[] = [
                    'label' => $segment,
                    'description' => null,
                    'icon' => null,
                    'state' => $this->resolveSegmentState($index, $activeIndex),
                ];

                continue;
            }

            if (! is_array($segment)) {
                throw new InvalidArgumentException('Each progress segment must be a string or configuration array.');
            }
            foreach (['label', 'description'] as $textKey) {
                if (isset($segment[$textKey]) && ! is_string($segment[$textKey])) {
                    throw new InvalidArgumentException('Progress segment text must be a string.');
                }
            }
            if (isset($segment['icon']) && ! is_string($segment['icon']) && ! $segment['icon'] instanceof BackedEnum && ! $segment['icon'] instanceof Htmlable) {
                throw new InvalidArgumentException('Progress segment icon must be an icon name, enum or Htmlable.');
            }

            $state = $segment['state'] ?? $this->resolveSegmentState($index, $activeIndex);

            if (! in_array($state, ['complete', 'active', 'pending'], true)) {
                throw new InvalidArgumentException('Progress segment state must be complete, active or pending.');
            }

            $normalized[] = [
                'label' => filled($segment['label'] ?? null) ? (string) $segment['label'] : null,
                'description' => filled($segment['description'] ?? null) ? (string) $segment['description'] : null,
                'icon' => $segment['icon'] ?? null,
                'state' => $state,
            ];
        }

        return $normalized;
    }

    protected function resolveActiveSegmentIndex(int $segmentCount): int
    {
        $activeSegment = $this->evaluate($this->activeSegment);

        if ($activeSegment !== null) {
            return max(0, min($segmentCount - 1, (int) $activeSegment));
        }

        if ($this->isIndeterminate()) {
            return 0;
        }

        $ratio = $this->getProgressRatio();
        $index = (int) floor($ratio * $segmentCount);

        return max(0, min($segmentCount - 1, $index));
    }

    protected function resolveSegmentState(int $index, int $activeIndex): string
    {
        if ($index < $activeIndex) {
            return 'complete';
        }

        if ($index === $activeIndex) {
            return 'active';
        }

        return 'pending';
    }

    public function getActiveSegmentIndex(): int
    {
        return $this->resolveActiveSegmentIndex(count($this->getNormalizedSegments()));
    }

    public function getProgressRatio(): float
    {
        if ($this->isIndeterminate()) {
            return 0.0;
        }

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

    public function hasHeader(): bool
    {
        if ($this->hasCardChrome()) {
            return false;
        }

        return filled($this->getLabel()) || ($this->shouldShowValue() && ! $this->isIndeterminate() && ! $this->hasSegments() && ! $this->isPillsVariant());
    }

    public function getVariant(): string
    {
        $variant = (string) $this->evaluate($this->variant);

        if (! in_array($variant, ['track', 'pills'], true)) {
            throw new InvalidArgumentException("Progress bar variant [{$variant}] is not supported.");
        }

        return $variant;
    }

    public function isPillsVariant(): bool
    {
        return $this->getVariant() === 'pills' && ! $this->hasSegments();
    }

    public function usesAutoPillCount(): bool
    {
        return $this->evaluate($this->pillCount) === null;
    }

    public function getPillCount(): ?int
    {
        $count = $this->evaluate($this->pillCount);

        if ($count === null) {
            return null;
        }

        $count = (int) $count;

        if ($count < 1) {
            throw new InvalidArgumentException('Progress bar pill count must be at least 1.');
        }

        return min($count, 100);
    }

    public function getActivePillCount(): int
    {
        if ($this->isIndeterminate() || $this->usesAutoPillCount()) {
            return 0;
        }

        $count = $this->getPillCount();
        $active = (int) round($this->getProgressRatio() * $count);

        return max(0, min($count, $active));
    }

    public function getGradientFrom(): string
    {
        $from = $this->evaluate($this->gradientFrom);

        if (filled($from)) {
            return ProgressColor::normalize((string) $from);
        }

        return match ($this->getColorToken() ?? 'primary') {
            'success' => 'rgb(34 197 94)',
            'warning' => 'rgb(245 158 11)',
            'danger' => 'rgb(239 68 68)',
            default => 'rgb(239 68 68)',
        };
    }

    public function getGradientTo(): string
    {
        $to = $this->evaluate($this->gradientTo);

        if (filled($to)) {
            return ProgressColor::normalize((string) $to);
        }

        return match ($this->getColorToken() ?? 'primary') {
            'success' => 'rgb(74 222 128)',
            'warning' => 'rgb(251 191 36)',
            'danger' => 'rgb(245 158 11)',
            default => 'rgb(245 158 11)',
        };
    }

    public function getPillColorForIndex(int $index): ?string
    {
        if ($this->usesAutoPillCount()) {
            return null;
        }

        $activePillCount = $this->getActivePillCount();

        if ($index < 0 || $index >= $activePillCount) {
            return null;
        }

        if ($activePillCount === 1) {
            return $this->getGradientFrom();
        }

        return $this->interpolateColor(
            $this->getGradientFrom(),
            $this->getGradientTo(),
            $index / ($activePillCount - 1),
        );
    }

    protected function interpolateColor(string $from, string $to, float $ratio): string
    {
        $fromChannels = ProgressColor::parseRgbChannels($from);
        $toChannels = ProgressColor::parseRgbChannels($to);
        $ratio = max(0.0, min(1.0, $ratio));

        $red = (int) round($fromChannels[0] + ($toChannels[0] - $fromChannels[0]) * $ratio);
        $green = (int) round($fromChannels[1] + ($toChannels[1] - $fromChannels[1]) * $ratio);
        $blue = (int) round($fromChannels[2] + ($toChannels[2] - $fromChannels[2]) * $ratio);

        return ProgressColor::toRgbString($red, $green, $blue);
    }

    public function hasShell(): bool
    {
        return (bool) $this->evaluate($this->hasShell);
    }

    public function getDescription(): ?string
    {
        $description = $this->evaluate($this->description);

        return filled($description) ? (string) $description : null;
    }

    public function getFooter(): ?string
    {
        $footer = $this->evaluate($this->footer);

        return filled($footer) ? (string) $footer : null;
    }

    public function shouldShowValueBadge(): bool
    {
        return (bool) $this->evaluate($this->shouldShowValueBadge);
    }

    public function hasCardChrome(): bool
    {
        return $this->hasShell();
    }

    public function hasCardHeader(): bool
    {
        return filled($this->getLabel()) || ($this->shouldShowValue() && $this->shouldShowValueBadge() && ! $this->isIndeterminate());
    }

    public function getStartMarker(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->startMarker);
    }

    public function getEndMarker(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->endMarker);
    }

    public function getCurrentMarker(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->currentMarker);
    }

    public function hasStartMarker(): bool
    {
        return filled($this->getStartMarker());
    }

    public function hasEndMarker(): bool
    {
        return filled($this->getEndMarker());
    }

    public function hasCurrentMarker(): bool
    {
        return filled($this->getCurrentMarker());
    }

    public function hasTrackMarkers(): bool
    {
        return $this->hasStartMarker() || $this->hasEndMarker() || $this->hasCurrentMarker();
    }

    public function getActiveSegmentProgress(): float
    {
        $progress = $this->finiteProgressNumber($this->evaluate($this->activeSegmentProgress));

        return max(0.0, min(1.0, $progress));
    }

    public function shouldShowSegmentThumb(): bool
    {
        $condition = $this->evaluate($this->shouldShowSegmentThumb);

        if ($condition !== null) {
            return (bool) $condition;
        }

        return $this->hasSegments();
    }

    public function getRemainingTrackStyle(): string
    {
        $style = (string) $this->evaluate($this->remainingTrackStyle);

        if (! in_array($style, ['solid', 'dashed'], true)) {
            throw new InvalidArgumentException("Progress bar remaining track style [{$style}] is not supported.");
        }

        return $style;
    }

    public function getSegmentFillPercentage(): float
    {
        $segments = $this->getNormalizedSegments();
        $count = count($segments);

        if ($count === 0) {
            return 0.0;
        }

        $activeIndex = $this->getActiveSegmentIndex();
        $activeProgress = $this->getActiveSegmentProgress();

        return (($activeIndex + $activeProgress) / $count) * 100;
    }

    public function getSegmentFillWidthForIndex(int $index): float
    {
        $segments = $this->getNormalizedSegments();

        if (! isset($segments[$index])) {
            return 0.0;
        }

        return match ($segments[$index]['state']) {
            'complete' => 100.0,
            'active' => $this->getActiveSegmentProgress() * 100,
            default => 0.0,
        };
    }

    public function getSegmentThumbPosition(): float
    {
        return $this->getSegmentFillPercentage();
    }

    /** @return array<string, mixed> */
    public function getPresentation(): array
    {
        $progress = $this->getProgressValue();
        $indeterminate = $this->isIndeterminate();
        $segments = $this->getNormalizedSegments();
        $activeProgress = $this->getActiveSegmentProgress();
        $fills = array_map(static fn (array $segment): float => match ($segment['state']) {
            'complete' => 100.0, 'active' => $activeProgress * 100.0, default => 0.0,
        }, $segments);
        $hasLabels = false;
        $hasIcons = false;
        foreach ($segments as $segment) {
            $hasLabels = $hasLabels || filled($segment['label']) || filled($segment['description']) || filled($segment['icon']);
            $hasIcons = $hasIcons || filled($segment['icon']);
        }
        if ($segments !== []) {
            $progress = new ProgressValue($progress->max * array_sum($fills) / count($segments) / 100.0, $progress->max);
        }
        $pills = $segments === [] && $this->getVariant() === 'pills';
        $count = $pills ? $this->getPillCount() : null;
        $label = $this->getLabel();
        $showValue = $this->shouldShowValue() && ! $indeterminate;

        return [
            'progress' => $progress, 'percentage' => $indeterminate ? 0 : $progress->percentage,
            'value' => $this->getDisplayValue() ?? $progress->percentage.'%',
            'label' => $label, 'size' => $this->getSize(), 'showValue' => $showValue,
            'description' => $this->getDescription(), 'footer' => $this->getFooter(),
            'shell' => $this->hasShell(), 'indeterminate' => $indeterminate,
            'segments' => $segments, 'fills' => $fills, 'hasLabels' => $hasLabels, 'hasIcons' => $hasIcons,
            'segmentThumb' => $this->shouldShowSegmentThumb(), 'pills' => $pills, 'pillCount' => $count,
            'activePills' => $count === null || $indeterminate ? 0 : (int) round($count * $progress->ratio),
            'gradientFrom' => $this->getGradientFrom(), 'gradientTo' => $this->getGradientTo(),
            'badge' => $this->shouldShowValueBadge(),
            'startMarker' => $this->getStartMarker(), 'endMarker' => $this->getEndMarker(), 'currentMarker' => $this->getCurrentMarker(),
            'dashed' => ! $pills && $segments === [] && $this->getRemainingTrackStyle() === 'dashed',
        ];
    }
}
