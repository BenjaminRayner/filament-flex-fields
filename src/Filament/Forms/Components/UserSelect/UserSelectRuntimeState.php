<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Support\Select\Transport\BoundedJsonPayload;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Request-scoped caches for a single UserSelect field instance.
 */
class UserSelectRuntimeState
{
    /**
     * @var array<string, array<int|string, array<string, mixed>>>
     */
    public array $searchResultsCache = [];

    /**
     * @var array<string, array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }>
     */
    public array $resolvedOptionShapeCache = [];

    /**
     * @var array<string, Model|null>
     */
    public array $resolvedRecordCache = [];

    /**
     * Full records are distinct from projected option-label batches (native edit actions).
     *
     * @var array<string, Model|null>
     */
    public array $selectedRecordCache = [];

    /**
     * @var ?list<array{value: string, user: array{name: string, email: ?string, avatarUrl: ?string, verified: bool, initials: string}}>
     */
    public ?array $cachedSelectedUsersForDisplay = null;

    public ?string $cachedSelectedUsersIdentity = null;

    private ?string $contextIdentity = null;

    /** @var array<string, int> */
    private array $searchSizes = [];

    private int $searchBytes = 0;

    public function __construct(
        private readonly int $maxSearchEntries = 64,
        private readonly int $maxSearchBytes = 4194304,
    ) {}

    public function synchronizeContext(string $identity): void
    {
        if ($this->contextIdentity === $identity) {
            return;
        }
        $this->contextIdentity = $identity;
        $this->searchResultsCache = [];
        $this->searchSizes = [];
        $this->searchBytes = 0;
        $this->resolvedRecordCache = [];
        $this->selectedRecordCache = [];
        $this->resolvedOptionShapeCache = [];
        $this->cachedSelectedUsersForDisplay = null;
        $this->cachedSelectedUsersIdentity = null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $results
     * @return array<int|string, array<string, mixed>>
     */
    public function rememberSearchResults(string $key, array $results): array
    {
        $this->forgetSearch($key);
        if ($this->maxSearchEntries <= 0 || $this->maxSearchBytes <= 0) {
            return $results;
        }
        try {
            $bytes = (new BoundedJsonPayload)->measure($results, $this->maxSearchBytes);
        } catch (InvalidArgumentException) {
            return $results;
        }
        while ($this->searchResultsCache !== [] && (count($this->searchResultsCache) >= $this->maxSearchEntries
            || $this->searchBytes + $bytes > $this->maxSearchBytes)) {
            $this->forgetSearch((string) array_key_first($this->searchResultsCache));
        }
        $this->searchResultsCache[$key] = $results;
        $this->searchSizes[$key] = $bytes;
        $this->searchBytes += $bytes;

        return $results;
    }

    /** @return array<int|string, array<string, mixed>>|null */
    public function cachedSearchResults(string $key): ?array
    {
        if (! array_key_exists($key, $this->searchResultsCache)) {
            return null;
        }
        $results = $this->searchResultsCache[$key];
        unset($this->searchResultsCache[$key]);
        $this->searchResultsCache[$key] = $results;

        return $results;
    }

    private function forgetSearch(string $key): void
    {
        $this->searchBytes -= $this->searchSizes[$key] ?? 0;
        unset($this->searchResultsCache[$key], $this->searchSizes[$key]);
    }
}
