<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise;

use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\FieldAccessState;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use InvalidArgumentException;

/** Request-local policies; explicit tenant rules override shared rules. */
final class FieldRbacMatrix
{
    public const string ABILITY_VIEW = 'view';

    public const string ABILITY_EDIT = 'edit';

    public const string ABILITY_CONFIGURE = 'configure';

    public static function can(string $userKey, string $ability, string $fieldType, string $scope = ''): bool
    {
        if (! self::validAbility($ability)) {
            return false;
        }

        $rules = self::state()->rules;

        foreach (array_unique([$scope, '']) as $context) {
            foreach (array_unique([$fieldType, '*']) as $field) {
                $decision = $rules[$context][$userKey][$field][$ability] ?? null;

                if ($decision !== null) {
                    return $decision;
                }
            }
        }

        return self::defaultDecision($scope);
    }

    public static function defaultDecision(string $scope = ''): bool
    {
        $defaults = self::state()->defaults;

        return $defaults[$scope] ?? $defaults[''] ?? true;
    }

    /** Configure false for an explicit-grant policy, including anonymous callers. */
    public static function setDefaultDecision(bool $allowed, string $scope = ''): void
    {
        self::state()->defaults[$scope] = $allowed;
    }

    public static function grant(string $userKey, string $ability, string $fieldType, string $scope = ''): void
    {
        self::write($userKey, $ability, $fieldType, $scope, true);
    }

    public static function deny(string $userKey, string $ability, string $fieldType, string $scope = ''): void
    {
        self::write($userKey, $ability, $fieldType, $scope, false);
    }

    public static function reset(?string $userKey = null, ?string $scope = null): void
    {
        $state = self::state();

        if ($userKey === null && $scope === null) {
            $state->rules = [];
            $state->defaults = [];

            return;
        }

        foreach ($scope === null ? array_keys($state->rules) : [$scope] as $context) {
            if ($userKey === null) {
                unset($state->rules[$context], $state->defaults[$context]);
            } else {
                unset($state->rules[$context][$userKey]);
            }
        }
    }

    private static function write(string $user, string $ability, string $field, string $scope, bool $decision): void
    {
        if ($user === '' || $field === '' || ! self::validAbility($ability)) {
            throw new InvalidArgumentException('Field policy requires a user, field type and a supported ability.');
        }

        self::state()->rules[$scope][$user][$field][$ability] = $decision;
    }

    private static function validAbility(string $ability): bool
    {
        return in_array($ability, [self::ABILITY_VIEW, self::ABILITY_EDIT, self::ABILITY_CONFIGURE], true);
    }

    private static function state(): FieldAccessState
    {
        return RequestState::resolve(FieldAccessState::class);
    }
}
