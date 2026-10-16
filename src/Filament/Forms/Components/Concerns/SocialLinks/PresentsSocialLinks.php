<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SocialLinks;

use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialPlatformDefinition;

trait PresentsSocialLinks
{
    /**
     * @return list<array{value: string, label: string, placeholder: string, brand: string, hosts: list<string>}>
     */
    public function getPlatformDefinitions(): array
    {
        return array_map(
            fn (SocialPlatformDefinition $definition): array => $definition->toAlpineArray(),
            array_values($this->getPlatformDefinitionMap()),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getBrandIconSvgs(): array
    {
        return $this->preparePlatformIcons($this->getPlatformDefinitionMap());
    }

    /** @param array<string, SocialPlatformDefinition> $definitions
     * @return array<string, string> */
    private function preparePlatformIcons(array $definitions): array
    {
        $icons = [];

        foreach ($definitions as $value => $definition) {
            if ($definition->iconSvg !== null) {
                $icons[$value] = $definition->iconSvg;

                continue;
            }

            $icons[$value] = view('filament-flex-fields::forms.components.social-links.icon', [
                'icon' => $definition->iconName(),
            ])->render();
        }

        return $icons;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlpineConfiguration(): array
    {
        $definitions = $this->getPlatformDefinitionMap();

        return [
            'platforms' => array_map(fn (SocialPlatformDefinition $definition): array => $definition->toAlpineArray(), array_values($definitions)),
            'brandIcons' => $this->preparePlatformIcons($definitions),
            'maxLinks' => $this->getMaxLinks(),
            'reorderable' => $this->isReorderable(),
            'autoFormatUrls' => $this->shouldAutoFormatUrls(),
            'labels' => [
                'add' => __('filament-flex-fields::default.social_links.add'),
                'addPlatform' => __('filament-flex-fields::default.social_links.add_platform'),
                'choosePlatform' => __('filament-flex-fields::default.social_links.choose_platform'),
                'remove' => __('filament-flex-fields::default.social_links.remove'),
                'moveUp' => __('filament-flex-fields::default.social_links.move_up'),
                'moveDown' => __('filament-flex-fields::default.social_links.move_down'),
                'empty' => __('filament-flex-fields::default.social_links.empty'),
                'maxReached' => __('filament-flex-fields::default.social_links.max_reached'),
                'url' => __('filament-flex-fields::default.social_links.url'),
                'required' => __('filament-flex-fields::default.social_links.validation.required'),
                'unknownPlatform' => __('filament-flex-fields::default.social_links.validation.unknown_platform'),
                'platformNotAllowed' => __('filament-flex-fields::default.social_links.validation.platform_not_allowed'),
                'invalidUrl' => __('filament-flex-fields::default.social_links.validation.invalid_url'),
                'platformMismatch' => __('filament-flex-fields::default.social_links.validation.platform_mismatch', ['platform' => ':platform']),
            ],
            'icons' => [
                'remove' => FlexIcon::TrashBin,
                'chevron' => FlexIcon::ChevronDown,
                'chevronUp' => FlexIcon::ChevronUp,
                'chevronDown' => FlexIcon::ChevronDown,
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getWrapperClasses(): array
    {
        return [
            'fff-social-links-field' => true,
            'fff-flex-text-input' => true,
            'fff-social-links-field--'.$this->getSize() => true,
            'fff-flex-text-input--'.$this->getSize() => true,
            'fff-social-links-field--'.$this->getVariant() => true,
            'fff-flex-text-input--'.($this->getVariant() === 'ghost' ? 'flat' : $this->getVariant()) => true,
            'fff-rounding-'.$this->getRounding() => true,
            'fff-social-links-field--reorderable' => $this->isReorderable(),
        ];
    }
}
