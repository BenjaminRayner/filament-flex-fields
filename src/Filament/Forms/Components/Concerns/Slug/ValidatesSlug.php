<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Bjanczak\FilamentFlexFields\Support\Slug\SlugGenerator;
use Bjanczak\FilamentFlexFields\Support\Slug\Spatie\UniquenessQuery;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Preview and final validation resolve the same current configuration. */
trait ValidatesSlug
{
    protected string|Closure|null $slugValidationPattern = null;

    /** @var array<int, string|Closure>|Closure */
    protected array|Closure $slugValidationRules = [];

    protected bool|Closure|null $slugMustBeUnique = null;

    protected bool|Closure $slugCheckUniqueLive = true;

    protected string|Closure|null $slugUniqueClass = null;

    protected ?Closure $slugUniqueQueryScope = null;

    /** @var array<string, mixed> */
    protected array $slugUniqueOptions = [];

    public function slugPattern(string|Closure $pattern): static
    {
        return $this->regex($pattern);
    }

    public function regex(string|Closure|null $pattern): static
    {
        $this->slugValidationPattern = $pattern;

        return $this;
    }

    public function slugUnique(bool|Closure $condition = true): static
    {
        $this->slugMustBeUnique = $condition;

        return $this;
    }

    public function liveUniqueValidation(bool|Closure $condition = true): static
    {
        $this->slugCheckUniqueLive = $condition;

        return $this;
    }

    public function slugUniqueModel(string|Closure|null $model): static
    {
        $this->slugUniqueClass = $model;

        return $this;
    }

    public function slugUniqueScope(?Closure $scope): static
    {
        $this->slugUniqueQueryScope = $scope;

        return $this;
    }

