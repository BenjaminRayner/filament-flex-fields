<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuildMetadataReader;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\DevelopmentAssetFallback;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactPath;
use RuntimeException;

/** Scoped release seal; full artifact hashing belongs to deployment preflight. */
final class AssetVersionManifest
{
    public const FORMAT = 2;

    /** @var array<string, string>|null */
    private ?array $versions = null;

    /** @var array<string, int> */
    private array $bytes = [];

    /** @var array<string, string> */
    private array $metadata = [];

    private ?string $release = null;

    /** @var array<string, array<string, array{digest: string, size: int, modified: int}>> */
    private array $publicationStamps = [];

    public function __construct(private readonly ?string $packageRoot = null, private readonly bool $allowDevelopmentFallback = true) {}

    public function digestFor(string $relativePath): ?string
    {
        return $this->assets()[$relativePath] ?? null;
    }

    public function bytesFor(string $relativePath): ?int
    {
        $this->assets();

        return $this->bytes[$relativePath] ?? null;
    }

    public function release(): ?string
    {
        $this->assets();

        return $this->release;
    }

    /** @return array<string, string> */
    public function metadata(): array
    {
        $this->assets();

        return $this->metadata;
    }

    public function versionFor(?string $sourcePath): ?string
    {
        if ($sourcePath === null) {
            return null;
        }
        $resolved = realpath($sourcePath);
        $dist = realpath($this->root().'/resources/dist');
        if ($resolved === false || $dist === false || ! str_starts_with($resolved, $dist.DIRECTORY_SEPARATOR)) {
            return null;
        }
        $key = str_replace(DIRECTORY_SEPARATOR, '/', substr($resolved, strlen($dist) + 1));

        $digest = $this->assets()[$key] ?? null;
        if ($digest === null) {
            $exception = new RuntimeException('Distribution asset is absent from the release seal: '.$key);
            if (! $this->allowDevelopmentFallback) {
                throw $exception;
            }
            DevelopmentAssetFallback::report($exception);
        }

        return $digest;
    }

    /** Content identity for compatibility assets, computed by the publisher, never by HTTP. */
    public function publishedVersionFor(string $relativePath): string
    {
        ArtifactPath::assert($relativePath);
        $root = public_path();
        $stamps = $this->publicationStamps[$root] ??= self::readPublicationStamps($root);

        return $stamps[$relativePath]['digest'] ?? throw new RuntimeException(
            'Unpublished FLEX compatibility asset: '.$relativePath.'. Publish assets before serving this release, or register an AssetPackage manifest.',
        );
    }

    /** @return array<string, array{digest: string, size: int, modified: int}> */
    public static function readPublicationStamps(string $publicRoot): array
    {
        $path = $publicRoot.'/filament-flex-fields-assets/.published.json';
        if (! file_exists($path) && ! is_link($path)) {
            return [];
        }

        return self::validatePublicationStamps(BuildMetadataReader::read($path));
    }

    /** @param array<string, array{digest: string, size: int, modified: int}> $stamps */
    public static function encodePublicationStamps(array $stamps): string
    {
        $json = json_encode(self::validatePublicationStamps($stamps), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > BuildMetadataReader::MAX_BYTES) {
            throw new RuntimeException('Compatibility publication metadata exceeds its byte budget.');
        }

        return $json;
    }

    /** @param array<array-key, mixed> $stamps
     * @return array<string, array{digest: string, size: int, modified: int}>
     */
    private static function validatePublicationStamps(array $stamps): array
    {
        if (count($stamps) > 8192) {
            throw new RuntimeException('Compatibility publication metadata exceeds its artifact budget.');
        }
        $result = [];
        foreach ($stamps as $path => $stamp) {
            if (! is_string($path) || ! is_array($stamp) || count($stamp) !== 3
                || ! is_string($stamp['digest'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $stamp['digest']) !== 1
                || ! is_int($stamp['size'] ?? null) || $stamp['size'] < 0 || ! is_int($stamp['modified'] ?? null)) {
                throw new RuntimeException('Invalid compatibility publication metadata.');
            }
            ArtifactPath::assert($path);
            $result[$path] = ['digest' => $stamp['digest'], 'size' => $stamp['size'], 'modified' => $stamp['modified']];
        }

        return $result;
    }

    /** @return array<string, string> */
    public function assets(): array
    {
        if ($this->versions !== null) {
            return $this->versions;
        }
        try {
            $data = BuildMetadataReader::read($this->root().'/resources/dist/asset-versions.json');
            if (count($data) !== 5 || ($data['format'] ?? null) !== self::FORMAT
                || ! is_string($data['release'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $data['release']) !== 1
                || ! is_array($data['assets'] ?? null) || ! is_array($data['bytes'] ?? null) || ! is_array($data['metadata'] ?? null)) {
                throw new RuntimeException('Unsupported or incomplete FLEX release seal. Rebuild all FLEX assets.');
            }
            $assets = $this->digests($data['assets']);
            $metadata = $this->digests($data['metadata']);
            $expectedMetadata = ['resources/dist/components/alpine-manifest.json', 'src/Support/Assets/BuiltInComponentIndex.php'];
            if (array_keys($metadata) !== $expectedMetadata || count($data['bytes']) !== count($assets)) {
                throw new RuntimeException('Release seal is missing cross-layer metadata or byte sizes.');
            }
            $bytes = $data['bytes'];
            ksort($bytes);
            foreach ($assets as $file => $digest) {
                if (! isset($bytes[$file]) || ! is_int($bytes[$file]) || $bytes[$file] < 0) {
                    throw new RuntimeException('Invalid release artifact byte size: '.$file);
                }
            }
            $fingerprint = hash('sha256', json_encode(['assets' => $assets, 'bytes' => $bytes, 'metadata' => $metadata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            if (! hash_equals($fingerprint, $data['release'])) {
                throw new RuntimeException('Release seal fingerprint differs from its metadata.');
            }
            $this->bytes = $bytes;
            $this->metadata = $metadata;
            $this->release = $fingerprint;

            return $this->versions = $assets;
        } catch (RuntimeException $exception) {
            if (! $this->allowDevelopmentFallback) {
                throw $exception;
            }
            DevelopmentAssetFallback::report($exception);

            return $this->versions = [];
        }
    }

    public function root(): string
    {
        return $this->packageRoot ?? dirname(__DIR__, 3);
    }

    /** @param array<array-key, mixed> $values
     * @return array<string, string>
     */
    private function digests(array $values): array
    {
        if ($values === [] || count($values) > 4096) {
            throw new RuntimeException('Release digest map must be nonempty and bounded.');
        }
        $result = [];
        foreach ($values as $file => $digest) {
            if (! is_string($file) || ! is_string($digest) || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
                throw new RuntimeException('Invalid release artifact digest.');
            }
            ArtifactPath::assert($file);
            $result[$file] = $digest;
        }
        ksort($result);

        return $result;
    }
}
