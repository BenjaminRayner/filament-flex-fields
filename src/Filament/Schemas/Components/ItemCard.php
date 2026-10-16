<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Concerns\ResolvesConfiguredIcons;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard\HasCardAppearance;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard\HasCardInteraction;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard\HasCardMedia;
use Bjanczak\FilamentFlexFields\Support\ItemCard\NavigationUrl;
use Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation\CardRenderState;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\CanOpenUrl;
use Filament\Schemas\Components\Concerns\HasDescription;
use Filament\Schemas\Components\Concerns\HasHeading;

class ItemCard extends Component
{
    use CanOpenUrl { getUrl as protected getConfiguredUrl; }
    use HasCardAppearance;
    use HasCardInteraction;
    use HasCardMedia;
    use HasDescription;
    use HasHeading;
    use ResolvesConfiguredIcons;

    protected string $view = 'filament-flex-fields::schemas.components.item-card';

    final public function __construct(string|Closure|null $heading = null)
    {
        $this->heading($heading);
    }

    public static function make(string|Closure|null $heading = null): static
    {
        $static = app(static::class, ['heading' => $heading]);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->gap(false);
        $this->columns(1);
    }

    public function getItemCardRenderState(): CardRenderState
    {
        return CardRenderState::from($this);
    }

    public function getUrl(mixed $state = null): ?string
    {
        return NavigationUrl::sanitize(func_num_args() === 1
            ? $this->getConfiguredUrl($state)
            : $this->getConfiguredUrl());
    }
}
