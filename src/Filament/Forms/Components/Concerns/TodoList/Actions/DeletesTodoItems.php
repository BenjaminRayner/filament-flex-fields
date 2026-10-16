<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Actions;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Filament\Actions\Action;

trait DeletesTodoItems
{
    protected function makeDeleteTodoItemAction(): Action
    {
        return Action::make('confirmDeleteTodoItem')
            ->label(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.delete_item'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.delete_item'))
            ->modalDescription(function (array $arguments, TodoListField $component): string {
                $item = $component->findItemInState($arguments['id'] ?? null);

                $description = __('filament-flex-fields::default.todo_list.delete_confirmation', [
                    'label' => (string) ($item['label'] ?? ''),
                ]);

                return is_string($description) ? $description : '';
            })
            ->modalSubmitActionLabel(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.delete_item'))
            ->visible(function (array $arguments, TodoListField $component): bool {
                $item = $component->findItemInState($arguments['id'] ?? null);

                return $component->requiresDeleteConfirmation() && is_array($item) && $component->itemIsDeletable($item);
            })
            ->action(function (array $arguments, TodoListField $component, Action $action): void {
                $id = $arguments['id'] ?? null;
                if (! is_string($id) || ! $component->applyConfirmedTodoDeletion($id)) {
                    $action->halt();
                }
                $component->getLivewire()->dispatch($component->getDeleteSyncedEvent(), id: $id);
            });
    }

    protected function applyConfirmedTodoDeletion(string $id): bool
    {
        if (! $this->requiresDeleteConfirmation() || ! $this->authorizeTodoDeletion(['id' => $id])) {
            return false;
        }

        // Resolve again after the callback: it may have changed the current state.
        $state = $this->currentTodoActionItems();
        $item = $this->findItemInState($id);
        if ($state === null || $item === null || ! $this->itemIsDeletable($item)) {
            return false;
        }
        $this->state(array_values(array_map(static function (array $row) use ($id): array {
            $row['children'] = array_values(array_filter($row['children'] ?? [], static fn (array $child): bool => (string) $child['id'] !== $id));

            return $row;
        }, array_filter($state, static fn (array $row): bool => (string) $row['id'] !== $id))));

        return true;
    }

    public function getDeleteSyncedEvent(): string
    {
        return 'fff-todo-list-deleted-'.md5((string) $this->getLivewireKey());
    }
}
