<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TodoList\Validation;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Bjanczak\FilamentFlexFields\Support\TodoList\TodoStateCodec;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class TodoSelectionRule implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly TodoListField $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if ($value === null) {
                $value = [];
            }
            if (! is_array($value)) {
                throw new InvalidArgumentException('Todo state must be a list.');
            }
            $items = $this->field->normalizeState($value);
            $seed = $this->field->seedItemsFromOptions();
            $seeded = TodoStateCodec::index($seed);
            $parents = [];
            foreach ($seed as $root) {
                $parents[$root['id']] = null;
                foreach ($root['children'] ?? [] as $child) {
                    $parents[$child['id']] = $root['id'];
                }
            }
            foreach ($items as $root) {
                if (array_key_exists($root['id'], $parents) && $parents[$root['id']] !== null) {
                    throw new InvalidArgumentException('Configured child cannot become a root.');
                }
                foreach ($root['children'] ?? [] as $child) {
                    if (array_key_exists($child['id'], $parents) && $parents[$child['id']] !== $root['id']) {
                        throw new InvalidArgumentException('Configured task cannot change parent.');
                    }
                }
            }
            $submitted = TodoStateCodec::index($items);
            foreach ($submitted as $id => $item) {
                if (! isset($seeded[$id]) && ! $this->field->canCreate() && $this->field->getRemoteLoader() === null) {
                    throw new InvalidArgumentException('Todo contains an unavailable id.');
                }
                if (($seeded[$id]['disabled'] ?? false) && $item['done'] !== $seeded[$id]['done']) {
                    throw new InvalidArgumentException('Locked Todo completion cannot change.');
                }
            }
        } catch (InvalidArgumentException) {
            $fail($this->message('validation.array', ['attribute' => $attribute]));

            return;
        }
        $count = count(array_filter($items, static fn (array $item): bool => $item['done']));
        $maxItems = $this->field->getMaxItems();
        $min = $this->field->getMinDone() ?? ($this->field->isRequired() ? 1 : null);
        $max = $this->field->getMaxDone();
        $exact = $this->field->getExactDone();
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Todo minimum exceeds maximum.');
        }
        foreach (['max_items' => [$maxItems, count($items) > ($maxItems ?? PHP_INT_MAX)],
            'min_done' => [$min, $count < ($min ?? 0)], 'max_done' => [$max, $count > ($max ?? PHP_INT_MAX)],
            'exact_done' => [$exact, $exact !== null && $count !== $exact]] as $key => [$limit, $invalid]) {
            if ($invalid) {
                $fail($this->message('filament-flex-fields::default.validation.todo_list.'.$key, ['count' => $limit]));
            }
        }
    }

    /** @param array<string, mixed> $parameters */
    private function message(string $key, array $parameters): string
    {
        $message = __($key, $parameters);

        return is_string($message) ? $message : $key;
    }
}
