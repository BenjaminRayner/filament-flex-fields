<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SocialLinks\ConfiguresSocialLinks;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SocialLinks\PresentsSocialLinks;
use Bjanczak\FilamentFlexFields\StateCasts\SocialLinksStateCast;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialLinksNormalizer;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\Validation\SocialLinksRule;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class SocialLinksField extends Field
{
    use CanBeReadOnly;
    use ConfiguresSocialLinks;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use PresentsSocialLinks;

    protected string $view = 'filament-flex-fields::forms.components.social-links-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (SocialLinksField $component): SocialLinksRule => new SocialLinksRule(
            $component->getPlatformDefinitionMap(), $component->getMaxLinks(), $component->isRequired(),
        ));
        $this->dehydrateStateUsing(fn (mixed $state): mixed => SocialLinksNormalizer::isValidShape($state)
            ? SocialLinksNormalizer::dehydrate(SocialLinksNormalizer::normalize($state)) : $state);
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new SocialLinksStateCast];
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }
}
