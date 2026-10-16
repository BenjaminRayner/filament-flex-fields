<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration;

use Closure;

trait ConfiguresTodoTasks
{
    /** @var array<string|int, string|array<string, mixed>>|Closure */
    protected array|Closure $options = [];

    protected bool|Closure $undoCompletionNotifications = true;

    /** @param array<string|int, string|array<string, mixed>>|Closure $options */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function undoCompletionNotifications(bool|Closure $enabled = true): static
    {
        $this->undoCompletionNotifications = $enabled;

        return $this;
    }

    public function hasUndoCompletionNotifications(): bool
    {
        return (bool) $this->evaluate($this->undoCompletionNotifications);
    }
}
