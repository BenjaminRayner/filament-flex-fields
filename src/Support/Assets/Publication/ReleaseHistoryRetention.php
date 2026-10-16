<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Closure;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

/** One verified retention algorithm for builtin and portable release histories. */
final class ReleaseHistoryRetention
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /** @param Closure(string, string): ReleaseInventory $readInventory
     * @param  list<string>  $protected
     * @return list<array{release: string, publishedAt: int, protected: bool, eligible: bool, removed: bool}>
     */
    public function run(string $publicRoot, string $relativeDirectory, string $current, Closure $readInventory, int $keep = 3, int $minimumAge = 2592000, array $protected = [], bool $prune = false): array
    {
        if ($keep < 1 || $keep > 256 || $minimumAge < 86400 || $minimumAge > 315360000 || count($protected) > 256) {
            throw new InvalidArgumentException('Invalid release retention policy. Keep at least one release and a one-day grace period.');
        }
        foreach ([$current, ...$protected] as $release) {
            if (preg_match('/^[a-f0-9]{64}$/D', $release) !== 1) {
                throw new InvalidArgumentException('Protected release must be a SHA-256 identity.');
            }
        }

        return PublicationLock::run($publicRoot, function (string $root) use ($relativeDirectory, $current, $readInventory, $keep, $minimumAge, $protected, $prune): array {
            $publisher = new ReleaseDirectoryPublisher($this->filesystem);
            $directory = $publisher->destination($root, $relativeDirectory);
            if (! is_dir($directory)) {
                if ($prune) {
                    throw new RuntimeException('Publish the current sealed release before pruning history.');
                }

                return [];
            }
            $rows = [];
            foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
                if (! $entry instanceof \SplFileInfo) {
                    throw new RuntimeException('Unexpected release directory iterator value.');
                }
                $release = $entry->getFilename();
                if (preg_match('/^[a-f0-9]{64}$/D', $release) !== 1) {
                    continue;
                }
                if ($entry->isLink() || ! $entry->isDir() || count($rows) >= 4096) {
                    throw new RuntimeException('Unsafe or excessive release history.');
                }
                $inventory = $readInventory($entry->getPathname(), $release);
                $publisher->verify($inventory, $entry->getPathname());
                $modified = filemtime($entry->getPathname().'/.release.json');
                if ($modified === false) {
                    throw new RuntimeException('Cannot read release publication time.');
                }
                $rows[] = ['release' => $release, 'publishedAt' => $modified, 'protected' => in_array($release, [$current, ...$protected], true), 'eligible' => false, 'removed' => false];
            }
            usort($rows, static fn (array $left, array $right): int => ($right['publishedAt'] <=> $left['publishedAt']) ?: strcmp($left['release'], $right['release']));
            if ($prune && ! in_array($current, array_column($rows, 'release'), true)) {
                throw new RuntimeException('Publish the current sealed release before pruning history.');
            }
            $threshold = time() - $minimumAge;
            // All releases are verified before the first deletion. A tampered
            // history cannot cause unrelated valid releases to be pruned first.
            foreach ($rows as $index => &$row) {
                $row['eligible'] = $index >= $keep && ! $row['protected'] && $row['publishedAt'] <= $threshold;
                if ($prune && $row['eligible']) {
                    if (! $this->filesystem->deleteDirectory($directory.'/'.$row['release'])) {
                        throw new RuntimeException('Cannot remove an eligible release.');
                    }
                    $row['removed'] = true;
                }
            }
            unset($row);

            return $rows;
        });
    }
}
