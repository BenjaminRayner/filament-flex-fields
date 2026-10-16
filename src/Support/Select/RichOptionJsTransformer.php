<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select;

use Bjanczak\FilamentFlexFields\Support\HtmlSanitizer;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionTree;
use Closure;
use InvalidArgumentException;

class RichOptionJsTransformer
{
    public function __construct(
        protected HtmlSanitizer $htmlSanitizer,
    ) {}

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     * @param  Closure(string|int, array<string, mixed>|string): array{value: int|string, label: string, description: ?string, icon: mixed, image: ?string, badge: ?string, badge_color: ?string, chip_label: ?string, disabled: bool, isDisabled: bool}  $normalizeOption
     * @param  Closure(array{value: int|string, label: string, description: ?string, icon: mixed, image: ?string, badge: ?string, badge_color: ?string, chip_label: ?string, disabled: bool, isDisabled: bool}, bool=): string  $formatOptionLabelForJs
     * @param  Closure(array<string, mixed>): bool  $isOptionGroupArray
     * @param  Closure(array<string, mixed>): bool  $isRichOptionArray
     * @param  Closure(string|null): ?string  $sanitizeHtml
     * @return list<array<string, mixed>>
     */
    public function transform(
        array $options,
        Closure $normalizeOption,
        Closure $formatOptionLabelForJs,
        Closure $isOptionGroupArray,
        Closure $isRichOptionArray,
        Closure $sanitizeHtml,
        string $optionLayout = 'list',
        int $depth = 0,
    ): array {
        if ($depth > 32) {
            throw new InvalidArgumentException('Select option groups exceed the maximum depth of 32.');
        }

        return (new OptionTree)->map($options, $isOptionGroupArray, function (string|int $value, mixed $label) use (
            $normalizeOption, $formatOptionLabelForJs, $sanitizeHtml, $optionLayout,
        ): array {
            if (! is_string($label) && ! is_array($label)) {
                throw new InvalidArgumentException('Select options must contain strings, rich records or groups.');
            }
            $normalized = $normalizeOption($value, $label);
            $dropdownLabel = $sanitizeHtml($formatOptionLabelForJs($normalized, false));
            $triggerLabel = $sanitizeHtml($formatOptionLabelForJs($normalized, true));

            $option = [
                'label' => $dropdownLabel,
                'value' => (string) $value,
                'isDisabled' => $normalized['disabled'],
            ];

            if (filled($normalized['chip_label'] ?? null) || $triggerLabel !== $dropdownLabel) {
                $option['triggerLabel'] = $triggerLabel;
            }

            if ($this->isClientRenderable($normalized, $optionLayout)) {
                $option['fffClientRender'] = true;

                if (filled($normalized['description'] ?? null)) {
                    $option['description'] = $normalized['description'];
                }

                if (filled($normalized['image'] ?? null)) {
                    $option['image'] = $normalized['image'];
                }

                if (($normalized['verified'] ?? false) === true) {
                    $option['verified'] = true;
                }
            }

            return $option;
        }, depth: $depth);
    }

    /**
     * @param  array{
     *     value: string|int,
     *     label: string,
     *     description: ?string,
     *     icon: mixed,
     *     image: ?string,
     *     badge: ?string,
     *     badge_color: ?string,
     *     disabled: bool,
     *     verified?: bool,
     * }  $normalized
     */
    public function isClientRenderable(array $normalized, string $optionLayout = 'list'): bool
    {
        if ($optionLayout === 'grid') {
            return false;
        }

        if (filled($normalized['icon'] ?? null) || filled($normalized['badge'] ?? null)) {
            return false;
        }

        return filled($normalized['description'] ?? null)
            || filled($normalized['image'] ?? null)
            || (($normalized['verified'] ?? false) === true);
    }

    public function sanitizeHtml(?string $html, bool $shouldSanitize): ?string
    {
        if (! $shouldSanitize || $html === null) {
            return $html;
        }

        return $this->htmlSanitizer->sanitize($html);
    }
}
