<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\CoverCard;

use Closure;
use Filament\Actions\Action;
use Illuminate\Support\Str;

trait HasCoverAction
{
    public function footerAction(Action|Closure|null $action): static
    {
        if ($action instanceof Closure) {
            $action = Action::make('footer')
                ->label(fn () => __('filament-flex-fields::default.cover_card.action'))
                ->action($action);
        }

        // Replace only this card's primary CTA; keep separately registered actions.
        foreach ($this->actions as $key => $registeredAction) {
            if ($registeredAction === $this->action) {
                unset($this->actions[$key]);
            }
        }
        $this->cachedActions = null;
        $this->action($action);

        if ($action !== null) {
            $this->assignActionKey($action);
        }

        return $this;
    }

    protected function assignActionKey(Action $action): void
    {
        if ($this->hasStatePath() || filled($this->evaluate($this->key))) {
            return;
        }

        $this->key(Str::kebab($action->getName()));
    }

    public function getFooterAction(): ?Action
    {
        return $this->getAction();
    }
}
