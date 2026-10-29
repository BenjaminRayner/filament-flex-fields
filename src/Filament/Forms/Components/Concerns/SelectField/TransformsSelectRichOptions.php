<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\HtmlSanitizer;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionTree;
use Bjanczak\FilamentFlexFields\Support\Select\RichOptionJsTransformer;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

/**
 * @phpstan-type NormalizedSelectOption array{
 *     value: string|int,
 *     label: string,
 *     description: ?string,
 *     icon: string|BackedEnum|Htmlable|null,
 *     image: ?string,
 *     badge: ?string,
 *     badge_color: ?string,
 *     chip_label: ?string,
 *     disabled: bool,
 *     isDisabled: bool,
 * }
 *
 * @mixin SelectField
 */
trait TransformsSelectRichOptions
{
    protected ?RichOptionJsTransformer $richOptionJsTransformer = null;

    protected ?HtmlSanitizer $htmlSanitizer = null;

    protected ?bool $transformingRichOptionHtml = null;

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public function transformRichOptionsForJs(array $options): array
    {
        $previous = $this->transformingRichOptionHtml;
        $layout = $this->getOptionLayout();
        $this->transformingRichOptionHtml = $this->usesRichOptions === null
            && ! $this->hasOptionView() && $layout !== 'grid' && ! $this->isHtmlAllowed()
                ? $this->optionsContainRichShape($options) : null;

        try {
            // Exact stock, text-only options need neither rich normalization nor
            // two identical label renders. Keep native disabled-option callbacks
            // and the same bounded, shape-preserving traversal for every leaf.
            if ($this::class === SelectField::class && $this->transformingRichOptionHtml === false) {
                return (new OptionTree)->map($options, fn (array $label): bool => $this->isOptionGroupArray($label),
                    function (string|int $value, mixed $label): array {
                        if (! is_string($label)) {
                            throw new \InvalidArgumentException('Select options must contain strings, rich records or groups.');
                        }

                        return ['label' => $label, 'value' => (string) $value,
                            'isDisabled' => $this->isOptionDisabled($value, $label)];
                    });
            }

            return $this->getRichOptionJsTransformer()->transform(
                $options,
                fn (string|int $value, array|string $label): array => $this->normalizeOption($value, $label),
                fn (array $option, bool $compact = false): string => $this->formatOptionLabelForJs($option, $compact),
                fn (array $option): bool => $this->isOptionGroupArray($option),
                fn (array $option): bool => $this->isRichOptionArray($option),
                fn (?string $html): ?string => $this->shouldSanitizeTransformerOutput($html),
                $layout,
            );
        } finally {
            // Nested transformations and failed render callbacks restore their caller's scope.
            $this->transformingRichOptionHtml = $previous;
        }
    }

    protected function shouldSanitizeTransformerOutput(?string $html): ?string
    {
        return $this->sanitizeUserProvidedHtml($html);
    }

    protected function getRichOptionJsTransformer(): RichOptionJsTransformer
    {
        return $this->richOptionJsTransformer ??= new RichOptionJsTransformer($this->getHtmlSanitizer());
    }

    protected function getHtmlSanitizer(): HtmlSanitizer
    {
        return $this->htmlSanitizer ??= app(HtmlSanitizer::class);
    }

    protected function sanitizeUserProvidedHtml(?string $html): ?string
    {
        if ($html === null || ! $this->isHtmlAllowed()) {
            return $html;
        }

        return $this->getHtmlSanitizer()->sanitize($html);
    }

    protected function sanitizeRichHtml(?string $html): ?string
    {
        if ($html === null || ! $this->shouldSanitizeRichHtml()) {
            return $html;
        }

        return $this->getHtmlSanitizer()->sanitize($html);
    }

