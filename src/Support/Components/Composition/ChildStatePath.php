<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use InvalidArgumentException;

/** Shared deterministic naming for renderers and sibling references, relative to the real parent. */
final class ChildStatePath
{
    public static function for(string $slot, string $key): string
    {
        FamilyRenderContext::assertKey($key);
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $slot) !== 1) {
            throw new InvalidArgumentException('Invalid child state slot.');
        }

        return $slot.'.k_'.hash('sha256', $key);
    }
}
