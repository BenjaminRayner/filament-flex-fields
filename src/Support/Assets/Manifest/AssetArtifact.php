<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** One immutable build output. Hashes describe bytes, not trust in a package author. */
final readonly class AssetArtifact
{
    /** @var list<string> */
    public array $imports;

    /** @var list<string> */
    public array $dynamicImports;

    /** @var list<string> */
    public array $resources;

    /**
     * @param  list<string>  $imports  Static module/CSS imports, in build order.
     * @param  list<string>  $dynamicImports  Lazy module imports; never eager preloads.
     * @param  list<string>  $resources  Worker, font, WASM and other linked outputs.
     */
    public function __construct(
        public string $path,
        public ArtifactKind $type,
        public string $sha256,
        public int $bytes,
        array $imports = [],
        array $dynamicImports = [],
        array $resources = [],
    ) {
        $this->imports = ArraySnapshot::copy($imports);
        $this->dynamicImports = ArraySnapshot::copy($dynamicImports);
        $this->resources = ArraySnapshot::copy($resources);

        ArtifactPath::assert($path);
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $type->extensions(), true)) {
            throw new InvalidArgumentException('Artifact type does not match its build output: '.$path);
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1 || $bytes < 0) {
            throw new InvalidArgumentException('Artifact needs a SHA-256 and a nonnegative byte count: '.$path);
        }
        foreach ([$imports, $dynamicImports, $resources] as $edges) {
            ManifestIdentity::assertList($edges);
        }
    }

    /** @return array{path: string, type: string, sha256: string, bytes: int, imports: list<string>, dynamicImports: list<string>, resources: list<string>} */
    public function export(): array
    {
        return ['path' => $this->path, 'type' => $this->type->value, 'sha256' => $this->sha256, 'bytes' => $this->bytes,
            'imports' => $this->imports, 'dynamicImports' => $this->dynamicImports, 'resources' => $this->resources];
    }
}
