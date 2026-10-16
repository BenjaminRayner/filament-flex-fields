<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

use DOMDocument;
use JsonException;

/** Bounded structured-data fallback; never execute page scripts or follow @id URLs. */
final class JsonLdMetadata
{
    /** @return array{title?: string, description?: string, image?: string} */
    public function read(DOMDocument $document): array
    {
        $nodes = [];
        $scripts = 0;
        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower(trim(explode(';', $script->getAttribute('type'))[0])) !== 'application/ld+json') {
                continue;
            }
            if (++$scripts > 16) {
                break;
            }
            $json = $script->textContent;
            if (strlen($json) > 65536) {
                continue;
            }
            try {
                $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (! is_array($decoded)) {
                continue;
            }
            $queue = [$decoded];
            for ($cursor = 0; $cursor < count($queue) && $cursor < 128; $cursor++) {
                $node = $queue[$cursor];
                if (array_is_list($node)) {
                    foreach (array_slice($node, 0, 32) as $child) {
                        if (is_array($child) && count($queue) < 128) {
                            $queue[] = $child;
                        }
                    }

                    continue;
                }
                $types = $node['@type'] ?? [];
                $types = is_string($types) ? [$types] : (is_array($types) ? $types : []);
                $rank = 10;
                foreach (['WebPage', 'Article', 'NewsArticle', 'BlogPosting', 'Product', 'VideoObject'] as $priority => $type) {
                    if (in_array($type, $types, true)) {
                        $rank = min($rank, $priority);
                    }
                }
                if ($rank < 10 && count($nodes) < 128) {
                    $nodes[] = [$rank, $node];
                }
                foreach (['@graph', 'mainEntity', 'mainEntityOfPage'] as $key) {
                    if (is_array($node[$key] ?? null) && count($queue) < 128) {
                        $queue[] = $node[$key];
                    }
                }
            }
        }
        usort($nodes, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $result = [];
        foreach ($nodes as [, $node]) {
            $title = MetadataText::first([$node['headline'] ?? null, $node['name'] ?? null], 256);
            $description = MetadataText::clean($node['description'] ?? null, 1024);
            $image = $this->image($node['image'] ?? $node['thumbnailUrl'] ?? $node['primaryImageOfPage'] ?? null);
            if (! isset($result['title']) && $title !== null) {
                $result['title'] = $title;
            }
            if (! isset($result['description']) && $description !== null) {
                $result['description'] = $description;
            }
            if (! isset($result['image']) && $image !== null) {
                $result['image'] = $image;
            }
        }

        return $result;
    }

    private function image(mixed $value, int $depth = 0): ?string
    {
        if ($depth > 4) {
            return null;
        }
        if (is_string($value) && strlen($value) <= 2048 && trim($value) !== '') {
            return trim($value);
        }
        if (! is_array($value)) {
            return null;
        }
        if (array_is_list($value)) {
            foreach (array_slice($value, 0, 16) as $image) {
                if (($candidate = $this->image($image, $depth + 1)) !== null) {
                    return $candidate;
                }
            }

            return null;
        }

        return $this->image($value['contentUrl'] ?? $value['url'] ?? null, $depth + 1);
    }
}
