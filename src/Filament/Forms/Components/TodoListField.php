<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\ConfiguresTodoFeedback;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\ConfiguresTodoInteraction;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\InteractsWithTodoActions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\InteractsWithTodoState;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\PresentsTodoList;
use Bjanczak\FilamentFlexFields\Support\TodoList\Validation\TodoSelectionRule;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class TodoListField extends Field
{
    use ConfiguresTodoFeedback;
    use ConfiguresTodoInteraction;
    use HasControlSize;
    use InteractsWithTodoActions;
    use InteractsWithTodoState;
    use PresentsTodoList;

    protected string $view = 'filament-flex-fields::forms.components.todo-list-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (TodoListField $component): array => $component->seedItemsFromOptions());

        $this->afterStateHydrated(function (TodoListField $component, mixed $state): void {
            if ($state === null) {
                $component->state($component->seedItemsFromOptions());
            } elseif (is_array($state)) {
                try {
                    $component->state($component->normalizeState($state));
                } catch (\InvalidArgumentException) {
                    // Preserve malformed persisted state so validation can report it.
                }
            }
        });

        $this->dehydrateStateUsing(fn (TodoListField $component, mixed $state): mixed => is_array($state) ? $component->normalizeState($state) : $state);

        $this->rule(fn (TodoListField $component): ValidationRule => new TodoSelectionRule($component));
    }

    public static function makeNewItemId(): string
    {
        return (string) Str::uuid();
    }
}
