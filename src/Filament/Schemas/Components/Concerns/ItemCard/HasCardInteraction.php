<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Schemas\Components\Concerns\ItemCard;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation\PlainText;
use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

trait HasCardInteraction
{
    protected bool|Closure $hasChevron = false;

    protected bool|Closure|null $isPressable = null;

    public function chevron(bool|Closure $condition = true): static
    {
        $this->hasChevron = $condition;

        return $this;
    }

    public function pressable(bool|Closure $condition = true): static
    {
        $this->isPressable = $condition;

        return $this;
    }

    public function pressableAction(Action|Closure|null $action): static
    {
        if ($action instanceof Closure) {
            $name = Str::camel(Str::slug(PlainText::from($this->getHeading()), '_')) ?: 'press';

            $action = Action::make($name)
                ->action($action);
        }

        $this->pressable();

        return $this->action($action);
    }

    public function action(?Action $action): static
    {
        if ($action !== null) {
            $this->assignActionKey($action);
        }

        foreach ($this->actions as $key => $registeredAction) {
            if ($registeredAction === $this->action) {
                unset($this->actions[$key]);
            }
        }
        $this->cachedActions = null;

        return parent::action($action);
    }

    protected function assignActionKey(Action $action): void
    {
        if ($this->hasStatePath() || filled($this->evaluate($this->key))) {
            return;
        }

        $this->key(Str::kebab($action->getName()));
    }

    public function hasChevron(): bool
    {
        return (bool) $this->evaluate($this->hasChevron);
    }

    public function getChevronIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveConfiguredIcon('item_card_chevron_icon', FlexIcon::ChevronRight);
    }

    public function isPressable(): bool
    {
        return $this->resolvePressable($this->getUrl());
    }

    public function resolvePressable(?string $url, ?bool $hasChildControls = null): bool
    {
        // A row containing fields/actions must not become a nested button/link.
        if ($hasChildControls ?? $this->hasInteractiveAction()) {
            return false;
        }
        if ($this->isPressable !== null) {
            return (bool) $this->evaluate($this->isPressable);
        }

        $parent = $this->getParentItemCardGroup();

        if ($parent?->areRowsPressable()) {
            return true;
        }

        return $this->hasChevron() || $this->getPressableAction() !== null || $url !== null;
    }

    public function getPressableAction(): ?Action
    {
        if ($this->action === null) {
            return null;
        }

        return $this->getAction($this->action->getName());
    }

    public function hasInteractiveAction(): bool
    {
        $components = $this->getDefaultChildComponents();

        if ($components instanceof Schema) {
            return $components->getComponents() !== [];
        }

        return is_array($components) && $components !== [];
    }
}
