<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\RichEditor;

use Illuminate\Contracts\Filesystem\Filesystem;
use WeakMap;

/** Request-scoped cache. Disk identity cannot be recycled while an entry is alive. */
class RichEditorAttachmentManifestRepository
{
    /** @var WeakMap<Filesystem, array<string, RichEditorAttachmentManifest|null>> */
    private WeakMap $cache;

    public function __construct()
    {
        $this->cache = new WeakMap;
    }

    public function read(Filesystem $disk, string $masterPath): ?RichEditorAttachmentManifest
    {
        $entries = $this->cache[$disk] ?? [];

        if (array_key_exists($masterPath, $entries)) {
            return $entries[$masterPath];
        }

        $manifestPath = RichEditorAttachmentPaths::manifestPath($masterPath);
        $result = null;

        if ($disk->exists($manifestPath)) {
            $stream = $disk->readStream($manifestPath);
            $json = null;

            if (is_resource($stream)) {
                try {
                    $json = stream_get_contents($stream, 1048577);
                } finally {
                    fclose($stream);
                }
            }

            // Cap the read itself, rather than allocating the entire sidecar first.
            $payload = is_string($json) && strlen($json) <= 1048576 ? json_decode($json, true, 32) : null;
            $result = is_array($payload) ? RichEditorAttachmentManifest::fromArray($payload) : null;
        }

        if (count($entries) >= 256) {
            array_shift($entries);
        }

        $entries[$masterPath] = $result;
        $this->cache[$disk] = $entries;

        return $result;
    }

    public function forget(Filesystem $disk, ?string $masterPath = null): void
    {
        if ($masterPath === null) {
            unset($this->cache[$disk]);

            return;
        }

        $entries = $this->cache[$disk] ?? [];
        unset($entries[$masterPath]);
        $this->cache[$disk] = $entries;
    }
}
