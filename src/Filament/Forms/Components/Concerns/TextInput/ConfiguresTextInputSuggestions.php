<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;

trait ConfiguresTextInputSuggestions
{
    /** @var array<array-key, mixed>|Closure */
    protected array|Closure $textInputSuggestions = [];

    protected ?Closure $textInputSuggestionSearch = null;

    /** @param array<array-key, mixed>|Closure $items */
    public function suggestions(array|Closure $items): static
    {
        $this->textInputSuggestions = $items;

        return $this;
    }

    public function suggestionsUsing(?Closure $search): static
    {
        $this->textInputSuggestionSearch = $search;

        return $this;
    }

    public function hasTextInputSuggestions(): bool
    {
        return $this->textInputSuggestionSearch !== null || $this->textInputSuggestions instanceof Closure || $this->textInputSuggestions !== [];
    }

    public function hasAsyncTextInputSuggestions(): bool
    {
        return $this->textInputSuggestionSearch !== null;
    }

    /** @return list<array{value: string, label: string, description: string, disabled: bool}> */
    public function getInitialTextInputSuggestions(): array
    {
        return $this->normalizeTextInputSuggestions($this->evaluate($this->textInputSuggestions));
    }

    /** @return list<array{value: string, label: string, description: string, disabled: bool}> */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getTextInputSuggestionsForJs(string $search): array
    {
        SearchQueryInput::assert($search);
        if ($this->isDisabled() || $this->isReadOnly() || $this->isSensitiveTextInput() || $this->isHidden()
            || ! $this->hasAsyncTextInputSuggestions()) {
            return [];
        }
        $options = $this->getTextInputEditingOptions();
        if (mb_strlen($search) < $options['suggestionMinLength']) {
            return [];
        }
        if (! app(SelectSearchRateLimiter::class)->attempt('text-input:'.$this->getKey())) {
            return [];
        }
        $results = $this->normalizeTextInputSuggestions($this->evaluate($this->textInputSuggestionSearch, ['search' => $search]));

        return array_slice($results, 0, $options['suggestionLimit']);
    }

    /** @return list<array{value: string, label: string, description: string, disabled: bool}> */
    protected function normalizeTextInputSuggestions(mixed $items): array
    {
        if (! is_array($items) || count($items) > 200) {
            throw new InvalidArgumentException('Suggestions must resolve to at most 200 bounded entries.');
        }
        $list = array_is_list($items);
        $result = [];
        foreach ($items as $key => $item) {
            $record = is_array($item) ? $item : ['value' => $list ? $item : $key, 'label' => $item];
            $value = $record['value'] ?? $record['label'] ?? null;
            $label = $record['label'] ?? $value;
            $description = $record['description'] ?? '';
            if ((! is_string($value) && ! is_numeric($value)) || ! is_string($label) || ! is_string($description)
                || mb_strlen((string) $value) > 512 || mb_strlen($label) > 120 || mb_strlen($description) > 240) {
                throw new InvalidArgumentException('Invalid suggestion value, label or description.');
            }
            $result[] = ['value' => (string) $value, 'label' => $label, 'description' => $description, 'disabled' => (bool) ($record['disabled'] ?? false)];
        }

        return $result;
    }
}
