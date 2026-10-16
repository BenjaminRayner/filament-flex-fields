<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard\HasGroupAppearance;
use Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation\GroupRenderState;
use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Concerns\HasDescription;
use Filament\Schemas\Components\Concerns\HasHeading;

class ItemCardGroup extends Component
{
    use HasDescription;
    use HasGroupAppearance;
    use HasHeading;

    protected string $view = 'filament-flex-fields::schemas.components.item-card-group';

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

        $this->key('item-card-group', isInheritable: false);
        $this->gap(false);
        $this->columns(1);
    }

    public function getItemCardGroupRenderState(): GroupRenderState
    {
        return GroupRenderState::from($this);
    }
}
