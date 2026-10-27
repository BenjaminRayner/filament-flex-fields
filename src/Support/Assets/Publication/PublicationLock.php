<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/** Root-wide lock shared by builtin/package publication and explicit retention. */
final class PublicationLock
{
    /** @template T
     * @param  Closure(string): T  $operation
     * @return T
     */
    public static function run(string $publicRoot, Closure $operation, int $waitMilliseconds = 30000): mixed
    {
        if ($waitMilliseconds < 10 || $waitMilliseconds > 300000) {
            throw new InvalidArgumentException('Publication lock wait must be between 10 and 300000 milliseconds.');
        }
        $root = ArtifactPath::root($publicRoot);
        $metadata = $root.DIRECTORY_SEPARATOR.'filament-flex-fields-assets';
        if (is_link($metadata)) {
            throw new RuntimeException('Unsafe publication metadata directory.');
        }
        if (! is_dir($metadata) && ! @mkdir($metadata, 0755, true) && ! is_dir($metadata)) {
            throw new RuntimeException('Cannot create publication metadata directory.');
        }
        if (realpath($metadata) !== $metadata) {
            throw new RuntimeException('Unsafe publication metadata path.');
        }
        foreach (['.publish.lock', '.published.json'] as $file) {
            $path = $metadata.'/'.$file;
            if (is_link($path) || (file_exists($path) && ! is_file($path))) {
                throw new RuntimeException('Unsafe publication metadata file.');
            }
        }
        $lock = fopen($metadata.'/.publish.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open publication lock.');
        }
        $deadline = hrtime(true) + $waitMilliseconds * 1000000;
        while (! flock($lock, LOCK_EX | LOCK_NB)) {
            if (hrtime(true) >= $deadline) {
                fclose($lock);
                throw new RuntimeException('Publication lock acquisition timed out; another writer may still be active.');
            }
            usleep(10000);
        }
        try {
            return $operation($root);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
