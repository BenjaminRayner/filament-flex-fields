<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use InvalidArgumentException;

/** Trusted boot metadata. Never exported into manifests or rendered asset plans. */
final readonly class DefinitionOrigin
{
    public string $file;

    public function __construct(string $file, public int $line = 1)
    {
        if ($file === '' || strlen($file) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $file)
            || $line < 1 || $line > 2147483647) {
            throw new InvalidArgumentException('A definition origin requires a local file and a positive line number.');
        }
        $canonical = realpath($file);
        if ($canonical === false || ! is_file($canonical) || ! is_readable($canonical)) {
            throw new InvalidArgumentException('A definition origin must identify a readable installed source file.');
        }
        $this->file = $canonical;
    }
}
