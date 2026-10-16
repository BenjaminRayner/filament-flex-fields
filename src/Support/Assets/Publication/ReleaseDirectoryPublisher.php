<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Caller owns the publication lock. A complete verified directory is the commit. */
final class ReleaseDirectoryPublisher
{
    public function __construct(private readonly Filesystem $filesystem) {}

    public function preflight(ReleaseInventory $inventory, string $root, string $relativeDirectory): void
    {
        $destination = $this->destination($root, $relativeDirectory);
        foreach ($inventory->files as $path => $file) {
            $this->verifyFile(ArtifactPath::source($inventory->sourceRoot, $path), $file);
        }
        if (file_exists($destination)) {
            $this->verify($inventory, $destination);
        }
    }

    /** @return array{copied: int, unchanged: int} */
    public function publish(ReleaseInventory $inventory, string $root, string $relativeDirectory): array
    {
        $destination = $this->destination($root, $relativeDirectory);
        $parent = dirname($destination);
        $this->filesystem->ensureDirectoryExists($parent);
        $this->assertSafeDirectory(ArtifactPath::root($root), $parent);
        // All publishers share the same root lock; abandoned stages cannot belong
        // to a live writer. Final releases and unknown directories are never swept.
        $this->cleanStages($parent);
        if (file_exists($destination)) {
            $this->verify($inventory, $destination);

            return ['copied' => 0, 'unchanged' => count($inventory->files)];
        }
        $stage = $parent.'/.fff-stage-'.bin2hex(random_bytes(12));
        if (! mkdir($stage, 0700)) {
            throw new RuntimeException('Cannot create release staging directory.');
        }
        try {
            foreach ($inventory->files as $path => $file) {
                $target = $stage.'/'.$path;
                $this->filesystem->ensureDirectoryExists(dirname($target));
                if (! $this->filesystem->copy(ArtifactPath::source($inventory->sourceRoot, $path), $target) || ! chmod($target, 0644)) {
                    throw new RuntimeException('Cannot stage verified release artifact.');
                }
                $this->verifyFile($target, $file);
            }
            if (file_put_contents($stage.'/.release.json', $inventory->marker, LOCK_EX) !== strlen($inventory->marker)
                || ! chmod($stage.'/.release.json', 0644)) {
                throw new RuntimeException('Cannot write release commit marker.');
            }
            $this->verify($inventory, $stage);
            $this->assertSafeDirectory(ArtifactPath::root($root), $destination);
            if (file_exists($destination) || ! chmod($stage, 0755) || ! rename($stage, $destination)) {
                throw new RuntimeException('Cannot commit the complete release.');
            }

            return ['copied' => count($inventory->files), 'unchanged' => 0];
        } finally {
            if (is_dir($stage)) {
                $this->removeTree($stage);
            }
        }
    }

    public function verify(ReleaseInventory $inventory, string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            throw new RuntimeException('Unsafe published release.');
        }
        $directory = ArtifactPath::root($directory);
        $expected = ['.release.json' => false];
        foreach (array_keys($inventory->files) as $path) {
            $expected[$path] = false;
            for ($parent = dirname($path); $parent !== '.'; $parent = dirname($parent)) {
                $expected[$parent] = true;
            }
        }
        $found = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if ($file->isLink() || ! array_key_exists($relative, $expected) || $file->isDir() !== $expected[$relative]
                || (! $file->isDir() && ! $file->isFile())) {
                throw new RuntimeException('Unexpected or unsafe published release entry.');
            }
            $found++;
        }
        if ($found !== count($expected) || file_get_contents($directory.'/.release.json', false, null, 0, strlen($inventory->marker) + 1) !== $inventory->marker) {
            throw new RuntimeException('Published release is incomplete or has inconsistent metadata.');
        }
        foreach ($inventory->files as $path => $file) {
            $this->verifyFile(ArtifactPath::source($directory, $path), $file);
        }
    }

    public function destination(string $root, string $relativeDirectory): string
    {
        if (strlen($relativeDirectory) > 1024) {
            throw new RuntimeException('Release publication path exceeds its budget.');
        }
        foreach (explode('/', $relativeDirectory) as $segment) {
            ArtifactPath::assert($segment);
        }
        $root = ArtifactPath::root($root);
        $destination = $root.'/'.$relativeDirectory;
        $this->assertSafeDirectory($root, $destination);

        return $destination;
    }

    private function assertSafeDirectory(string $root, string $destination): void
    {
        if (! str_starts_with($destination, $root.'/')) {
            throw new RuntimeException('Release publication escaped its root.');
        }
        $path = $root;
        foreach (explode('/', substr($destination, strlen($root) + 1)) as $segment) {
            $path .= '/'.$segment;
            if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
                throw new RuntimeException('Unsafe release publication directory.');
            }
        }
    }

    /** @param array{sha256: string, bytes: int} $file */
    private function verifyFile(string $path, array $file): void
    {
        clearstatcache(true, $path);
        if (filesize($path) !== $file['bytes'] || hash_file('sha256', $path) !== $file['sha256']) {
            throw new RuntimeException('Release artifact differs from its sealed hash or byte size.');
        }
    }

    private function cleanStages(string $parent): void
    {
        $stages = [];
        $scanned = 0;
        foreach (new \FilesystemIterator($parent, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if (! $entry instanceof \SplFileInfo || ++$scanned > 8192) {
                throw new RuntimeException('Release parent exceeds the recovery scan budget.');
            }
            if (str_starts_with($entry->getFilename(), '.fff-stage-')) {
                $stages[] = $entry->getPathname();
                if (count($stages) > 256) {
                    throw new RuntimeException('Release staging directory exceeds the recovery budget.');
                }
            }
        }
        foreach ($stages as $stage) {
            if (preg_match('/^\.fff-stage-[a-f0-9]{24}$/D', basename($stage)) === 1 && is_dir($stage) && ! is_link($stage)) {
                $this->removeTree($stage);
            }
        }
    }

    /** Never follow symlinks, including in a stage abandoned by a killed process. */
    private function removeTree(string $directory): void
    {
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if (! $entry instanceof \SplFileInfo) {
                throw new RuntimeException('Unexpected release directory iterator value.');
            }
            if ($entry->isDir() && ! $entry->isLink()) {
                $this->removeTree($entry->getPathname());
            } elseif (! unlink($entry->getPathname())) {
                throw new RuntimeException('Cannot remove an abandoned release stage entry.');
            }
        }
        if (! rmdir($directory)) {
            throw new RuntimeException('Cannot remove an abandoned release stage.');
        }
    }
}
