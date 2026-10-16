<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\UrlMeta;

use Bjanczak\FilamentFlexFields\Support\Http\Html\HeadBoundary;
use Bjanczak\FilamentFlexFields\Support\LinkPreview\LinkPreviewUrl;
use DOMDocument;
use InvalidArgumentException;

/** Single bounded DOM pass, ordered fallbacks and independent image admission. */
final class MetadataParser
{
    public const MAX_HEAD_BYTES = 262144;

    public function __construct(private readonly RedirectUrlResolver $resolver, private readonly JsonLdMetadata $structured) {}

    /** @return array{title?: string, description?: string, image?: string} */
    public function parse(string $html, string $baseUrl): array
    {
        $html = substr($html, 0, self::MAX_HEAD_BYTES);
        $boundary = new HeadBoundary;
        if (($end = $boundary->end($html)) !== null) {
            $html = substr($html, 0, $end);
        }
        $charset = null;
        if (preg_match('/<meta\b[^>]*charset\s*=\s*["\']?\s*([a-zA-Z0-9_-]+)/i', substr($html, 0, 8192), $match)) {
            $charset = $match[1];
        }
        if ($charset !== null && in_array(strtolower($charset), ['iso-8859-1', 'windows-1252'], true)) {
            $converted = mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false) {
                $html = $converted;
            }
        }
        if (! mb_check_encoding($html, 'UTF-8')) {
            $converted = mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
            if ($converted === false) {
                return [];
            }
            $html = $converted;
        }
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $meta = [];
        $images = [];
        $currentImage = null;
        foreach ($document->getElementsByTagName('meta') as $node) {
            $key = strtolower(trim($node->getAttribute('property') ?: $node->getAttribute('name') ?: $node->getAttribute('itemprop')));
            $value = trim($node->getAttribute('content'));
            if ($value === '' || strlen($value) > 4096) {
                continue;
            }
            if (count($meta[$key] ?? []) < 16) {
                $meta[$key][] = $value;
            }
            if (in_array($key, ['og:image', 'og:image:url'], true)) {
                if (count($images) < 32) {
                    $images[] = ['url' => $value];
                    $currentImage = array_key_last($images);
                }
            } elseif ($key === 'og:image:secure_url') {
                if ($currentImage === null && count($images) < 32) {
                    $images[] = [];
                    $currentImage = array_key_last($images);
                }
                if ($currentImage !== null && ! isset($images[$currentImage]['secure'])) {
                    $images[$currentImage]['secure'] = $value;
                }
            }
        }
        $structured = $this->structured->read($document);
        $title = MetadataText::first([...($meta['og:title'] ?? []), ...($meta['twitter:title'] ?? []), $document->getElementsByTagName('title')->item(0)?->textContent, $structured['title'] ?? null], 256);
        $description = MetadataText::first([...($meta['og:description'] ?? []), ...($meta['twitter:description'] ?? []), ...($meta['description'] ?? []), ...($meta['abstract'] ?? []), $structured['description'] ?? null], 1024);
        $base = $baseUrl;
        $baseHref = $document->getElementsByTagName('base')->item(0)?->getAttribute('href');
        if (is_string($baseHref) && ($candidate = $this->imageUrl($baseUrl, $baseHref)) !== null) {
            $base = $candidate;
        }
        $candidates = [];
        foreach ($images as $image) {
            if (isset($image['secure'])) {
                $candidates[] = $image['secure'];
            }
            if (isset($image['url'])) {
                $candidates[] = $image['url'];
            }
        }
        $candidates = [...$candidates, ...($meta['twitter:image'] ?? []), ...($meta['twitter:image:src'] ?? []), ...($meta['image'] ?? []), $structured['image'] ?? null];
        $links = [];
        foreach ($document->getElementsByTagName('link') as $node) {
            $relations = preg_split('/\s+/', strtolower(trim($node->getAttribute('rel')))) ?: [];
            foreach (['image_src', 'apple-touch-icon', 'icon'] as $rank => $relation) {
                if (in_array($relation, $relations, true) && count($links) < 32) {
                    $links[] = [$rank, $node->getAttribute('href')];
                }
            }
        }
        usort($links, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        foreach ($links as [, $link]) {
            $candidates[] = $link;
        }
        $result = [];
        if ($title !== null) {
            $result['title'] = $title;
        }
        if ($description !== null) {
            $result['description'] = $description;
        }
        foreach ($candidates as $value) {
            if (is_string($value) && ($image = $this->imageUrl($base, $value)) !== null) {
                $result['image'] = $image;
                break;
            }
        }

        return $result;
    }

    private function imageUrl(string $base, string $value): ?string
    {
        if (strlen($value) > 2048 || trim($value) === '') {
            return null;
        }
        try {
            $absolute = $this->resolver->resolve($base, trim($value));
        } catch (InvalidArgumentException) {
            return null;
        }

        return LinkPreviewUrl::isPublicSyntax($absolute) ? $absolute : null;
    }
}
