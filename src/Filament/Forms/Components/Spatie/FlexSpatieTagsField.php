<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocales;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * Spatie Tags integration for {@see TagsField}.
 *
 * Requires `spatie/laravel-tags` and a model using `Spatie\Tags\HasTags`.
 *
 * @see https://github.com/spatie/laravel-tags
 */
class FlexSpatieTagsField extends TagsField
{
    protected string|Closure|null $spatieTagType = null;

    protected bool $allowAnySpatieTagType = true;

    protected string|Closure|null $spatieTagLocale = null;

    protected string|Closure|null $spatieTagFallbackLocale = null;

    protected int|Closure $spatieTagSearchLimit = 20;

    protected ?Closure $modifySpatieTagSearchQuery = null;

    public function tagLocale(string|Closure|null $locale): static
    {
        $this->spatieTagLocale = $locale;

        return $this;
    }

    public function getTagLocale(): string
    {
        $locale = $this->evaluate($this->spatieTagLocale) ?? app()->getLocale();
        TranslatableLocales::assertLocale($locale);

        return $locale;
    }

    /** Fallback labels preserve tag identities; missing translations are never copied on save. */
    public function tagFallbackLocale(string|Closure|null $locale): static
    {
        $this->spatieTagFallbackLocale = $locale;

        return $this;
    }

    public function getTagFallbackLocale(): ?string
    {
        $locale = $this->evaluate($this->spatieTagFallbackLocale);
        if ($locale !== null) {
            TranslatableLocales::assertLocale($locale);
        }

        return $locale;
    }

    public function tagSearchLimit(int|Closure $limit): static
    {
        $this->spatieTagSearchLimit = $limit;

        return $this;
    }

    public function getTagSearchLimit(): int
    {
        $limit = $this->evaluate($this->spatieTagSearchLimit);
        if (! is_int($limit) || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Spatie tag search limit must be between 1 and 100.');
        }

        return $limit;
    }

    /** Scope suggestions only; enforce tenant ownership in the configured Tag model for writes too. */
    public function modifyTagSearchQueryUsing(?Closure $callback): static
    {
        $this->modifySpatieTagSearchQuery = $callback;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSpatieRelationship();
        $this->configureSpatieTagSearch();
    }

    protected function configureSpatieTagSearch(): void
    {
        $this->getSearchResultsUsing(function (FlexSpatieTagsField $component, string $search): array {
            if (! class_exists($component->getTagClassName())) {
                return [];
            }

            $tagClass = $component->getTagClassName();
            $type = $component->getSpatieTagType();
            $locale = $component->getTagLocale();
            $escaped = addcslashes($search, '%_\\');

            $fallback = $component->getTagFallbackLocale();
            $query = $tagClass::query()->where(function (Builder $query) use ($component, $locale, $fallback, $escaped): void {
                $query->where('name->'.$locale, 'like', '%'.$escaped.'%');
                if ($fallback !== null && $fallback !== $locale) {
                    $query->orWhere(fn (Builder $query) => $component->withoutTagTranslation($query, $locale)
                        ->where('name->'.$fallback, 'like', '%'.$escaped.'%'));
                }
            });

            if ($component->modifySpatieTagSearchQuery !== null) {
                $scoped = $component->evaluate($component->modifySpatieTagSearchQuery, ['query' => $query, 'search' => $search, 'locale' => $locale]);
                if ($scoped !== null && ! $scoped instanceof Builder) {
                    throw new InvalidArgumentException('Tag search query callback must return a Builder or mutate it in place.');
                }
                $query = $scoped ?? $query;
            }

            if (! $component->allowsAnySpatieTagType()) {
                $query->when(
                    filled($type),
                    fn (Builder $query) => $query->where('type', $type),
                    fn (Builder $query) => $query->whereNull('type'),
                );
            }

            return $query
                ->orderBy('name->'.$locale)
                ->limit($component->getTagSearchLimit())
                ->get()
                ->map(fn (Model $tag): string => $component->translatedTagName($tag))
                ->all();
        });
    }

    private function translatedTagName(Model $tag): string
    {
        if (! method_exists($tag, 'getTranslation')) {
            throw new RuntimeException('The configured Spatie Tag model must support translated names.');
        }
        $fallback = $this->getTagFallbackLocale();
        $name = $tag->getTranslation('name', $this->getTagLocale(), $fallback === null);
        if ($fallback !== null && $name === '') {
            $name = $tag->getTranslation('name', $fallback, false);
        }
        if (! is_string($name)) {
            throw new RuntimeException('A translated tag name must be a string.');
        }

        return $name;
    }

