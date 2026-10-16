<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use InvalidArgumentException;

/** Recommendations are links, never executable installation instructions. */
/** @phpstan-import-type LocalizedText from CatalogValue */
final readonly class RecommendedPlugin
{
    /** @var list<ComponentKind> */
    public array $kinds;

    /**
     * @param  LocalizedText  $label
     * @param  LocalizedText  $description
     * @param  list<string>  $keywords
     * @param  list<ComponentKind>  $kinds
     */
    public function __construct(
        public string $id, public string|array $label, public string|array $description,
        public string $author, public string $repository, public CatalogCategory $category,
        public array $keywords = [], public ?string $icon = 'hugeicons-grid-view',
        public ?string $image = null, public ?string $heroImage = null,
        public ?string $composerPackage = null, public ?int $popularRank = null,
        public bool $newAndNoteworthy = false, public int $sort = 100,
        public ?string $version = null, public ?string $website = null,
        public ?string $privacyPolicy = null, public ?string $termsOfService = null,
        array $kinds = [],
    ) {
        if (! array_is_list($kinds) || count($kinds) > 5) {
            throw new InvalidArgumentException('Recommendation kinds require a bounded list of public component kinds.');
        }
        $seenKinds = [];
        foreach ($kinds as $kind) {
            if (! $kind instanceof ComponentKind || $kind === ComponentKind::Primitive || isset($seenKinds[$kind->value])) {
                throw new InvalidArgumentException('Recommendation kinds must be unique field, column, display, entry or action cases.');
            }
            $seenKinds[$kind->value] = true;
        }
        $this->kinds = ArraySnapshot::copy($kinds);
        CatalogValue::id($id);
        CatalogValue::text($label, CatalogValue::LABEL_LIMIT);
        CatalogValue::text($description, CatalogValue::DESCRIPTION_LIMIT);
        CatalogValue::text($author, CatalogValue::AUTHOR_LIMIT);
        CatalogValue::https($repository, true);
        // Reuse the discovery contract without granting an official verification badge.
        new ComponentPresentation($label, $description, $category, $keywords, $icon, $image, $heroImage, $author, popularRank: $popularRank);
        if ($composerPackage !== null && ! preg_match('~^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$~D', $composerPackage)) {
            throw new InvalidArgumentException('Invalid Composer package identity.');
        }
        if ($version !== null) {
            CatalogValue::text($version, 80);
        }
        foreach ([$website, $privacyPolicy, $termsOfService] as $link) {
            if ($link !== null) {
                CatalogValue::https($link);
            }
        }
    }
}
