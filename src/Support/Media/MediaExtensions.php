<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAdapter;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaScanner;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaExtensionState;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Closure;
use InvalidArgumentException;

/** Register factories during provider boot; tenant services resolve in the current scope. */
final class MediaExtensions
{
    /** @param Closure(): MediaAdapter $factory */
    public static function adapter(string $name, Closure $factory, bool $replace = false): void
    {
        self::register('adapters', $name, $factory, $replace);
    }

    /** @param Closure(): MediaProcessor $factory */
    public static function processor(string $name, Closure $factory, bool $replace = false): void
    {
        self::register('processors', $name, $factory, $replace);
    }

    /** @param Closure(): MediaScanner $factory */
    public static function scanner(string $name, Closure $factory, bool $replace = false): void
    {
        self::register('scanners', $name, $factory, $replace);
    }

    /** @param Closure(): MediaAccessPolicy $factory */
    public static function accessPolicy(string $name, Closure $factory, bool $replace = false): void
    {
        self::register('accessPolicies', $name, $factory, $replace);
    }

    /** @param 'adapters'|'processors'|'scanners'|'accessPolicies' $group */
    private static function register(string $group, string $name, Closure $factory, bool $replace): void
    {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name)) {
            throw new InvalidArgumentException('Invalid Media OS extension identity.');
        }
        RequestState::configure(MediaExtensionState::class, static function (MediaExtensionState $state) use ($group, $name, $factory, $replace): void {
            if ((! $replace && isset($state->{$group}[$name])) || (! isset($state->{$group}[$name]) && count($state->{$group}) >= 64)) {
                throw new InvalidArgumentException('Duplicate Media OS extension or extension budget exceeded: '.$name);
            }
            $state->{$group}[$name] = $factory;
            $state->revision++;
        });
    }
}
