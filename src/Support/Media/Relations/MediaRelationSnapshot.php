<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use InvalidArgumentException;

/** A revision detects stale state; it is never an authorization token or a file receipt. */
final readonly class MediaRelationSnapshot
{
    /** @var list<string> */
    public array $paths;

    /** @var array<string, string> */
    public array $names;

    /** @var array<string, array<string, mixed>> */
    public array $metadata;

    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $names
     * @param  array<string, array<string, mixed>>  $metadata
     */
    public function __construct(array $paths, array $names, public string $revision, array $metadata = [])
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $revision) || ! array_is_list($paths) || count($paths) > 1000 || count($names) > 1000) {
            throw new InvalidArgumentException('Invalid media relation snapshot.');
        }
        $safePaths = [];
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || strlen($path) > 512) {
                throw new InvalidArgumentException('Invalid media relation path.');
            }
            $safePaths[] = $path;
        }
        $safeNames = [];
        foreach ($names as $path => $name) {
            if (! is_string($path) || ! is_string($name) || strlen($path) > 512 || strlen($name) > 1024) {
                throw new InvalidArgumentException('Invalid media relation original name.');
            }
            $safeNames[$path] = $name;
        }
        $this->paths = $safePaths;
        $this->names = $safeNames;
        $safeMetadata = [];
        if (count($metadata) > 1000) {
            throw new InvalidArgumentException('Invalid media metadata count.');
        }
        foreach ($metadata as $path => $value) {
            if (! is_string($path) || ! in_array($path, $safePaths, true)) {
                throw new InvalidArgumentException('Metadata must belong to a snapshot path.');
            }
            $safeMetadata[$path] = MediaRelationMetadata::normalize($value);
        }
        $this->metadata = $safeMetadata;
    }
}
