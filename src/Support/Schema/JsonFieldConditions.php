<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema;

use Bjanczak\FilamentFlexFields\Support\Schema\Conditions\ConditionProgram;
use Bjanczak\FilamentFlexFields\Support\Schema\Conditions\LoadedRelationReader;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/** Filament adapter: definition compilation is separate from live state resolution. */
final class JsonFieldConditions
{
    /** @var list<string> */
    public const OPERATORS = [
        'equals',
        'not_equals',
        'filled',
        'empty',
        'in',
        'contains',
        'not_contains',
        'greater_than',
        'less_than',
    ];

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    public static function compileVisibleWhen(array $rule, string $flexStatePathPrefix = 'flex_field_values', ?Model $record = null): Closure
    {
        return self::compile($rule, $flexStatePathPrefix, $record);
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    public static function compileRequiredWhen(array $rule, string $flexStatePathPrefix = 'flex_field_values', ?Model $record = null): Closure
    {
        return self::compile($rule, $flexStatePathPrefix, $record);
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    public static function compileDisabledWhen(array $rule, string $flexStatePathPrefix = 'flex_field_values', ?Model $record = null): Closure
    {
        return self::compile($rule, $flexStatePathPrefix, $record);
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    public static function assertValid(array $rule): void
    {
        ConditionProgram::compile($rule);
    }

    /**
     * Legacy callable adapter: flex/model getters receive their unqualified field path.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $rule
     * @param  callable(string): mixed  $get
     */
    public static function evaluate(array $rule, callable $get, ?Model $record = null): bool
    {
        return ConditionProgram::compile($rule)->evaluate(static function (string $path) use ($get, $record): mixed {
            [$source, $field] = explode('.', $path, 2);

            return $source === 'relation' ? LoadedRelationReader::read($record, $field) : $get($field);
        });
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $rule */
    private static function compile(array $rule, string $flexStatePathPrefix, ?Model $configuredRecord): Closure
    {
        $program = ConditionProgram::compile($rule);

        return static fn (Get $get, ?Model $record = null): bool => $program->evaluate(
            static function (string $path) use ($get, $flexStatePathPrefix, $record, $configuredRecord): mixed {
                [$source, $field] = explode('.', $path, 2);

                return match ($source) {
                    'model' => $get($field),
                    'relation' => LoadedRelationReader::read($record ?? $configuredRecord, $field),
                    default => $get($flexStatePathPrefix !== '' ? $flexStatePathPrefix.'.'.$field : $field),
                };
            },
        );
    }
}