    protected function shouldSanitizeRichHtml(): bool
    {
        return $this->isHtmlAllowed();
    }

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    protected function transformRichOptionsForJsLegacy(array $options): array
    {
        return $this->transformRichOptionsForJs($options);
    }

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     */
    protected function optionsContainRichShape(array $options): bool
    {
        foreach ((new OptionTree)->leaves($options, fn (array $label): bool => $this->isOptionGroupArray($label)) as [, $label]) {
            if (is_array($label) && $this->isRichOptionArray($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function isOptionGroupArray(array $options): bool
    {
        // A rich record has a label; every other options array is a group,
        // including an empty group or a group containing further groups.
        return ! $this->isRichOptionArray($options);
    }

    /**
     * @param  array<string, mixed>  $option
     */
    protected function isRichOptionArray(array $option): bool
    {
        return array_key_exists('label', $option);
    }

    /**
     * @param  array<string, mixed>  $label
     * @return NormalizedSelectOption
     */
    protected function normalizeOption(string|int $value, array|string $label): array
    {
        if (is_string($label)) {
            $disabled = $this->isOptionDisabled($value, $label);

            return [
                'value' => $value,
                'label' => $label,
                'description' => null,
                'icon' => null,
                'image' => null,
                'badge' => null,
                'badge_color' => null,
                'chip_label' => null,
                'disabled' => $disabled,
                'isDisabled' => $disabled,
            ];
        }

        $text = OptionText::required($label['label'] ?? $value, 'label');
        $disabled = (bool) ($label['disabled'] ?? false)
            || (bool) ($label['isDisabled'] ?? false)
            || $this->isOptionDisabled($value, $text);

        return [
            'value' => $value,
            'label' => $text,
            'description' => OptionText::optional($label['description'] ?? $label['desc'] ?? null, 'description'),
            'icon' => $label['icon'] ?? null,
            'image' => OptionText::optional($label['image'] ?? null, 'image'),
            'badge' => OptionText::optional($label['badge'] ?? null, 'badge'),
            'badge_color' => OptionText::optional($label['badge_color'] ?? null, 'badge_color'),
            'chip_label' => OptionText::optional($label['chip_label'] ?? $label['chipLabel'] ?? null, 'chip_label'),
            'disabled' => $disabled,
            'isDisabled' => $disabled,
        ];
    }

    /**
     * @param  NormalizedSelectOption  $option
     */
    protected function formatOptionLabelForJs(array $option, bool $compact = false): string
    {
        if ($this->hasOptionView()) {
            if ($compact && filled($option['chip_label'] ?? null)) {
                $chipOption = $option;
                $chipOption['label'] = (string) $option['chip_label'];

                return $this->renderOptionView($chipOption, layout: 'chip');
            }

            return $this->renderOptionView($option, layout: $this->resolveOptionViewLayout($compact));
        }

        if ($compact && filled($option['chip_label'] ?? null)) {
            $chipLabel = $option['chip_label'];

            return $this->isHtmlAllowed()
                ? (string) ($this->sanitizeUserProvidedHtml($chipLabel) ?? '')
                : $chipLabel;
        }

        if (! $this->usesRichOptionHtml()) {
            $label = $option['label'];

            return $this->isHtmlAllowed()
                ? (string) ($this->sanitizeUserProvidedHtml($label) ?? '')
                : $label;
        }

        if ($compact) {
            return $this->renderRichOptionLabel(
                $option,
                layout: $this->getOptionLayout() === 'list' && $this->shouldUseRichListTriggerDisplay() ? 'list' : 'trigger',
            );
        }

        if ($this->getOptionLayout() === 'grid') {
            return $this->renderRichOptionLabel($option, layout: 'grid');
        }

        if (! filled($option['description']) && ! filled($option['icon']) && ! filled($option['image']) && ! filled($option['badge'])) {
            $label = $option['label'];

            return $this->isHtmlAllowed()
                ? (string) ($this->sanitizeRichHtml($label) ?? '')
                : $label;
        }

        return $this->renderRichOptionLabel($option, layout: 'list');
    }

    /**
     * @param  NormalizedSelectOption  $option
     */
    protected function renderRichOptionLabel(array $option, string $layout = 'list'): string
    {
        if ($this->hasOptionView()) {
            return $this->renderOptionView($option, $layout);
        }

        /** @var View $view */
        $view = view('filament-flex-fields::forms.components.partials.select-rich-option', [
            'label' => $option['label'],
            'labelHtml' => $this->isHtmlAllowed() ? $this->sanitizeUserProvidedHtml($option['label']) : null,
            'description' => $layout === 'list' ? $option['description'] : null,
            'descriptionPosition' => $this->getOptionDescriptionPosition(),
            'icon' => $option['icon'],
            'image' => $option['image'],
            'badge' => $layout === 'list' ? $option['badge'] : null,
            'badgeColor' => $option['badge_color'] ?? 'primary',
            'layout' => $layout,
            'iconSize' => match ($this->getSize()) {
                'sm' => IconSize::Small,
                'lg' => IconSize::Large,
                default => IconSize::Medium,
            },
        ]);

        return $view->render();
    }

    /**
     * @return array<string, string>
     */
    public function getTriggerOptionLabelsForJs(): array
    {
        $labels = [];

        $tree = new OptionTree;
        foreach ($tree->leaves($this->getOptions(), fn (array $label): bool => $this->isOptionGroupArray($label)) as [$value, $label]) {
            $labels[(string) $value] = $this->formatOptionLabelForJs(
                $this->normalizeOption($value, $label),
                compact: ! $this->shouldUseRichListTriggerDisplay(),
            );
        }

        return $labels;
    }
}
