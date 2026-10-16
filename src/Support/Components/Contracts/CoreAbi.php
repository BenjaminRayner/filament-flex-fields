<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Contracts;

use InvalidArgumentException;

/** Public integration protocol versions, independent of package/value versions. */
final class CoreAbi
{
    public const PHP = 1;

    public const JS = 1;

    public static function assertCompatible(int $php, int $js): void
    {
        if ($php !== self::PHP || $js !== self::JS) {
            throw new InvalidArgumentException('Unsupported FLEX ABI: PHP '.$php.', JS '.$js.'. Supported: PHP '.self::PHP.', JS '.self::JS.'.');
        }
    }
}
