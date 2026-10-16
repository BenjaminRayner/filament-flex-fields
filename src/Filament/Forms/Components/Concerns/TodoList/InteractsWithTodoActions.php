<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Actions\DeletesTodoItems;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Actions\EditsTodoItems;
use Bjanczak\FilamentFlexFields\Support\TodoList\TodoStateCodec;

trait InteractsWithTodoActions
{
    use DeletesTodoItems;
    use EditsTodoItems;

    /**
     * Livewire: create via Alpine → callSchemaComponentMethod(..., 'createTodoItem', ...).
     *
     * @param  array{item?: array<string, mixed>}  $arguments
     * @return array<string, mixed>|null
     */
    public function createTodoItem(array $arguments): ?array
    {
        $item = $arguments['item'] ?? null;

        if (! $this->canCreate() || $this->isDisabled() || ! is_array($item) || ! isset($item['label'])
            || ! is_string($item['label']) || trim($item['label']) === '' || mb_strlen($item['label']) > 500) {
            return null;
        }

        $current = $this->currentTodoActionItems();
        if ($current === null) {
            return null;
        }
        $maxItems = $this->getMaxItems();

        if ($maxItems !== null && count($current) >= $maxItems) {
            return null;
        }

        $description = $this->canCreateWithDescription() ? ($item['description'] ?? null) : null;

        if ($description !== null && (! is_string($description) || mb_strlen($description) > 2000)) {
            return null;
        }

        $item = TodoStateCodec::seed([self::makeNewItemId() => [
            'label' => trim($item['label']),
            'description' => $description,
            'created' => true,
        ]])[0];

        if (! $this->hasCreateUsing()) {
            return $item;
        }

        $result = $this->evaluate($this->createUsing, [
            'item' => $item,
            'component' => $this,
        ]);

        if (! is_array($result)) {
            return null;
        }
        try {
            return TodoStateCodec::normalize([[...$item, ...$result, 'created' => true]], [])[0];
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Livewire: delete via Alpine → callSchemaComponentMethod(..., 'deleteTodoItem', ...).
     *
     * @param  array{id?: mixed, item?: array<string, mixed>}  $arguments
     */
    public function deleteTodoItem(array $arguments): bool
    {
        return ! $this->requiresDeleteConfirmation() && $this->authorizeTodoDeletion($arguments);
    }

    /** @param array{id?: mixed, item?: array<string, mixed>} $arguments */
    protected function authorizeTodoDeletion(array $arguments): bool
    {
        $id = $arguments['id'] ?? null;
        $item = $this->findItemInState($id ?? ($arguments['item']['id'] ?? null));

        if (! is_array($item) || ! isset($item['id'])) {
            return false;
        }

        if (! $this->itemIsDeletable($item)) {
            return false;
        }

        $children = [];

        foreach ($item['children'] ?? [] as $child) {
            if (is_array($child) && isset($child['id'])) {
                $children[] = $child;
            }
        }

        $deletedIds = array_values(array_unique([
            (string) $item['id'],
            ...array_map(static fn (array $child): string => (string) $child['id'], $children),
        ]));

        if (! $this->hasDeleteUsing()) {
            return true;
        }

        $result = $this->evaluate($this->deleteUsing, [
            'item' => $item,
            'children' => $children,
            'deletedIds' => $deletedIds,
            'id' => (string) $item['id'],
            'component' => $this,
        ]);

        return $result !== false;
    }

    /**
     * Livewire: reorder via Alpine → callSchemaComponentMethod(..., 'reorderTodoItems', ...).
     *
     * @param  array{items?: list<array<string, mixed>>}  $arguments
     */
    public function reorderTodoItems(array $arguments): bool
    {
        $items = $arguments['items'] ?? null;

        if (! is_array($items)) {
            return false;
        }

        if (! $this->isReorderable() || $this->isDisabled()) {
            return false;
        }

        $current = $this->currentTodoActionItems();
        if ($current === null) {
            return false;
        }
        $byId = [];

        foreach ($current as $row) {
            $byId[$row['id']] = $row;
        }

        $ordered = [];

        foreach ($items as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;

            if (! is_string($id) || ! isset($byId[$id])) {
                return false;
            }

            $ordered[] = $byId[$id];
            unset($byId[$id]);
        }

        if ($byId !== []) {
            return false;
        }

        foreach ($current as $position => $row) {
            if (($row['disabled'] ?? false) && $ordered[$position]['id'] !== $row['id']) {
                return false;
            }
        }
        $items = $ordered;

        if (! $this->hasReorderUsing()) {
            return true;
        }

        $result = $this->evaluate($this->reorderUsing, [
            'items' => $items,
            'component' => $this,
        ]);

        return $result !== false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function itemIsDeletable(array $item): bool
    {
        if (! $this->canDelete() || $this->isDisabled()) {
            return false;
        }

        if ((bool) ($item['disabled'] ?? $item['locked'] ?? false)) {
            return false;
        }

        return match ($this->getDeletableMode()) {
            'all' => true,
            'created' => (bool) ($item['created'] ?? false),
            default => false,
        };
    }

    /**
     * Livewire: toggle via Alpine → callSchemaComponentMethod(..., 'todoItemsToggled', ...).
     *
     * @param  array{changed?: list<array<string, mixed>>, items?: list<array<string, mixed>>}  $arguments
     */
    public function todoItemsToggled(array $arguments): void
    {
        if (! $this->hasAfterToggled() || $this->isDisabled()) {
            return;
        }

        $requested = is_array($arguments['changed'] ?? null) ? $arguments['changed'] : [];
        $items = $this->currentTodoActionItems();
        if ($items === null) {
            return;
        }
        $byId = [];

        foreach ($items as $item) {
            $byId[$item['id']] = $item;

            foreach ($item['children'] ?? [] as $child) {
                $byId[$child['id']] = $child;
            }
        }

        $changed = [];

        foreach ($requested as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;

            if (! is_string($id) || ! isset($byId[$id]) || ($byId[$id]['disabled'] ?? false)) {
                return;
            }

            $changed[$id] = $byId[$id];
        }

        $changed = array_values($changed);

        $this->evaluate($this->afterToggled, [
            'changed' => $changed,
            'items' => $items,
            'component' => $this,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function itemIsEditable(array $item): bool
    {
        if (! $this->canEdit() || $this->isDisabled()) {
            return false;
        }

        if ((bool) ($item['disabled'] ?? $item['locked'] ?? false)) {
            return false;
        }

        return match ($this->getEditableMode()) {
            'all' => true,
            'created' => (bool) ($item['created'] ?? false),
            default => false,
        };
    }
}
