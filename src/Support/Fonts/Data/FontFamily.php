<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Fonts\Data;

use Bjanczak\FilamentFlexFields\Support\Fonts\FontStylesheet;

/** Immutable catalog record; presentation URLs never become persisted field state. */
final readonly class FontFamily
{
    /** @param list<string> $variants
     * @param  list<string>  $subsets
     * @param  array<string, array{min: int|float, max: int|float, default: int|float}>  $axes
     */
    public function __construct(
        public string $id, public string $family, public string $category,
        public array $variants, public array $subsets, public bool $variable,
        public int $rank, public string $version, public ?string $file = null, public array $axes = [],
    ) {}

    /** @return array{weight: int, style: string} */
    public function variant(int $weight, string $style): array
    {
        $candidates = array_values(array_filter($this->variants, static fn (string $variant): bool => str_ends_with($variant, 'i') === ($style === 'italic')));
        $candidates = $candidates === [] ? $this->variants : $candidates;
        usort($candidates, static fn (string $a, string $b): int => abs((int) $a - $weight) <=> abs((int) $b - $weight));
        $selected = $candidates[0];

        return ['weight' => isset($this->axes['wght']) ? (int) max($this->axes['wght']['min'], min($this->axes['wght']['max'], $weight)) : (int) $selected, 'style' => str_ends_with($selected, 'i') ? 'italic' : 'normal'];
    }

    /** @param list<int> $weights
     * @param  list<string>  $styles
     */
    public function stylesheetUrl(array $weights = [400, 500, 600, 700], array $styles = ['normal'], string $display = 'swap'): ?string
    {
        return FontStylesheet::google($this, $weights, $styles, $display);
    }

    /** @param array{int, int} $weightRange
     * @param  list<string>  $styles
     */
    public function variableStylesheetUrl(array $weightRange = [100, 900], array $styles = ['normal'], string $display = 'swap'): string
    {
        return FontStylesheet::variableGoogle($this, $weightRange, $styles, $display);
    }

    public function fontFaceCss(string $display = 'swap'): string
    {
        return FontStylesheet::local($this, $display);
    }

    public function cssFamily(): string
    {
        return FontStylesheet::quote($this->family);
    }
}
