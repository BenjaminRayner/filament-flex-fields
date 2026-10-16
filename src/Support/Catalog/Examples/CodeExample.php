<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Examples;

use InvalidArgumentException;

/** A lightweight source reference. Construction performs no IO and never executes PHP. */
final readonly class CodeExample
{
    public function __construct(public string $path, public ?string $label = null)
    {
        if ((! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) && ! str_starts_with($path, '\\\\')) || str_contains($path, "\0") || strlen($path) > 2048 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            throw new InvalidArgumentException('An example must reference an absolute local PHP file.');
        }
        if ($label !== null && (trim($label) === '' || mb_strlen($label) > 80)) {
            throw new InvalidArgumentException('Invalid example label.');
        }
    }

    public static function php(string $path, ?string $label = null): self
    {
        return new self($path, $label);
    }
}
