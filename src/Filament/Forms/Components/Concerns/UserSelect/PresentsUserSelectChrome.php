<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Translations;

/**
 * @mixin UserSelect
 *
 * @phpstan-import-type NormalizedSelectOption from InteractsWithUserSelectPresentation
 * @phpstan-import-type NormalizedUserSelectOption from InteractsWithUserSelectPresentation
 */
trait PresentsUserSelectChrome
{
    /**
     * @return array<int|string, bool|string>
     */
    public function getWrapperClasses(): array
    {
        $classes = parent::getWrapperClasses();

        $classes['fff-user-select'] = true;

        if ($this->isMultiple()) {
            $classes['fff-user-select--multiple'] = true;
            $classes['fff-select-field--rich-list-trigger'] = true;
        }

        if (! $this->isMultiple()) {
            $classes['fff-user-select--single'] = true;
            $classes['fff-select-field--rich-list-trigger'] = true;
        }

        return $classes;
    }

    public function shouldUseRichListTriggerDisplay(): bool
    {
        return ! $this->isMultiple();
    }

    public function shouldRenderAvatarStackTrigger(): bool
    {
        return false;
    }

    public function shouldRenderMultipleUserTags(): bool
    {
        return $this->isMultiple();
    }

    /**
     * @return array{
     *     tryDifferentSearch: string,
     *     minSearchLength: string,
     *     noUsersAvailable: string,
     * }
     */
    public function getUserSelectEmptyStateHintsForJs(): array
    {
        $minSearchLength = (int) $this->getMinSearchLength();

        return [
            'tryDifferentSearch' => Translations::get('filament-flex-fields::default.user_select_field.empty_hint.try_different_search'),
            'minSearchLength' => Translations::get('filament-flex-fields::default.user_select_field.empty_hint.min_search_length', [
                'count' => $minSearchLength,
            ]),
            'noUsersAvailable' => Translations::get('filament-flex-fields::default.user_select_field.empty_hint.no_users_available'),
        ];
    }
}
