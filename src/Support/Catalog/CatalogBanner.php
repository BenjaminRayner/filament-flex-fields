<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog;

/** @phpstan-import-type LocalizedText from CatalogValue */
final readonly class CatalogBanner
{
    /**
     * @param  LocalizedText  $title
     * @param  LocalizedText  $description
     * @param  LocalizedText  $action
     * @param  LocalizedText|null  $eyebrow
     */
    public function __construct(
        public string $id, public string|array $title, public string|array $description,
        public string $url, public string|array $action = 'Explore', public ?string $image = null,
        public ?string $backgroundImage = null, public string $theme = 'light',
        public string $backgroundPosition = 'center', public float $overlayOpacity = .35,
        public string|array|null $eyebrow = null, public string $alignment = 'start',
    ) {
        CatalogValue::id($id);
        CatalogValue::text($title, CatalogValue::LABEL_LIMIT);
        CatalogValue::text($description, CatalogValue::DESCRIPTION_LIMIT);
        CatalogValue::text($action, CatalogValue::ACTION_LIMIT);
        CatalogValue::https($url);
        CatalogValue::image($image);
        CatalogValue::image($backgroundImage);
        if ($eyebrow !== null) {
            CatalogValue::text($eyebrow, CatalogValue::ACTION_LIMIT);
        }
        if (! in_array($theme, ['light', 'dark'], true) || ! in_array($backgroundPosition, ['center', 'top', 'bottom', 'left', 'right'], true)
            || ! in_array($alignment, ['start', 'center'], true) || ! is_finite($overlayOpacity) || $overlayOpacity < 0 || $overlayOpacity > 1) {
            throw new \InvalidArgumentException('Invalid banner presentation options.');
        }
    }

    /** @return array<string, mixed> */
    public function presentation(): array
    {
        return [...get_object_vars($this), 'title' => CatalogValue::localized($this->title), 'description' => CatalogValue::localized($this->description),
            'action' => CatalogValue::localized($this->action), 'eyebrow' => $this->eyebrow === null ? null : CatalogValue::localized($this->eyebrow)];
    }
}
