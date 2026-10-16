<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard\HasCoverAction;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard\HasCoverBackground;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard\HasCoverContent;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard\HasCoverLayout;
use Bjanczak\FilamentFlexFields\Support\CoverCard\Presentation\CoverCardRenderState;
use Filament\Schemas\Components\Component;

class CoverCard extends Component
{
    use HasCoverAction;
    use HasCoverBackground;
    use HasCoverContent;
    use HasCoverLayout;

    protected string $view = 'filament-flex-fields::schemas.components.cover-card';

    public static function make(): static
    {
        $card = app(static::class);
        $card->configure();

        return $card;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->gap(false);
        $this->columns(1);
    }

    public function getCoverCardRenderState(): CoverCardRenderState
    {
        return CoverCardRenderState::from($this);
    }
}
