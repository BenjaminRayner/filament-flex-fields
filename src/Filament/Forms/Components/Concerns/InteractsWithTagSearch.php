<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\StringSuggestions;
use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/** Tags exposes the standard Select search endpoint; legacy callers keep the string endpoint. */
trait InteractsWithTagSearch
{
    protected ?Closure $getSearchResultsUsing = null;

    protected int|Closure $minSearchLength = 2;

    protected int|Closure $tagSearchDebounce = 150;

    protected ?Closure $validationSuggestionsUsing = null;

    /** Resolve all submitted candidates in one scoped query for large remote catalogs. */
    public function validationSuggestionsUsing(?Closure $callback): static
    {
        $this->validationSuggestionsUsing = $callback;

        return $this;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    public function getAllowedTagSuggestionsForValidation(array $tags): array
    {
        if ($this->validationSuggestionsUsing !== null) {
            return (new StringSuggestions)->normalize($this->evaluate($this->validationSuggestionsUsing, ['tags' => $tags]));
        }
        if (! $this->shouldSearchSuggestions()) {
            return $this->getSuggestionsForJs();
        }
        if (count($tags) > 100) {
            throw new \InvalidArgumentException('Remote suggestions-only validation above 100 tags requires a bulk validationSuggestionsUsing callback.');
        }
        $allowed = [];
        foreach ($tags as $tag) {
            foreach ($this->searchTagSuggestions($tag) as $candidate) {
                $allowed[$candidate] = $candidate;
            }
        }

        return (new StringSuggestions)->normalize(array_values($allowed));
    }

    public function getSearchResultsUsing(?Closure $callback): static
    {
        $this->getSearchResultsUsing = $callback;

        return $this;
    }

    public function minSearchLength(int|Closure $length): static
    {
        $this->minSearchLength = $length;

        return $this;
    }

    public function searchDebounce(int|Closure $milliseconds): static
    {
        $this->tagSearchDebounce = $milliseconds;

        return $this;
    }

    public function getSearchDebounce(): int
    {
        return $this->boundedTagSearchInteger($this->tagSearchDebounce, 'searchDebounce', 60000);
    }

    public function getMinSearchLength(): int
    {
        return $this->boundedTagSearchInteger($this->minSearchLength, 'minSearchLength', 10000);
    }

    private function boundedTagSearchInteger(int|Closure $value, string $name, int $maximum): int
    {
        $integer = PickerConfiguration::integer($this->evaluate($value), $name);
        if ($integer < 0 || $integer > $maximum) {
            throw new \InvalidArgumentException("Tags {$name} must be between 0 and {$maximum}.");
        }

        return $integer;
    }

    public function shouldSearchSuggestions(): bool
    {
        return $this->getSearchResultsUsing !== null;
    }

    /** @return list<string> */
    public function getSuggestionsForJs(): array
    {
        return $this->shouldSearchSuggestions() ? [] : (new StringSuggestions)->normalize($this->getSuggestions());
    }

    /** @return list<string> */
    public function searchTagSuggestions(string $search): array
    {
        SearchQueryInput::assert($search);
        $query = trim($search);
        if (! $this->shouldSearchSuggestions()) {
            return (new StringSuggestions)->normalize($this->getSuggestions());
        }
        if (mb_strlen($query) < $this->getMinSearchLength()) {
            return [];
        }

        return (new StringSuggestions)->normalize($this->evaluate($this->getSearchResultsUsing, ['search' => $query]));
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function getTagSearchResults(string $search): array
    {
        SearchQueryInput::assert($search);
        app(SelectSearchRateLimiter::class)->requireAttempt((string) $this->getName());

        return $this->searchTagSuggestions($search);
    }

    /** @return list<array{value: string, label: string}> */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSearchResultsForJs(string $search): array
    {
        return array_values(array_map(static fn (string $value): array => ['value' => $value, 'label' => $value], $this->getTagSearchResults($search)));
    }
}