    /** @param array<string, mixed> $parameters */
    public function slugUniqueParameters(array $parameters): static
    {
        $this->slugUniqueOptions = $parameters;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSlugUniqueParameters(): array
    {
        return $this->slugUniqueOptions;
    }

    public function getSlugUniqueScope(): ?Closure
    {
        return $this->slugUniqueQueryScope;
    }

    public function shouldEnforceSlugUnique(): bool
    {
        return $this->slugMustBeUnique !== null ? (bool) $this->evaluate($this->slugMustBeUnique)
            : ($this->resolveSpatieValidationContext()?->options->generateUniqueSlugs ?? true);
    }

    public function shouldValidateSlugUniquenessLive(): bool
    {
        return $this->shouldEnforceSlugUnique() && (bool) $this->evaluate($this->slugCheckUniqueLive);
    }

    public function getSlugUniqueValidationMessage(): string
    {
        return Translations::get('filament-flex-fields::default.validation.slug.unique');
    }

    /** @param array<int, string|Closure>|Closure $rules */
    public function slugRules(array|Closure $rules): static
    {
        $this->slugValidationRules = $rules;
        if (is_array($rules) && in_array('required', $rules, true)) {
            $this->required();
        }

        return $this;
    }

    public function getRegex(): string
    {
        $pattern = $this->evaluate($this->slugValidationPattern);

        return is_string($pattern) && $pattern !== '' ? $pattern : SlugGenerator::patternForSeparator($this->getSeparator(), $this->allowsHomepageSlug());
    }

    public function requiresServerDraftValidation(): bool
    {
        return $this->shouldValidateSlugUniquenessLive() || $this->slugValidationPattern !== null || $this->slugValidationRules !== [];
    }

    public function getSlugUniqueModel(): ?string
    {
        $class = $this->evaluate($this->slugUniqueClass);

        return is_string($class) && $class !== '' ? $class : null;
    }

    public function isSlugValueUnique(?string $slug): bool
    {
        if (! $this->shouldEnforceSlugUnique() || $slug === null || $slug === '') {
            return true;
        }
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return true;
        }
        $options = $this->slugUniqueOptions;
        $class = $options['model'] ?? $this->getSlugUniqueModel() ?? $this->getSpatieModelClass();
        $class = $class instanceof Closure ? $this->evaluate($class) : $class;
        $column = (string) ($options['column'] ?? $this->getSpatieSlugField());
        $table = $options['table'] ?? null;
        if (is_string($class) && is_subclass_of($class, Model::class)) {
            $context = $class === $this->getSpatieModelClass() ? $this->resolveSpatieValidationContext() : null;
            if ($context !== null) {
                $column = array_key_exists('column', $options) ? $column : UniquenessQuery::column($context);
                $query = UniquenessQuery::make($context);
            } else {
                $query = $class::query();
            }
        } elseif (is_string($table) && $table !== '') {
            $query = DB::table($table);
        } else {
            return true;
        }
        $query->where($column, $slug);
        $ignorable = $options['ignorable'] ?? $this->resolveRecord();
        if ($ignorable instanceof Closure) {
            $ignorable = $this->evaluate($ignorable);
        }
        if (($options['ignoreRecord'] ?? true) && $ignorable !== null) {
            if ($ignorable instanceof Model) {
                $query->where($ignorable->getKeyName(), '!=', $ignorable->getKey());
            } elseif (is_scalar($ignorable)) {
                $query->where((string) ($options['ignoreColumn'] ?? 'id'), '!=', $ignorable);
            }
        }
        $scope = $options['modifyQueryUsing'] ?? $options['scope'] ?? $this->slugUniqueQueryScope;
        if ($scope instanceof Closure) {
            $scoped = $this->evaluate($scope, ['query' => $query]);
            if ($scoped instanceof Builder || $scoped instanceof QueryBuilder) {
                $query = $scoped;
            }
        }
        if (($modifyRule = $options['modifyRuleUsing'] ?? null) instanceof Closure) {
            $model = $query instanceof Builder ? $query->getModel() : null;
            $validationTable = $table ?? ($model !== null
                ? ($model->getConnectionName() ?? (string) config('database.default')).'.'.$model->getTable()
                : $query->from);
            $unique = Rule::unique($validationTable, $column);
            if (($options['ignoreRecord'] ?? true) && $ignorable !== null) {
                $unique->ignore($ignorable, $options['ignoreColumn'] ?? null);
            }
            // Run rule constraints against the same scoped candidates, rather
            // than performing a second, broader existence check afterwards.
            $identity = (string) ($options['identityColumn'] ?? ($query instanceof Builder ? $query->getModel()->getKeyName() : ($options['ignoreColumn'] ?? 'id')));
            $candidateKey = $query instanceof Builder ? $query->getModel()->qualifyColumn($identity) : $identity;
            $candidates = (clone $query)->select($candidateKey);
            $unique->using(static function (QueryBuilder $check) use ($candidates, $identity): void {
                $check->whereIn($identity, $candidates);
            });
            $changed = $this->evaluate($modifyRule, ['rule' => $unique]);

            return Validator::make(['slug' => $slug], ['slug' => [$changed ?? $unique]])->passes();
        }

        return ! $query->exists();
    }

    /** @return array{available: bool, message: string|null} */
    public function checkSlugUniqueness(string $slug): array
    {
        $available = $this->isSlugValueUnique($slug);

        return ['available' => $available, 'message' => $available ? null : $this->getSlugUniqueValidationMessage()];
    }

    public function validateSlugValue(mixed $value, bool $checkUnique = true): ?string
    {
        if (! is_string($value) && $value !== null) {
            return Translations::get('filament-flex-fields::default.validation.slug.invalid');
        }
        $value = $value ?? '';
        $rules = $this->evaluate($this->slugValidationRules);
        $rules = is_array($rules) ? array_values($rules) : [];
        $rules = [...$rules, $this->isRequired() ? 'required' : 'nullable', 'string'];
        if ($this->getMaxSlugLength() !== null) {
            $rules[] = 'max:'.$this->getMaxSlugLength();
        }
        $validator = Validator::make(['slug' => $value], ['slug' => $rules], attributes: ['slug' => $this->getLabel()]);
        if ($validator->fails()) {
            return $validator->errors()->first('slug');
        }
        if ($value === '') {
            return null;
        }
        $homepage = $this->allowsHomepageSlug() && SlugGenerator::isHomepage($value);
        if ($this->normalizeSlug($value) === '' || (! $homepage && preg_match($this->getRegex(), $value) !== 1)) {
            return Translations::get('filament-flex-fields::default.validation.slug.pattern');
        }

        return $checkUnique && ! $this->isSlugValueUnique($value) ? $this->getSlugUniqueValidationMessage() : null;
    }
}
