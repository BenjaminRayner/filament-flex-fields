<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Examples;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalSharedCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use RuntimeException;

/** Detail-page service. Discovery and form asset planning never resolve this reader. */
final class CodeExampleRepository
{
    public const MAX_BYTES = 131072;

    public const MAX_LINES = 2000;

    public function __construct(private readonly ComponentRegistry $components, private readonly PhpSyntaxHighlighter $highlighter) {}

    /** @return list<array{key: string, label: string, source?: string, lines?: list<string>, unavailable: bool}> */
    public function forComponent(string $id): array
    {
        if (! $this->components->has($id)) {
            return [];
        }
        $result = [];
        $catalog = $this->components->definition($id)->catalog;
        if ($catalog === null) {
            return [];
        }
        $references = $catalog->examples;
        $paths = array_fill_keys(array_map(static fn (CodeExample $reference): string => $reference->path, $references), true);
        foreach ($this->components->all() as $definition) {
            $related = $definition->catalog;
            if ($related?->groupedWith !== $id) {
                continue;
            }
            foreach ($related->examples as $reference) {
                if (isset($paths[$reference->path])) {
                    continue;
                }
                $paths[$reference->path] = true;
                $references[] = CodeExample::php($reference->path, mb_substr(CatalogValue::localized($related->label).' · '.($reference->label ?? basename($reference->path)), 0, 80));
            }
        }
        foreach ($references as $index => $reference) {
            try {
                $result[] = ['key' => (string) $index, 'label' => $reference->label ?? basename($reference->path), ...$this->read($reference)];
            } catch (RuntimeException $exception) {
                // Do not expose local paths or source content through an error message.
                $result[] = ['key' => (string) $index, 'label' => $reference->label ?? basename($reference->path), 'unavailable' => true];
            }
        }

        return $result;
    }

    /** @return array{source: string, lines: list<string>, unavailable: false} */
    public function read(CodeExample $reference): array
    {
        $handle = @fopen($reference->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Example file is unavailable.');
        }
        try {
            $stat = fstat($handle);
            if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || $stat['size'] > self::MAX_BYTES) {
                throw new RuntimeException('Invalid example file.');
            }
            $source = stream_get_contents($handle, self::MAX_BYTES + 1);
        } finally {
            fclose($handle);
        }
        if ($source === false || strlen($source) > self::MAX_BYTES || ! mb_check_encoding($source, 'UTF-8')) {
            throw new RuntimeException('Invalid example source.');
        }
        $source = rtrim(str_replace(["\r\n", "\r"], "\n", $source), "\n");
        if (substr_count($source, "\n") + 1 > self::MAX_LINES) {
            throw new RuntimeException('Example has too many lines.');
        }
        // Content identity prevents stale output even when edits preserve mtime and size.
        $lines = RequestState::resolve(OptionalSharedCache::class)->remember('flex.examples.php.json-v2.'.hash('sha256', $source), 3600,
            fn (): array => $this->highlighter->lines($source), static function (mixed $value): bool {
                if (! is_array($value) || ! array_is_list($value) || count($value) > self::MAX_LINES) {
                    return false;
                }
                foreach ($value as $line) {
                    if (! is_string($line)) {
                        return false;
                    }
                }

                return true;
            });

        return ['source' => $source, 'lines' => $lines, 'unavailable' => false];
    }
}
