<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use InvalidArgumentException;
use RuntimeException;

/** Build/preflight compiler. Loading metadata never hashes component bytes. */
final class AssetManifestCompiler
{
    public function load(string $path): AssetManifest
    {
        $data = $this->readJson($path);
        $this->assertKeys($data, ['format', 'package', 'artifacts', 'entries', 'styles', 'helpers', 'release'], 'manifest');
        if (! is_string($data['release']) || preg_match('/^[a-f0-9]{64}$/D', $data['release']) !== 1) {
            throw new InvalidArgumentException('Manifest requires a release fingerprint.');
        }
        $manifest = $this->parse($data, false);
        if (! hash_equals($manifest->release, $data['release'])) {
            throw new InvalidArgumentException('Manifest metadata differs from its release fingerprint.');
        }

        return $manifest;
    }

    /**
     * The build specification declares all graph edges; opaque Worker()/URL()
     * constructions are not guessed by scanning source text. A bundler adapter
     * must provide those edges before this compiler computes content identities.
     */
    public function build(string $specificationPath, string $sourceRoot): AssetManifest
    {
        $data = $this->readJson($specificationPath);
        $this->assertKeys($data, ['format', 'package', 'artifacts', 'entries', 'styles', 'helpers'], 'build specification');
        $draft = $this->parse($data, true);
        $root = ArtifactPath::root($sourceRoot);
        $artifacts = [];
        foreach ($draft->artifacts as $id => $artifact) {
            $source = ArtifactPath::source($root, $artifact->path);
            clearstatcache(true, $source);
            $digest = hash_file('sha256', $source);
            $bytes = filesize($source);
            if ($digest === false || $bytes === false) {
                throw new RuntimeException('Cannot read build output: '.$artifact->path);
            }
            $artifacts[$id] = new AssetArtifact($artifact->path, $artifact->type, $digest, $bytes,
                $artifact->imports, $artifact->dynamicImports, $artifact->resources);
        }

        return new AssetManifest($draft->package, $artifacts, $draft->entries, $draft->styles, $draft->helpers);
    }

    /** Full bytes verification belongs to publish/doctor, never the HTTP render path. */
    public function verifySources(AssetManifest $manifest, string $sourceRoot): void
    {
        $root = ArtifactPath::root($sourceRoot);
        foreach ($manifest->artifacts as $id => $artifact) {
            $source = ArtifactPath::source($root, $artifact->path);
            clearstatcache(true, $source);
            if (filesize($source) !== $artifact->bytes || hash_file('sha256', $source) !== $artifact->sha256) {
                throw new RuntimeException('Artifact differs from its manifest: '.$manifest->package.' -> '.$id.' -> '.$artifact->path);
            }
        }
    }

    /** @return array<array-key, mixed> */
    private function readJson(string $path): array
    {
        // Read at most the budget plus one; do not trust a previous filesize().
        $json = is_file($path) && is_readable($path) ? file_get_contents($path, false, null, 0, AssetManifest::MAX_JSON_BYTES + 1) : false;
        if (! is_string($json) || strlen($json) > AssetManifest::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Manifest is missing, unreadable or exceeds the 4 MiB metadata budget.');
        }
        $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('Manifest must be a JSON object.');
        }

        return $data;
    }

    /** @param array<array-key, mixed> $data */
    private function parse(array $data, bool $draft): AssetManifest
    {
        if ($data['format'] !== AssetManifest::FORMAT || ! is_string($data['package']) || ! is_array($data['artifacts']) || count($data['artifacts']) > 4096) {
            throw new InvalidArgumentException('Unsupported manifest format or artifact budget.');
        }
        $artifacts = [];
        foreach ($data['artifacts'] as $id => $record) {
            ManifestIdentity::assert($id);
            if (! is_array($record)) {
                throw new InvalidArgumentException('Invalid manifest artifact: '.$id);
            }
            $keys = ['path', 'type', 'imports', 'dynamicImports', 'resources'];
            $this->assertKeys($record, $draft ? $keys : [...$keys, 'sha256', 'bytes'], 'artifact '.$id);
            $type = is_string($record['type']) ? ArtifactKind::tryFrom($record['type']) : null;
            if (! is_string($record['path']) || $type === null
                || ! is_array($record['imports']) || ! is_array($record['dynamicImports']) || ! is_array($record['resources'])
                || (! $draft && (! is_string($record['sha256']) || ! is_int($record['bytes'])))) {
                throw new InvalidArgumentException('Invalid typed artifact metadata: '.$id);
            }
            ManifestIdentity::assertList($record['imports']);
            ManifestIdentity::assertList($record['dynamicImports']);
            ManifestIdentity::assertList($record['resources']);
            $artifacts[$id] = new AssetArtifact($record['path'], $type, $draft ? str_repeat('0', 64) : $record['sha256'],
                $draft ? 0 : $record['bytes'], $record['imports'], $record['dynamicImports'], $record['resources']);
        }
        foreach (['entries', 'styles', 'helpers'] as $map) {
            if (! is_array($data[$map])) {
                throw new InvalidArgumentException('Manifest roots must be maps: '.$map);
            }
        }

        return new AssetManifest($data['package'], $artifacts, $data['entries'], $data['styles'], $data['helpers']);
    }

    /** @param array<array-key, mixed> $data
     * @param  list<string>  $keys
     */
    private function assertKeys(array $data, array $keys, string $context): void
    {
        if (count($data) !== count($keys) || array_diff($keys, array_keys($data)) !== []) {
            throw new InvalidArgumentException('Unknown or missing metadata keys in '.$context.'.');
        }
    }
}
