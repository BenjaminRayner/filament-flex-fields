<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use RuntimeException;

/** Scoped immutable metadata. Validation does not read module bytes. */
final class AlpineBuildManifest
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly ?string $path = null) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $data = BuildMetadataReader::read($this->path ?? dirname(__DIR__, 4).'/resources/dist/components/alpine-manifest.json');
        if (($data['__format__'] ?? null) !== 2) {
            throw new RuntimeException('Unsupported Alpine build manifest format. Rebuild all FLEX assets.');
        }
        foreach (array_keys($data) as $key) {
            if (! is_string($key)) {
                throw new RuntimeException('Alpine build metadata keys must be names.');
            }
        }
        $entries = $this->names($data['__entries__'] ?? null, false);
        $chunks = $this->names($data['__shared_chunks__'] ?? null, true);
        $files = array_fill_keys([...array_map(static fn (string $entry): string => $entry.'.js', $entries), ...$chunks], true);
        if ($entries === [] || count($files) !== count($entries) + count($chunks) || count($files) > 4096) {
            throw new RuntimeException('Duplicate Alpine roots or excessive module budget.');
        }
        $actualEntries = array_values(array_filter(array_keys($data), static fn (string $key): bool => ! str_starts_with($key, '__')));
        sort($actualEntries);
        $expectedEntries = $entries;
        sort($expectedEntries);
        if ($actualEntries !== $expectedEntries) {
            throw new RuntimeException('Alpine entry map differs from its declared roots.');
        }
        $metadataKeys = array_values(array_filter(array_keys($data), static fn (string $key): bool => str_starts_with($key, '__')));
        if (count($metadataKeys) !== 6 || array_diff($metadataKeys, ['__format__', '__entries__', '__shared_chunks__', '__chunk_imports__', '__dynamic_imports__', '__chunk_modules__']) !== []) {
            throw new RuntimeException('Unknown Alpine build metadata keys.');
        }
        $edgeCount = 0;
        foreach (['__chunk_imports__', '__dynamic_imports__'] as $key) {
            $graph = $data[$key] ?? null;
            if (! is_array($graph) || count($graph) !== count($files) || array_diff(array_keys($graph), array_keys($files)) !== []) {
                throw new RuntimeException('Incomplete Alpine import graph: '.$key);
            }
            foreach ($graph as $edges) {
                $edges = $this->names($edges, true);
                $edgeCount += count($edges);
                if ($edgeCount > 32768) {
                    throw new RuntimeException('Unknown Alpine dependency or excessive import edge budget.');
                }
                foreach ($edges as $edge) {
                    if (! isset($files[$edge])) {
                        throw new RuntimeException('Unknown Alpine dependency: '.$edge);
                    }
                }
            }
        }
        foreach ($entries as $entry) {
            if (array_diff($this->names($data[$entry], true), $chunks) !== []) {
                throw new RuntimeException('Unknown eager dependency for Alpine entry: '.$entry);
            }
        }
        $modules = $data['__chunk_modules__'] ?? null;
        if (! is_array($modules) || count($modules) !== count($chunks) || array_diff(array_keys($modules), $chunks) !== []) {
            throw new RuntimeException('Incomplete Alpine source-module projection.');
        }
        $moduleCount = 0;
        foreach ($modules as $names) {
            if (! is_array($names) || ! array_is_list($names)) {
                throw new RuntimeException('Invalid Alpine source-module projection.');
            }
            $moduleCount += count($names);
            if ($moduleCount > 32768) {
                throw new RuntimeException('Excessive Alpine source-module projection.');
            }
            foreach ($names as $name) {
                if (! is_string($name) || strlen($name) > 512) {
                    throw new RuntimeException('Invalid Alpine source-module name.');
                }
            }
        }

        return $this->data = $data;
    }

    /** One byte-identical asset is consumed by blocking SSR and native ESM. */
    public function firstPaintFile(string $name): string
    {
        $source = match ($name) {
            'segment' => 'core/segment-overflow-ssr.js',
            'timezone' => 'core/timezone-browser-ssr-entry.js',
            'readiness' => 'core/scoped-style-readiness-ssr.js',
            default => throw new RuntimeException('Unknown first-paint contract.'),
        };
        $data = $this->data();
        $matches = [];
        foreach ($data['__chunk_modules__'] as $file => $modules) {
            if (in_array($source, $modules, true)) {
                $matches[] = $file;
            }
        }
        if (count($matches) !== 1 || preg_match('/^flex-fields-first-paint-'.preg_quote($name, '/').'-[a-f0-9]{64}\\.js$/D', $matches[0]) !== 1) {
            throw new RuntimeException('Missing or ambiguous first-paint owner; rebuild FLEX assets.');
        }

        return $matches[0];
    }

    /** @return list<string> */
    private function names(mixed $names, bool $module): array
    {
        if (! is_array($names) || ! array_is_list($names) || count($names) > 4096) {
            throw new RuntimeException('Alpine names must be bounded lists.');
        }
        foreach ($names as $name) {
            if (! is_string($name) || str_contains($name, '/') || ($module && ! str_ends_with($name, '.js'))
                || (! $module && preg_match('/^[a-z][a-z0-9-]*$/D', $name) !== 1)) {
                throw new RuntimeException('Invalid Alpine build module name.');
            }
            ArtifactPath::assert($name);
        }
        if (count(array_unique($names)) !== count($names)) {
            throw new RuntimeException('Duplicate Alpine build module name.');
        }

        return $names;
    }
}
