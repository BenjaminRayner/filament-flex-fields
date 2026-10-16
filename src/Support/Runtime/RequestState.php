<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use Closure;
use Fiber;
use LogicException;
use WeakMap;

/** Container-scoped state. Plain PHP callers retain explicitly resettable local state. */
final class RequestState
{
    private static ?StandaloneStateScope $standalone = null;

    /** @var WeakMap<object, StandaloneStateScope>|null */
    private static ?WeakMap $fibers = null;

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public static function resolve(string $class): object
    {
        if (function_exists('app') && app()->bound($class)) {
            return app($class);
        }

        $scope = self::standaloneScope();
        if (isset($scope->states[$class])) {
            /** @var T $existing */
            $existing = $scope->states[$class];

            return $existing;
        }
        if (isset($scope->constructing[$class])) {
            throw new LogicException('Cyclic FLEX standalone state construction.');
        }
        if (count($scope->states) + count($scope->constructing) >= 128) {
            throw new LogicException('FLEX standalone state budget exhausted; reset between operations.');
        }
        $scope->constructing[$class] = true;
        try {
            /** @var T $state */
            $state = $scope->states[$class] = new $class;

            return $state;
        } finally {
            unset($scope->constructing[$class]);
        }
    }

    /**
     * Boot-time registrations are defaults replayed into each new scope. Runtime
     * overrides affect only the current scope and never attach worker listeners.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  Closure(T): void  $configure
     */
    public static function configure(string $class, Closure $configure): void
    {
        $state = self::resolve($class);

        // Validate/apply before retaining a boot default. A rejected callback
        // must not poison every subsequent worker scope through afterResolving.
        $configure($state);

        if (function_exists('app') && app()->bound($class) && ! app()->isBooted()) {
            app()->afterResolving($class, $configure);
        }
    }

    /** Only resets the current standalone context; never flushes Laravel bindings. */
    public static function resetStandalone(?string $class = null): void
    {
        $scope = self::standaloneScope();
        if ($class === null) {
            $scope->states = [];
        } else {
            unset($scope->states[$class]);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public static function withStandaloneScope(Closure $operation): mixed
    {
        $scope = self::standaloneScope();
        if ($scope->depth >= 32) {
            throw new LogicException('FLEX standalone scope nesting budget exhausted.');
        }
        $previous = $scope->states;
        $previousConstructing = $scope->constructing;
        $scope->states = [];
        $scope->constructing = [];
        $scope->depth++;
        try {
            return $operation();
        } finally {
            $scope->states = $previous;
            $scope->constructing = $previousConstructing;
            $scope->depth--;
        }
    }

    private static function standaloneScope(): StandaloneStateScope
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            return self::$standalone ??= new StandaloneStateScope;
        }
        self::$fibers ??= new WeakMap;

        return self::$fibers[$fiber] ??= new StandaloneStateScope;
    }
}
