<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExample;

/** The same component schema drives composition, discovery and its live demo. */
/** @phpstan-import-type LocalizedText from CatalogValue */
final readonly class ComponentPresentation
{
    /**
     * @param  LocalizedText  $label
     * @param  LocalizedText  $description
     * @param  list<string>  $keywords
     * @param  list<CodeExample>  $examples
     */
    public function __construct(
        public string|array $label,
        public string|array $description,
        public CatalogCategory $category,
        public array $keywords = [],
        public ?string $icon = 'hugeicons-grid-view',
        public ?string $image = null,
        public ?string $heroImage = null,
        public string $author = 'Community',
        public bool $official = false,
        public ?string $demoClass = null,
        public ?string $demoSlug = null,
        public ?string $documentation = null,
        public int $sort = 100,
        public ?int $popularRank = null,
        public bool $newAndNoteworthy = false,
        public ?string $website = null,
        public ?string $privacyPolicy = null,
        public ?string $termsOfService = null,
        public array $examples = [],
        public bool $listed = true,
        public ?string $groupedWith = null,
    ) {
        if ($groupedWith !== null) {
            CatalogValue::id($groupedWith);
            if ($demoClass === null) {
                throw new \InvalidArgumentException('Grouped catalog surfaces require a demo.');
            }
        }
        if (! array_is_list($examples) || count($examples) > 12) {
            throw new \InvalidArgumentException('Examples must be a list of at most 12 file references.');
        }
        $paths = [];
        foreach ($examples as $example) {
            if (! $example instanceof CodeExample || isset($paths[$example->path])) {
                throw new \InvalidArgumentException('Examples require unique typed file references.');
            }
            $paths[$example->path] = true;
        }
        CatalogValue::text($label, CatalogValue::LABEL_LIMIT);
        CatalogValue::text($description, CatalogValue::DESCRIPTION_LIMIT);
        CatalogValue::text($author, CatalogValue::AUTHOR_LIMIT);
        if (! array_is_list($keywords) || count($keywords) > CatalogValue::KEYWORDS_LIMIT) {
            throw new \InvalidArgumentException('Invalid component keywords.');
        }
        foreach ($keywords as $keyword) {
            CatalogValue::text($keyword, CatalogValue::KEYWORD_LIMIT);
        }
        if ($icon !== null && ! preg_match('/^hugeicons-[a-z0-9-]+$/D', $icon)) {
            throw new \InvalidArgumentException('Component catalog icons use Hugeicons.');
        }
        if ($icon === null && $image === null) {
            throw new \InvalidArgumentException('A catalog entry needs an icon or image.');
        }
        CatalogValue::image($image);
        CatalogValue::image($heroImage);
        if ($demoSlug !== null) {
            CatalogValue::id($demoSlug);
        }
        if (($demoClass === null) !== ($demoSlug === null)) {
            throw new \InvalidArgumentException('Demo class and slug must be declared together.');
        }
        if ($demoClass !== null) {
            foreach (explode('\\', ltrim($demoClass, '\\')) as $segment) {
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $segment)) {
                    throw new \InvalidArgumentException('Invalid component demo class.');
                }
            }
        }
        if ($documentation !== null && ! preg_match('/^[a-z0-9-]+\.md$/D', $documentation)) {
            throw new \InvalidArgumentException('Invalid documentation path.');
        }
        if ($popularRank !== null && $popularRank < 0) {
            throw new \InvalidArgumentException('Invalid popularity rank.');
        }
        foreach ([$website, $privacyPolicy, $termsOfService] as $link) {
            if ($link !== null) {
                CatalogValue::https($link);
            }
        }
    }
}
