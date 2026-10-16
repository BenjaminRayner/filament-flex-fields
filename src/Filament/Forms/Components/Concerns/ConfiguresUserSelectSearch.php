<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;

trait ConfiguresUserSelectSearch
{
    protected int|Closure $maxVisibleAvatars = 5;

    protected int|Closure $minSearchLength = 2;

    protected int|Closure $defaultSuggestionsLimit = 5;

    protected ?Closure $applySearchUsing = null;

    public function maxVisibleAvatars(int|Closure $limit): static
    {
        $this->maxVisibleAvatars = $limit;

        return $this;
    }

    public function defaultSuggestionsLimit(int|Closure $limit): static
    {
        $this->defaultSuggestionsLimit = $limit;

        return $this;
    }

    public function minSearchLength(int|Closure $length): static
    {
        $this->minSearchLength = $length;

        return $this;
    }

    public function applySearchUsing(?Closure $callback): static
    {
        $this->applySearchUsing = $callback;

        return $this;
    }

    public function getMaxVisibleAvatars(): int
    {
        return max(1, PickerConfiguration::integer($this->evaluate($this->maxVisibleAvatars), 'maxVisibleAvatars'));
    }

    public function getMinSearchLength(): int
    {
        return max(0, PickerConfiguration::integer($this->evaluate($this->minSearchLength), 'minSearchLength'));
    }

    public function getDefaultSuggestionsLimit(): int
    {
        $limit = PickerConfiguration::integer($this->evaluate($this->defaultSuggestionsLimit), 'defaultSuggestionsLimit');

        return max(1, $limit);
    }

    public function getApplySearchUsing(): ?Closure
    {
        return $this->applySearchUsing;
    }
}
