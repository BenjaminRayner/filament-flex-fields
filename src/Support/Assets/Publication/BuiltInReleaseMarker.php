<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use RuntimeException;

/** Strict readback independent of the currently installed PHP/distribution. */
final class BuiltInReleaseMarker
{
    public static function inventory(string $directory, string $release): ReleaseInventory
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $release) !== 1 || is_link($directory.'/.release.json') || ! is_file($directory.'/.release.json')) {
            throw new RuntimeException('Invalid core release marker path.');
        }
        $json = file_get_contents($directory.'/.release.json', false, null, 0, 4194305);
        if ($json === false || strlen($json) > 4194304) {
            throw new RuntimeException('Missing or oversized core release marker.');
        }
        $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($data) || array_keys($data) !== ['format', 'kind', 'release', 'assets', 'bytes', 'metadata']
            || $data['format'] !== 1 || $data['kind'] !== 'core' || $data['release'] !== $release
            || ! is_array($data['assets']) || ! is_array($data['bytes']) || ! is_array($data['metadata'])
            || count($data['assets']) !== count($data['bytes'])
            || array_keys($data['metadata']) !== ['resources/dist/components/alpine-manifest.json', 'src/Support/Assets/BuiltInComponentIndex.php']) {
            throw new RuntimeException('Invalid core release marker.');
        }
        foreach ($data['metadata'] as $hash) {
            if (! is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new RuntimeException('Invalid cross-layer release metadata.');
            }
        }
        $files = [];
        foreach ($data['assets'] as $path => $hash) {
            if (! is_string($path) || ! is_string($hash) || ! isset($data['bytes'][$path]) || ! is_int($data['bytes'][$path])) {
                throw new RuntimeException('Invalid core release artifact metadata.');
            }
            $files[$path] = ['sha256' => $hash, 'bytes' => $data['bytes'][$path]];
        }
        $assets = $data['assets'];
        $bytes = $data['bytes'];
        ksort($assets);
        ksort($bytes);
        $fingerprint = hash('sha256', json_encode(['assets' => $assets, 'bytes' => $bytes, 'metadata' => $data['metadata']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (! hash_equals($release, $fingerprint)) {
            throw new RuntimeException('Core release marker differs from its fingerprint.');
        }

        return new ReleaseInventory($directory, $files, $json);
    }
}
