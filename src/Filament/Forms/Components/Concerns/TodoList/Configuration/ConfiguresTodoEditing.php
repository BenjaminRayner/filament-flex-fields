<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;

trait ConfiguresTodoEditing
{
    protected bool|Closure $allowCreate = false;

    protected bool|Closure $createWithDescription = false;

    protected bool|Closure $allowDelete = false;

    protected bool|Closure $deleteConfirmation = false;

    protected string|Closure $deletable = 'created';

    protected bool|Closure $allowEdit = false;

    protected string|Closure $editable = 'all';

    protected bool|Closure|null $editWithDescription = null;

    protected string|BackedEnum|Htmlable|Closure|null $editIcon = null;

    protected bool|Closure $reorderable = false;

    protected int|Closure $reorderAnimationDuration = 200;

    protected ?Closure $createUsing = null;

    protected ?Closure $editUsing = null;

    protected ?Closure $deleteUsing = null;

    protected ?Closure $reorderUsing = null;

    protected ?Closure $afterToggled = null;

    protected string|Closure|null $createLabel = null;

    protected string|Closure|null $createPlaceholder = null;

    protected string|Closure|null $createDescriptionPlaceholder = null;

    public function allowCreate(bool|Closure $allow = true): static
    {
        $this->allowCreate = $allow;

        return $this;
    }

    public function createWithDescription(bool|Closure $allow = true): static
    {
        $this->createWithDescription = $allow;

        return $this;
    }

    public function allowDelete(bool|Closure $allow = true): static
    {
        $this->allowDelete = $allow;

        return $this;
    }

    public function deleteConfirmation(bool|Closure $required = true): static
    {
        $this->deleteConfirmation = $required;

        return $this;
    }

    public function requiresDeleteConfirmation(): bool
    {
        return (bool) $this->evaluate($this->deleteConfirmation);
    }

    public function deletable(string|Closure $mode): static
    {
        $this->deletable = $mode;

        return $this;
    }

    public function allowEdit(bool|Closure $allow = true): static
    {
        $this->allowEdit = $allow;

        return $this;
    }

    public function editable(string|Closure $mode): static
    {
        $this->editable = $mode;

        return $this;
    }

    public function editWithDescription(bool|Closure|null $allow = true): static
    {
        $this->editWithDescription = $allow;

        return $this;
    }

    public function editIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->editIcon = $icon;

        return $this;
    }

    public function reorderable(bool|Closure $enabled = true): static
    {
        $this->reorderable = $enabled;

        return $this;
    }

    public function reorderAnimationDuration(int|Closure $ms): static
    {
        $this->reorderAnimationDuration = $ms;

        return $this;
    }

    public function createUsing(?Closure $callback): static
    {
        $this->createUsing = $callback;

        return $this;
    }

    public function editUsing(?Closure $callback): static
    {
        $this->editUsing = $callback;

        return $this;
    }

    public function deleteUsing(?Closure $callback): static
    {
        $this->deleteUsing = $callback;

        return $this;
    }

    public function reorderUsing(?Closure $callback): static
    {
        $this->reorderUsing = $callback;

        return $this;
    }

    public function afterToggled(?Closure $callback): static
    {
        $this->afterToggled = $callback;

        return $this;
    }

    public function createLabel(string|Closure|null $label): static
    {
        $this->createLabel = $label;

        return $this;
    }

    public function createPlaceholder(string|Closure|null $placeholder): static
    {
        $this->createPlaceholder = $placeholder;

        return $this;
    }

    public function createDescriptionPlaceholder(string|Closure|null $placeholder): static
    {
        $this->createDescriptionPlaceholder = $placeholder;

        return $this;
    }

    public function canCreate(): bool
    {
        return (bool) $this->evaluate($this->allowCreate);
    }

    public function canCreateWithDescription(): bool
    {
        return $this->canCreate() && (bool) $this->evaluate($this->createWithDescription);
    }

    public function canDelete(): bool
    {
        return (bool) $this->evaluate($this->allowDelete);
    }

    public function getDeletableMode(): string
    {
        $mode = (string) $this->evaluate($this->deletable);

        if (! in_array($mode, ['created', 'all', 'none'], true)) {
            throw new InvalidArgumentException('TodoListField deletable mode must be created, all, or none.');
        }

        return $mode;
    }

    public function canEdit(): bool
    {
        return (bool) $this->evaluate($this->allowEdit);
    }

    public function getEditableMode(): string
    {
        $mode = (string) $this->evaluate($this->editable);

        if (! in_array($mode, ['created', 'all', 'none'], true)) {
            throw new InvalidArgumentException('TodoListField editable mode must be created, all, or none.');
        }

        return $mode;
    }

    public function canEditWithDescription(): bool
    {
        if ($this->editWithDescription === null) {
            return (bool) $this->evaluate($this->createWithDescription);
        }

        return (bool) $this->evaluate($this->editWithDescription);
    }

    public function hasEditUsing(): bool
    {
        return $this->editUsing instanceof Closure;
    }

    public function hasDeleteUsing(): bool
    {
        return $this->deleteUsing instanceof Closure;
    }

    public function hasReorderUsing(): bool
    {
        return $this->reorderUsing instanceof Closure;
    }

    public function isReorderable(): bool
    {
        if (! (bool) $this->evaluate($this->reorderable)) {
            return false;
        }

        if ($this->isVirtualizing() || $this->isInfiniteScroll() || $this->isPaginated()) {
            return false;
        }

        return true;
    }

    public function getReorderAnimationDuration(): int
    {
        return max(0, (int) $this->evaluate($this->reorderAnimationDuration));
    }

    public function hasCreateUsing(): bool
    {
        return $this->createUsing instanceof Closure;
    }

    public function hasAfterToggled(): bool
    {
        return $this->afterToggled instanceof Closure;
    }

    public function getCreateLabel(): string
    {
        return (string) ($this->evaluate($this->createLabel) ?: __('filament-flex-fields::default.todo_list.create_option'));
    }

    public function getCreatePlaceholder(): string
    {
        return (string) ($this->evaluate($this->createPlaceholder) ?: __('filament-flex-fields::default.todo_list.create_placeholder'));
    }

    public function getCreateDescriptionPlaceholder(): string
    {
        return (string) ($this->evaluate($this->createDescriptionPlaceholder) ?: __('filament-flex-fields::default.todo_list.create_description_placeholder'));
    }
}
