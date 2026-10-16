<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Illuminate\Contracts\Support\Htmlable;

trait PresentsTodoList
{
    public function getPlusIcon(): string
    {
        return FlexIcon::CirclePlusFill;
    }

    public function getDeleteIcon(): string
    {
        return FlexIcon::Xmark;
    }

    public function getEditIcon(): string|BackedEnum|Htmlable
    {
        return $this->evaluate($this->editIcon) ?: FlexIcon::Pencil;
    }

    public function getReorderIcon(): string
    {
        return FlexIcon::Grip;
    }

    public function getChildrenIcon(): string
    {
        return 'hugeicons-node-add';
    }

    public function getDateIcon(): string
    {
        return 'hugeicons-calendar-05';
    }

    /**
     * @return list<string>
     */
    public function getWrapperClasses(): array
    {
        $classes = [
            'fff-todo-list-field',
            'fff-todo-list-field--'.$this->getSize(),
            'fff-todo-list-field--strike-'.$this->getStrikethroughStyle(),
        ];

        if ($this->isReorderable()) {
            $classes[] = 'fff-todo-list-field--reorderable';
        }

        if ($color = $this->getColor()) {
            $classes[] = 'fi-color-'.$color;
        }

        return $classes;
    }

    /**
     * @return array<string, string>
     */
    public function getSizeStyles(): array
    {
        return match ($this->getSize()) {
            'sm' => [
                '--fff-todo-list-row-py' => '0.5rem',
                '--fff-todo-list-row-px' => '0.625rem',
                '--fff-todo-list-gap' => '0.625rem',
                '--fff-todo-list-label-size' => '0.8125rem',
                '--fff-todo-list-body-size' => '0.6875rem',
                '--fff-todo-list-check-size' => '1.125rem',
            ],
            'lg' => [
                '--fff-todo-list-row-py' => '0.875rem',
                '--fff-todo-list-row-px' => '1rem',
                '--fff-todo-list-gap' => '0.875rem',
                '--fff-todo-list-label-size' => '1rem',
                '--fff-todo-list-body-size' => '0.8125rem',
                '--fff-todo-list-check-size' => '1.375rem',
            ],
            default => [
                '--fff-todo-list-row-py' => '0.625rem',
                '--fff-todo-list-row-px' => '0.75rem',
                '--fff-todo-list-gap' => '0.75rem',
                '--fff-todo-list-label-size' => '0.875rem',
                '--fff-todo-list-body-size' => '0.75rem',
                '--fff-todo-list-check-size' => '1.25rem',
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    public function getItemsForJs(): array
    {
        $raw = $this->getState();
        try {
            $state = is_array($raw) ? $this->normalizeState($raw) : $this->seedItemsFromOptions();
        } catch (\InvalidArgumentException) {
            // Rendering does not repair or dehydrate rejected state. The form rule reports it.
            $state = [];
        }

        return array_map(fn (array $item): array => $this->presentItem($item), $state);
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function presentItem(array $item): array
    {
        $item['editable'] = $this->itemIsEditable($item);
        $item['deletable'] = $this->itemIsDeletable($item);
        $item['children'] = array_map(fn (array $child): array => $this->presentItem($child), $item['children'] ?? []);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlpineConfig(): array
    {
        return [
            'items' => $this->getItemsForJs(),
            'disabled' => $this->resolveDisabledForAlpine(),
            'sounds' => $this->soundsEnabled(),
            'checkSound' => $this->getCheckSoundUrl(),
            'accentSound' => $this->getAccentSoundUrl(),
            'createSound' => $this->getCreateSoundUrl(),
            'celebration' => $this->getCelebration(),
            'celebrationDurationMs' => $this->getCelebrationDurationMs(),
            'celebrationSound' => $this->getCelebrationSoundUrl(),
            'celebrationStartSound' => $this->getCelebrationStartSoundUrl(),
            'celebrationAudio' => $this->getCelebrationAudio(),
            'celebrationFullscreen' => $this->isCelebrationFullscreen(),
            'strikethroughStyle' => $this->getStrikethroughStyle(),
            'doneSettleMs' => $this->getDoneSettleMs(),
            'allowCreate' => $this->canCreate(),
            'createWithDescription' => $this->canCreateWithDescription(),
            'allowDelete' => $this->canDelete(),
            'deletableMode' => $this->getDeletableMode(),
            'allowEdit' => $this->canEdit(),
            'editableMode' => $this->getEditableMode(),
            'editWithDescription' => $this->canEditWithDescription(),
            'reorderable' => $this->isReorderable(),
            'reorderAnimationDuration' => $this->getReorderAnimationDuration(),
            'undoCompletionNotifications' => $this->hasUndoCompletionNotifications(),
            'undoEvent' => 'fff-todo-list-undo-'.md5((string) $this->getLivewireKey()),
            'editSyncedEvent' => $this->getEditSyncedEvent(),
            'deleteSyncedEvent' => $this->getDeleteSyncedEvent(),
            'deleteConfirmation' => $this->requiresDeleteConfirmation(),
            'componentKey' => $this->getKey(),
            'createUsingEnabled' => $this->hasCreateUsing(),
            'editUsingEnabled' => $this->hasEditUsing(),
            'deleteUsingEnabled' => $this->hasDeleteUsing(),
            'reorderUsingEnabled' => $this->hasReorderUsing(),
            'afterToggledEnabled' => $this->hasAfterToggled(),
            'labels' => [
                'taskCompleted' => __('filament-flex-fields::default.todo_list.task_completed'),
                'tasksCompleted' => __('filament-flex-fields::default.todo_list.tasks_completed'),
                'undo' => __('filament-flex-fields::default.todo_list.undo'),
                'editItem' => __('filament-flex-fields::default.todo_list.edit_item'),
                'loadingMore' => __('filament-flex-fields::default.todo_list.loading_more'),
                'loadFailed' => __('filament-flex-fields::default.todo_list.load_failed'),
            ],
            'createLabel' => $this->getCreateLabel(),
            'createPlaceholder' => $this->getCreatePlaceholder(),
            'createDescriptionPlaceholder' => $this->getCreateDescriptionPlaceholder(),
            'searchable' => $this->isSearchable(),
            'searchPrompt' => $this->getSearchPrompt(),
            'virtualizing' => $this->isVirtualizing(),
            'virtualItemHeight' => $this->getVirtualItemHeight(),
            'paginated' => $this->isPaginated(),
            'pageSize' => $this->getPageSize(),
            'infiniteScroll' => $this->isInfiniteScroll(),
            'remoteLoader' => $this->getRemoteLoader(),
            'persistCompletedOrder' => $this->shouldPersistCompletedOrder(),
            'maxItems' => $this->getMaxItems(),
        ];
    }

    protected function resolveDisabledForAlpine(): bool
    {
        try {
            return $this->isDisabled();
        } catch (\Error) {
            return false;
        }
    }
}
