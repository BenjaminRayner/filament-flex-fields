<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\ComponentPresentation;
use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\PublishedResource;
use Bjanczak\FilamentFlexFields\Support\Playground\TodoListFieldPlayground;

return new ComponentDefinition(
    id: 'todo-list-field',
    kind: ComponentKind::Field,
    styles: ['todo-list-field'],
    stylesFrom: ['text-input-presentation'],
    entry: 'todo-list-field',
    publications: [new PublishedResource(
        id: 'resources', sourceDirectory: 'resources/dist/audio/todo-list-field', publicDirectory: 'js/janczakb/filament-flex-fields/audio/todo-list-field',
    )],
    catalog: new ComponentPresentation(
        label: 'filament-flex-fields::catalog.components.todo-list-field.label',
        description: 'filament-flex-fields::catalog.components.todo-list-field.description',
        category: CatalogCategory::Collections,
        keywords: ['todo', 'list', 'field', 'multiple', 'choices', 'wybór'],
        icon: 'hugeicons-task-daily-01',
        author: 'FLEX',
        official: true,
        examples: [
            CodeExample::php(dirname(__DIR__).'/examples/todo-list-field/usage.php'),
        ],
        demoClass: TodoListFieldPlayground::class,
        demoSlug: 'todo-list-field',
        sort: 382,
        documentation: 'todolistfield.md',
    ),
);
