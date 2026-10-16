<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList;

use Bjanczak\FilamentFlexFields\Support\TodoList\TodoStateCodec;

trait InteractsWithTodoState
{
    /** @return array<string, mixed>|null */
    public function findItemInState(mixed $id): ?array
    {
        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $items = $this->currentTodoActionItems();

        return $items === null ? null : (TodoStateCodec::index($items)[(string) $id] ?? null);
    }

    /** @param list<array<string, mixed>> $items
     * @param  array<string, mixed>  $replacement
     * @return list<array<string, mixed>>
     */
    protected function mapReplaceItemById(array $items, string $needle, array $replacement): array
    {
        return array_map(static function (array $item) use ($needle, $replacement): array {
            if ((string) $item['id'] === $needle) {
                return [...$item, ...$replacement, 'id' => $item['id'], 'children' => $item['children'] ?? []];
            }
            $item['children'] = array_map(static fn (array $child): array => (string) $child['id'] === $needle
                ? [...$child, ...$replacement, 'id' => $child['id']] : $child, $item['children'] ?? []);

            return $item;
        }, $items);
    }

    /** @return list<array<string, mixed>> */
    public function seedItemsFromOptions(): array
    {
        return TodoStateCodec::seed($this->evaluate($this->options) ?? []);
    }

    /** @param array<int, mixed> $state
     * @return list<array<string, mixed>>
     */
    public function normalizeState(array $state): array
    {
        return TodoStateCodec::normalize($state, $this->evaluate($this->options) ?? []);
    }

    /** @return list<array<string, mixed>>|null */
    protected function currentTodoActionItems(): ?array
    {
        $state = $this->getState();
        if (! is_array($state)) {
            return null;
        }
        try {
            return $this->normalizeState($state);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
