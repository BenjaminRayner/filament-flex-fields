<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Portable, value-free build metadata. It contains no host URLs or source roots. */
final readonly class AssetManifest
{
    /** @var array<string, AssetArtifact> */
    public array $artifacts;

    /** @var array<string, string> */
    public array $entries;

    /** @var array<string, string> */
    public array $styles;

    /** @var array<string, string> */
    public array $helpers;

    public const FORMAT = 1;

    public const MAX_JSON_BYTES = 4194304;

    public string $release;

    /**
     * @param  array<string, AssetArtifact>  $artifacts
     * @param  array<string, string>  $entries  Logical Alpine/module entry => artifact ID.
     * @param  array<string, string>  $styles  Logical owned stylesheet => artifact ID.
     * @param  array<string, string>  $helpers  Logical SSR helper => classic script artifact ID.
     */
    public function __construct(
        public string $package,
        array $artifacts,
        array $entries = [],
        array $styles = [],
        array $helpers = [],
    ) {
        $this->artifacts = ArraySnapshot::copy($artifacts);
        $this->entries = ArraySnapshot::copy($entries);
        $this->styles = ArraySnapshot::copy($styles);
        $this->helpers = ArraySnapshot::copy($helpers);

        if (strlen($package) > 192 || preg_match('~^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?$~D', $package) !== 1) {
            throw new InvalidArgumentException('Asset manifest requires a Composer package identity.');
        }
        ArtifactPath::assert($package);
        if ($artifacts === [] || array_is_list($artifacts) || count($artifacts) > 4096) {
            throw new InvalidArgumentException('Asset manifest requires 1–4096 named build outputs.');
        }
        $paths = [];
        $edges = 0;
        foreach ($artifacts as $id => $artifact) {
            ManifestIdentity::assert($id);
            if (! $artifact instanceof AssetArtifact) {
                throw new InvalidArgumentException('Asset manifest requires typed artifacts.');
            }
            // Case-insensitive collisions are rejected for portable publication.
            $key = strtolower($artifact->path);
            if (isset($paths[$key])) {
                throw new InvalidArgumentException('Multiple artifacts target the same portable path: '.$artifact->path);
            }
            $paths[$key] = true;
            $edges += count($artifact->imports) + count($artifact->dynamicImports) + count($artifact->resources);
        }
        if ($edges > 32768) {
            throw new InvalidArgumentException('Asset manifest exceeds the dependency-edge budget.');
        }
        $directories = [];
        foreach ($artifacts as $artifact) {
            $parent = dirname($artifact->path);
            while ($parent !== '.') {
                $key = strtolower($parent);
                if (isset($paths[$key]) || (isset($directories[$key]) && $directories[$key] !== $parent)) {
                    throw new InvalidArgumentException('Artifact file/directory or directory-case collision: '.$artifact->path);
                }
                $directories[$key] = $parent;
                $parent = dirname($parent);
            }
        }
        $this->assertRoots($entries, ArtifactKind::Module);
        $this->assertRoots($styles, ArtifactKind::Stylesheet);
        $this->assertRoots($helpers, ArtifactKind::Script);
        $this->assertGraph();
        $this->release = hash('sha256', json_encode($this->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function artifact(string $id): AssetArtifact
    {
        return $this->artifacts[$id] ?? throw new InvalidArgumentException('Unknown artifact: '.$this->package.' -> '.$id);
    }

    /**
     * Static module/CSS closure only. Workers, lazy modules and binary resources
     * remain published dependencies; they do not become eager modulepreloads.
     * Native ESM cycles are valid and do not cause recursion or duplicate entries.
     *
     * @return list<string>
     */
    public function eagerArtifactsFor(string $entry): array
    {
        $root = $this->entries[$entry] ?? throw new InvalidArgumentException('Unknown manifest entry: '.$entry);
        $queue = [$root];
        $seen = [];
        for ($index = 0; $index < count($queue); $index++) {
            $id = $queue[$index];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $artifact = $this->artifacts[$id];
            foreach ($artifact->imports as $dependency) {
                $queue[] = $dependency;
            }
            foreach ($artifact->resources as $dependency) {
                if ($this->artifacts[$dependency]->type === ArtifactKind::Stylesheet) {
                    $queue[] = $dependency;
                }
            }
        }

        return array_keys($seen);
    }

    /** @return array<string, mixed> */
    public function export(): array
    {
        return [...$this->payload(), 'release' => $this->release];
    }

    public function toJson(): string
    {
        $json = json_encode($this->export(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Compiled manifest exceeds the 4 MiB metadata budget.');
        }

        return $json;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $artifacts = [];
        foreach ($this->artifacts as $id => $artifact) {
            $artifacts[$id] = $artifact->export();
        }
        ksort($artifacts, SORT_STRING);
        $entries = $this->entries;
        $styles = $this->styles;
        $helpers = $this->helpers;
        ksort($entries, SORT_STRING);
        ksort($styles, SORT_STRING);
        ksort($helpers, SORT_STRING);

        return ['format' => self::FORMAT, 'package' => $this->package, 'artifacts' => (object) $artifacts,
            'entries' => (object) $entries, 'styles' => (object) $styles, 'helpers' => (object) $helpers];
    }

    /** @param array<string, string> $roots */
    private function assertRoots(array $roots, ArtifactKind $type): void
    {
        if (($roots !== [] && array_is_list($roots)) || count($roots) > 1024) {
            throw new InvalidArgumentException('Manifest roots need unique, bounded logical identities.');
        }
        foreach ($roots as $id => $target) {
            ManifestIdentity::assert($id);
            ManifestIdentity::assert($target);
            if ($this->artifact($target)->type !== $type) {
                throw new InvalidArgumentException('Wrong artifact type for manifest root: '.$this->package.' -> '.$id.' -> '.$target);
            }
        }
        if (count(array_unique($roots, SORT_STRING)) !== count($roots)) {
            throw new InvalidArgumentException('A manifest artifact cannot have multiple root aliases.');
        }
    }

    private function assertGraph(): void
    {
        foreach ($this->artifacts as $id => $artifact) {
            foreach (['imports', 'dynamicImports', 'resources'] as $edge) {
                foreach ($artifact->{$edge} as $dependency) {
                    $target = $this->artifacts[$dependency] ?? throw new InvalidArgumentException('Missing manifest dependency: '.$this->package.' -> '.$id.' -> '.$edge.' -> '.$dependency);
                    $valid = match ($edge) {
                        'imports' => match ($artifact->type) {
                            ArtifactKind::Module, ArtifactKind::Worker => $target->type === ArtifactKind::Module,
                            ArtifactKind::Stylesheet => $target->type === ArtifactKind::Stylesheet,
                            default => false,
                        },
                        'dynamicImports' => in_array($artifact->type, [ArtifactKind::Module, ArtifactKind::Worker], true) && $target->type === ArtifactKind::Module,
                        default => ! in_array($target->type, [ArtifactKind::Module, ArtifactKind::Script], true),
                    };
                    if (! $valid) {
                        throw new InvalidArgumentException('Invalid manifest dependency type: '.$this->package.' -> '.$id.' -> '.$edge.' -> '.$dependency);
                    }
                }
            }
        }
        $queue = [...array_values($this->entries), ...array_values($this->styles), ...array_values($this->helpers)];
        $seen = [];
        for ($index = 0; $index < count($queue); $index++) {
            $id = $queue[$index];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $artifact = $this->artifacts[$id];
            foreach ([...$artifact->imports, ...$artifact->dynamicImports, ...$artifact->resources] as $dependency) {
                $queue[] = $dependency;
            }
        }
        if (count($seen) !== count($this->artifacts)) {
            $unreachable = array_keys(array_diff_key($this->artifacts, $seen));
            throw new InvalidArgumentException('Unreachable manifest artifacts ('.count($unreachable).'): '.implode(', ', array_slice($unreachable, 0, 8)));
        }
    }
}
