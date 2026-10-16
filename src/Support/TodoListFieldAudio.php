<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use InvalidArgumentException;

final class TodoListFieldAudio
{
    private const array FILES = [
        'todo-confetti.mp3', 'todo-celebrate-start.mp3', 'todo-accent.mp3',
        'todo-fireworks-burst.mp3', 'todo-checks.mp3', 'todo-sparkles.mp3',
        'todo-celebrate-burst.mp3', 'todo-create.mp3', 'todo-check.mp3',
        'todo-fireworks-start.mp3',
    ];

    public static function url(string $file): string
    {
        if (! in_array($file, self::FILES, true)) {
            throw new InvalidArgumentException('Unknown Todo List audio resource.');
        }

        return PublishedResourceUrl::for('todo-list-field', 'resources', $file);
    }
}
