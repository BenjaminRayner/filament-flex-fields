<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

/**
 * @mixin UserSelect
 *
 * @phpstan-import-type NormalizedSelectOption from InteractsWithUserSelectPresentation
 * @phpstan-import-type NormalizedUserSelectOption from InteractsWithUserSelectPresentation
 */
trait RendersUserSelectOptions
{
    /**
     * @param  NormalizedSelectOption  $option
     */
    protected function renderRichOptionLabel(array $option, string $layout = 'list'): string
    {
        return $this->optionPresenter()->renderUserOption([
            'label' => $option['label'],
            'description' => $option['description'],
            'image' => $option['image'],
            'verified' => (bool) ($option['verified'] ?? false),
        ], layout: $layout);
    }

    /**
     * @param  NormalizedSelectOption  $option
     */
    protected function formatOptionLabelForJs(array $option, bool $compact = false): string
    {
        if ($compact && $this->isMultiple()) {
            return $this->optionPresenter()->renderUserOption([
                'label' => $option['label'],
                'description' => $option['description'],
                'image' => $option['image'],
                'verified' => (bool) ($option['verified'] ?? false),
            ], layout: 'list');
        }

        return parent::formatOptionLabelForJs($option, $compact);
    }

    /**
     * @param  array{
     *     label: string,
     *     description?: ?string,
     *     image?: ?string,
     *     verified?: bool,
     * }  $option
     */
    public function renderUserOption(array $option, string $layout = 'list'): string
    {
        return $this->optionPresenter()->renderUserOption($option, $layout);
    }

    /**
     * @param  array{
     *     label: string,
     *     description?: ?string,
     *     image?: ?string,
     *     verified?: bool,
     * }  $option
     */
    public function renderUserSelectedTag(string|int $value, array $option): string
    {
        return $this->optionPresenter()->renderUserSelectedTag($value, $option);
    }

    public function getUserSelectInitials(string $name): string
    {
        return $this->optionPresenter()->initialsForName($name);
    }
}
