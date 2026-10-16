<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration;

use Closure;
use InvalidArgumentException;

trait ConfiguresTodoCollection
{
    protected bool|Closure $searchable = false;

    protected string|Closure|null $searchPrompt = null;

    protected bool|Closure $virtualizing = false;

    protected int|Closure $virtualItemHeight = 52;

    protected int|bool|Closure $paginated = false;

    protected bool|Closure $infiniteScroll = false;

    protected string|Closure|null $remoteLoader = null;

    protected bool|Closure $persistCompletedOrder = false;

    public function searchable(bool|Closure $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function searchPrompt(string|Closure|null $prompt): static
    {
        $this->searchPrompt = $prompt;

        return $this;
    }

    public function virtualizing(bool|Closure $virtualizing = true): static
    {
        $this->virtualizing = $virtualizing;

        return $this;
    }

    public function virtualItemHeight(int|Closure $height): static
    {
        $this->virtualItemHeight = $height;

        return $this;
    }

    public function paginated(int|bool|Closure $paginated = 10): static
    {
        $this->paginated = $paginated;

        $this->infiniteScroll = false;

        return $this;
    }

    public function infiniteScroll(bool|int|Closure $enabled = true): static
    {
        if (is_int($enabled)) {
            $this->paginated = $enabled;
            $this->infiniteScroll = true;

            return $this;
        }

        $this->infiniteScroll = $enabled;

        return $this;
    }

    public function remoteLoader(string|Closure|null $loader): static
    {
        $this->remoteLoader = $loader;

        return $this;
    }

    public function persistCompletedOrder(bool|Closure $persist = true): static
    {
        $this->persistCompletedOrder = $persist;

        return $this;
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->searchable);
    }

    public function getSearchPrompt(): string
    {
        return (string) ($this->evaluate($this->searchPrompt) ?: __('filament-flex-fields::default.todo_list.search_prompt'));
    }

    public function isVirtualizing(): bool
    {
        return (bool) $this->evaluate($this->virtualizing);
    }

    public function getVirtualItemHeight(): int
    {
        return max(32, (int) $this->evaluate($this->virtualItemHeight));
    }

    public function getPageSize(): ?int
    {
        $value = $this->evaluate($this->paginated);

        if ($value === false || $value === null) {
            return $this->isInfiniteScroll() ? 20 : null;
        }

        if ($value === true) {
            return 10;
        }

        if (! is_int($value) || $value < 1 || $value > 10000) {
            throw new InvalidArgumentException('Todo page size must be an integer between 1 and 10000.');
        }

        return $value;
    }

    public function isPaginated(): bool
    {
        return $this->evaluate($this->paginated) !== false && ! $this->isInfiniteScroll();
    }

    public function isInfiniteScroll(): bool
    {
        return (bool) $this->evaluate($this->infiniteScroll);
    }

    public function getRemoteLoader(): ?string
    {
        $loader = $this->evaluate($this->remoteLoader);

        return filled($loader) ? (string) $loader : null;
    }

    public function shouldPersistCompletedOrder(): bool
    {
        return (bool) $this->evaluate($this->persistCompletedOrder);
    }
}
