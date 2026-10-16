<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Segments;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;

/** Immutable presentation only: it never loads child schemas or asset files. */
final readonly class SegmentItem
{
    /** @param array<string|int, string>|string|null $badgeColor */
    public function __construct(
        public string|int $key,
        public string|Htmlable|null $label,
        public string|BackedEnum|Htmlable|null $icon = null,
        public bool $disabled = false,
        public ?string $tooltip = null,
        public ?string $panelId = null,
        public ?string $visibilityJs = null,
        public string|int|float|null $badge = null,
        public string|array|null $badgeColor = null,
        public string|Htmlable|null $badgeTooltip = null,
        public ?bool $initiallyVisible = null,
    ) {}
}
