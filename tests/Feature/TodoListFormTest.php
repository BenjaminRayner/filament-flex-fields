<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTodoListForm;
use Livewire\Livewire;

beforeEach(function () {
    TestableTodoListForm::$formSchema = [TodoListField::make('tasks')->options([
        'a' => 'Alpha', 'b' => 'Beta', 'locked' => ['label' => 'Locked', 'locked' => true, 'done' => true],
    ])];
});
afterEach(function () {
    TestableTodoListForm::$formSchema = [];
});

it('renders one SSR list and the shared search control', function () {
    TestableTodoListForm::$formSchema[0]->searchable();
    $html = Livewire::test(TestableTodoListForm::class)->html();
    expect(substr_count($html, 'data-todo-list role="list"'))->toBe(1)
        ->and($html)->toContain('fff-flex-text-input', 'data-todo-group-template', 'Alpha', 'data-fff-asset-consumer="todo-list-field"')
        ->not->toContain('x-for=', 'data-todo-ssr', 'x-init="init()"');
});

it('saves canonical task state including configured locks', function () {
    $state = [['id' => 'a', 'label' => 'Alpha', 'done' => true],
        ['id' => 'locked', 'label' => 'Locked', 'done' => true, 'disabled' => false]];
    $form = Livewire::test(TestableTodoListForm::class)->set('data.tasks', $state)->call('save')->assertHasNoErrors();
    expect($form->get('saved')['tasks'][0]['done'])->toBeTrue()
        ->and($form->get('saved')['tasks'][1]['disabled'])->toBeTrue();
});

it('rejects malformed submitted tasks before dehydration', function (mixed $state) {
    $form = Livewire::test(TestableTodoListForm::class)->set('data.tasks', $state)->call('save')->assertHasErrors(['data.tasks']);
    expect($form->get('saved'))->toBeNull();
})->with([
    'scalar' => ['a'], 'boolean' => [true], 'unknown' => [[['id' => 'unknown', 'label' => 'Unknown']]],
    'duplicate' => [[['id' => 'a', 'label' => 'A'], ['id' => 'a', 'label' => 'A']]],
    'bad flag' => [[['id' => 'a', 'label' => 'A', 'done' => 'yes']]],
    'blank' => [[['id' => 'a', 'label' => ' ']]],
    'oversized' => [[['id' => 'a', 'label' => str_repeat('x', 501)]]],
    'locked completion' => [[['id' => 'locked', 'label' => 'Locked', 'done' => false, 'disabled' => false]]],
    'nested level' => [[['id' => 'a', 'label' => 'A', 'children' => [['id' => 'c', 'label' => 'C', 'children' => ['Deep']]]]]],
    'tree collision' => [[['id' => 'a', 'label' => 'A', 'children' => [['id' => 'a', 'label' => 'Child']]]]],
    'associative' => [['a' => ['id' => 'a', 'label' => 'A']]],
]);

it('enforces root completion counts and configured item limits', function () {
    foreach (['min', 'max', 'exact', 'items', 'required'] as $kind) {
        $field = TodoListField::make('tasks')->options(['a' => 'A', 'b' => 'B']);
        match ($kind) {
            'min' => $field->minDone(2), 'max' => $field->maxDone(0), 'exact' => $field->exactDone(2),
            'items' => $field->maxItems(0), 'required' => $field->required(),
        };
        TestableTodoListForm::$formSchema = [$field];
        $state = $kind === 'required' ? [] : [['id' => 'a', 'label' => 'A', 'done' => true]];
        Livewire::test(TestableTodoListForm::class)->set('data.tasks', $state)->call('save')->assertHasErrors(['data.tasks']);
    }
});

it('accepts new tasks only when creation is enabled', function () {
    TestableTodoListForm::$formSchema[0]->allowCreate();
    Livewire::test(TestableTodoListForm::class)->set('data.tasks', [['id' => 'new', 'label' => 'New']])->call('save')->assertHasNoErrors();
});

it('isolates edit and undo events between forms with the same state path', function () {
    $read = function (string $html): array {
        preg_match('/data-todo-config="([^"]+)"/', $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    };
    $first = $read(Livewire::test(TestableTodoListForm::class)->html());
    $second = $read(Livewire::test(TestableTodoListForm::class)->html());
    expect($first['undoEvent'])->not->toBe($second['undoEvent'])
        ->and($first['editSyncedEvent'])->not->toBe($second['editSyncedEvent'])
        ->and($first['deleteSyncedEvent'])->not->toBe($second['deleteSyncedEvent']);
});

it('rejects changing the parent of a configured task', function (array $state) {
    TestableTodoListForm::$formSchema = [TodoListField::make('tasks')->options([
        'a' => ['label' => 'A', 'children' => ['child' => 'Child']], 'b' => 'B',
    ])->allowCreate()];
    Livewire::test(TestableTodoListForm::class)->set('data.tasks', $state)->call('save')->assertHasErrors(['data.tasks']);
})->with([
    'promoted child' => [[['id' => 'a:child', 'label' => 'Child']]],
    'moved child' => [[['id' => 'b', 'label' => 'B', 'children' => [['id' => 'a:child', 'label' => 'Child']]]]],
    'demoted root' => [[['id' => 'a', 'label' => 'A', 'children' => [['id' => 'b', 'label' => 'B']]]]],
]);

it('confirms deletion without invoking callbacks on cancel and cascades only the accepted task', function () {
    $calls = 0;
    TestableTodoListForm::$formSchema = [TodoListField::make('tasks')->options([
        'parent' => ['label' => 'Parent', 'children' => ['child' => 'Child']], 'other' => 'Other',
    ])->allowDelete()->deletable('all')->deleteConfirmation()->deleteUsing(function () use (&$calls): bool {
        $calls++;

        return true;
    })];
    $form = Livewire::test(TestableTodoListForm::class);
    $key = $form->instance()->getSchema('form')->getComponent('tasks')->getKey();
    $context = ['schemaComponent' => $key];
    $form->call('mountAction', 'confirmDeleteTodoItem', ['id' => 'parent'], $context);
    expect($form->get('data.tasks'))->toHaveCount(2)->and($calls)->toBe(0);
    $form->call('unmountAction');
    expect($form->get('data.tasks'))->toHaveCount(2)->and($calls)->toBe(0);
    $form->call('mountAction', 'confirmDeleteTodoItem', ['id' => 'parent'], $context)->call('callMountedAction');
    expect($calls)->toBe(1)->and(array_column($form->get('data.tasks'), 'id'))->toBe(['other']);
});

it('rechecks locks and callback rejection when confirming deletion', function () {
    TestableTodoListForm::$formSchema[0]->allowDelete()->deletable('all')->deleteConfirmation()->deleteUsing(fn (): bool => false);
    $form = Livewire::test(TestableTodoListForm::class);
    $field = $form->instance()->getSchema('form')->getComponent('tasks');
    $context = ['schemaComponent' => $field->getKey()];
    expect($field->deleteTodoItem(['id' => 'a']))->toBeFalse();
    $form->call('mountAction', 'confirmDeleteTodoItem', ['id' => 'a'], $context)->call('callMountedAction');
    expect($form->get('data.tasks'))->toHaveCount(3);
    $form->call('unmountAction')->call('mountAction', 'confirmDeleteTodoItem', ['id' => 'locked'], $context);
    expect($form->instance()->getMountedAction())->toBeNull();
});
