<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Actions;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

trait EditsTodoItems
{
    /**
     * @return list<Action>
     */
    public function getDefaultActions(): array
    {
        return [
            $this->makeEditTodoItemAction(),
            $this->makeDeleteTodoItemAction(),
        ];
    }

    protected function makeEditTodoItemAction(): Action
    {
        return Action::make('editTodoItem')
            ->label(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.edit_item'))
            ->modalHeading(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.edit_modal_heading'))
            ->modalSubmitActionLabel(fn (): string => $this->todoListString('filament-flex-fields::default.todo_list.edit_save'))
            ->modalWidth(Width::Medium)
            ->fillForm(function (array $arguments, TodoListField $component): array {
                $item = $component->findItemInState($arguments['id'] ?? ($arguments['item']['id'] ?? null));

                return [
                    'label' => (string) ($item['label'] ?? ''),
                    'description' => (string) ($item['description'] ?? ''),
                ];
            })
            ->schema(function (TodoListField $component): array {
                $fields = [
                    FlexTextInput::make('label')
                        ->label(fn (): string => $component->todoListString('filament-flex-fields::default.todo_list.edit_label'))
                        ->placeholder(fn (): string => $component->getCreatePlaceholder())
                        ->required()
                        ->maxLength(500)
                        ->autofocus(),
                ];

                if ($component->canEditWithDescription()) {
                    $fields[] = FlexTextInput::make('description')
                        ->label(fn (): string => $component->todoListString('filament-flex-fields::default.todo_list.edit_description'))
                        ->placeholder(fn (): string => $component->getCreateDescriptionPlaceholder())
                        ->maxLength(2000);
                }

                return $fields;
            })
            ->action(function (array $data, array $arguments, TodoListField $component): void {
                $updated = $component->applyEditedTodoItem(
                    (string) ($arguments['id'] ?? ''),
                    $data,
                    is_array($arguments['item'] ?? null) ? $arguments['item'] : null,
                );

                if (! is_array($updated)) {
                    return;
                }

                // Alpine lives under wire:ignore — push the patched row so the list label updates immediately.
                $component->getLivewire()->dispatch($component->getEditSyncedEvent(), item: $updated);
            })
            ->visible(fn (TodoListField $component): bool => $component->canEdit());
    }

    protected function todoListString(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $clientItem  Compatibility argument; mutation uses the component state.
     * @return array<string, mixed>|null
     */
    public function applyEditedTodoItem(string $id, array $data, ?array $clientItem = null): ?array
    {
        if ($id === '' || ! $this->canEdit() || $this->isDisabled() || ! is_string($data['label'] ?? null)
            || mb_strlen($data['label']) > 500) {
            return null;
        }

        $needle = (string) $id;
        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '') {
            return null;
        }

        $current = $this->findItemInState($needle);

        if ($current === null || ! $this->itemIsEditable($current)) {
            return null;
        }

        $patch = [
            'label' => $label,
        ];

        if ($this->canEditWithDescription()) {
            $rawDescription = $data['description'] ?? '';

            if (! is_string($rawDescription) || mb_strlen($rawDescription) > 2000) {
                return null;
            }

            $description = trim($rawDescription);
            $patch['description'] = $description !== '' ? $description : null;
        }

        $candidate = array_merge($current, $patch);

        if ($this->hasEditUsing()) {
            $result = $this->evaluate($this->editUsing, [
                'item' => $current,
                'data' => $patch,
                'children' => is_array($current['children'] ?? null) ? $current['children'] : [],
                'component' => $this,
            ]);

            if (! is_array($result)) {
                return null;
            }

            $candidate = array_merge($current, $result, [
                'id' => $current['id'],
            ]);

            if (! isset($candidate['label']) || trim((string) $candidate['label']) === '') {
                return null;
            }
        }

        // Keep nested children when editing a parent (editUsing must not wipe them).
        if (array_key_exists('children', $current) && ! array_key_exists('children', $candidate)) {
            $candidate['children'] = $current['children'];
        }

        $state = $this->normalizeState(is_array($this->getState()) ? $this->getState() : []);
        try {
            $next = $this->normalizeState($this->mapReplaceItemById($state, $needle, $candidate));
        } catch (\InvalidArgumentException) {
            return null;
        }
        $this->state($next);

        return $candidate;
    }

    public function getEditSyncedEvent(): string
    {
        return 'fff-todo-list-edited-'.md5((string) $this->getLivewireKey());
    }
}