    /** @param Builder<Model> $query
     * @return Builder<Model>
     */
    private function withoutTagTranslation(Builder $query, string $locale): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('name->'.$locale)->orWhere('name->'.$locale, ''));
    }

    /** @param list<string> $names
     * @return list<Model>
     */
    private function resolveSpatieTags(Model $record, array $names): array
    {
        if (! method_exists($record, 'tags')) {
            throw new RuntimeException('Spatie Tags requires a model with a tags relationship.');
        }
        $tagClass = $this->getTagClassName();
        $locale = $this->getTagLocale();
        $type = $this->getSpatieTagType();
        $fallback = $this->getTagFallbackLocale();
        // Re-read owned associations instead of trusting client IDs or stale relation state.
        $existing = $record->tags()->get()->filter(fn (Model $tag): bool => $this->allowsAnySpatieTagType() || $tag->getAttribute('type') === $type);
        $resolved = [];
        foreach ($names as $name) {
            $associated = $existing->filter(fn (Model $tag): bool => $this->translatedTagName($tag) === $name);
            if ($associated->isNotEmpty()) {
                array_push($resolved, ...$associated->all());

                continue;
            }
            $query = $tagClass::query()->where('name->'.$locale, $name);
            if (! $this->allowsAnySpatieTagType()) {
                $query->where('type', $type);
            }
            $matches = $query->get();
            if ($matches->isEmpty() && $fallback !== null && $fallback !== $locale) {
                $query = $this->withoutTagTranslation($tagClass::query(), $locale)->where('name->'.$fallback, $name);
                if (! $this->allowsAnySpatieTagType()) {
                    $query->where('type', $type);
                }
                $matches = $query->get();
            }
            if ($matches->isEmpty()) {
                $matches->push($tagClass::findOrCreate($name, $this->allowsAnySpatieTagType() ? null : $type, $locale));
            }
            foreach ($matches as $tag) {
                if (! $tag instanceof Model) {
                    throw new RuntimeException('Tag queries must return Eloquent models.');
                }
                $resolved[] = $tag;
            }
        }

        $byIdentity = [];
        foreach ($resolved as $tag) {
            $byIdentity[(string) $tag->getKey()] = $tag;
        }

        return array_values($byIdentity);
    }

    protected function configureSpatieRelationship(): void
    {
        $this->loadStateFromRelationshipsUsing(static function (FlexSpatieTagsField $component, ?Model $record): void {
            if (! $record || ! method_exists($record, 'tagsWithType')) {
                return;
            }

            $record->load('tags');

            if ($component->allowsAnySpatieTagType()) {
                $tags = $record->getRelationValue('tags');
            } else {
                $tags = $record->tagsWithType($component->getSpatieTagType());
            }

            $component->state($tags->map(fn (Model $tag): string => $component->translatedTagName($tag))->values()->all());
        });

        $this->saveRelationshipsUsing(static function (FlexSpatieTagsField $component, ?Model $record, array $state): void {
            if (! $record || ! method_exists($record, 'syncTagsWithType') || ! method_exists($record, 'syncTags') || ! method_exists($record, 'tags')) {
                return;
            }

            if ($component->allowsAnySpatieTagType()) {
                $component->syncSpatieTagsWithAnyType($record, $state);
                $record->unsetRelation('tags');

                return;
            }
            $record->getConnection()->transaction(function () use ($component, $record, $state): void {
                $tags = $component->resolveSpatieTags($record, array_values($state));
                $record->syncTagsWithType($tags, $component->getSpatieTagType());
            });
            $record->unsetRelation('tags');
        });

        $this->dehydrated(false);
    }

    /**
     * Group tags into a Spatie collection type (same API as Filament `SpatieTagsInput`).
     *
     * Pass `null` to allow tags of any type.
     */
    public function type(string|Closure|null $type): static
    {
        if ($type === null) {
            $this->allowAnySpatieTagType = true;
            $this->spatieTagType = null;
        } else {
            $this->allowAnySpatieTagType = false;
            $this->spatieTagType = $type;
        }

        return $this;
    }

    public function getSpatieTagType(): ?string
    {
        $type = $this->evaluate($this->spatieTagType);

        return is_string($type) ? $type : null;
    }

    public function allowsAnySpatieTagType(): bool
    {
        return $this->allowAnySpatieTagType;
    }

    /**
     * @return class-string
     */
    public function getTagClassName(): string
    {
        $model = $this->getModel();

        if ($model && method_exists($model, 'getTagClassName')) {
            return $model::getTagClassName();
        }

        $configured = config('tags.tag_model', 'Spatie\\Tags\\Tag');

        if (! is_string($configured) || ! class_exists($configured)) {
            throw new RuntimeException(
                'spatie/laravel-tags is required for FlexSpatieTagsField. Install it with: composer require spatie/laravel-tags',
            );
        }

        return $configured;
    }

    /**
     * @return array<string>
     */
    public function getSuggestions(): array
    {
        if ($this->suggestions !== null) {
            return parent::getSuggestions();
        }

        return [];
    }

    /**
     * @param  array<string>  $state
     */
    protected function syncSpatieTagsWithAnyType(?Model $record, array $state): void
    {
        if (! $record || ! method_exists($record, 'tags')) {
            return;
        }

        $record->getConnection()->transaction(function () use ($record, $state): void {
            $tags = $this->resolveSpatieTags($record, array_values($state));
            $record->tags()->sync(collect($tags)->map(fn (Model $tag): mixed => $tag->getKey())->all());
        });
    }
}
