<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

use InvalidArgumentException;

/** Static directory owned by one component. Never accepts a public path outside package namespaces. */
final readonly class PublishedResource
{
    public function __construct(public string $id, public string $sourceDirectory, public string $publicDirectory)
    {
        if (! preg_match('/^[a-z][a-z0-9-]{0,95}$/D', $id)) {
            throw new InvalidArgumentException('Invalid resource identity.');
        }
        if ($sourceDirectory === '' || str_contains($sourceDirectory, "\0")) {
            throw new InvalidArgumentException('Invalid resource source.');
        }
        if (! preg_match('~^(?:filament-flex-fields-assets/|js/janczakb/filament-flex-fields/)[a-zA-Z0-9_./-]+$~D', $publicDirectory)
            || in_array('..', explode('/', $publicDirectory), true) || in_array('.', explode('/', $publicDirectory), true)) {
            throw new InvalidArgumentException('Published resources must stay in a package public namespace.');
        }
    }

    public function sourcePath(): string
    {
        return str_starts_with($this->sourceDirectory, '/')
            ? $this->sourceDirectory
            : dirname(__DIR__, 3).'/'.$this->sourceDirectory;
    }
}
