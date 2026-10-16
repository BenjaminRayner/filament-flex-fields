<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Bjanczak\FilamentFlexFields\Support\Segments\SegmentItem;

/** One SSR snapshot: no alternate hidden inputs or duplicate badge markup. */
final readonly class SlugRenderState
{
    /** @param array<string, mixed> $options
     * @param  list<SegmentItem>  $primary
     * @param  list<SegmentItem>  $secondary
     */
    private function __construct(
        public array $options,
        public string $editable,
        public ?string $host,
        public ?string $path,
        public ?string $postfix,
        public bool $locked,
        public bool $readOnly,
        public bool $showSeparator,
        public array $primary,
        public array $secondary,
    ) {}

    public static function from(SlugField $field): self
    {
        $options = $field->getAlpineConfiguration();
        $labels = $field->getUiLabels();
        $editable = ! $field->isDisabled() && ! $field->isSlugReadOnly();
        $inline = $field->shouldUseInlineEditing();
        $chrome = (bool) $options['permalinkPreview'];
        $host = $chrome && $field->isUrlHostVisible() ? $field->getDisplayUrlHost() : null;
        $path = $chrome && $field->isUrlPathVisible() ? $field->getUrlPath() : null;
        $primary = $editable && $inline ? [
            new SegmentItem('edit', $labels['edit'], 'hugeicons-edit-02', visibilityJs: "mode === 'view'", initiallyVisible: true),
            new SegmentItem('confirm', $labels['confirm'], 'hugeicons-tick-02', visibilityJs: "mode === 'edit'", initiallyVisible: false),
            new SegmentItem('cancel', $labels['cancel'], 'hugeicons-multiplication-sign', visibilityJs: "mode === 'edit'", initiallyVisible: false),
            new SegmentItem('reset', $labels['reset'], 'hugeicons-refresh', visibilityJs: "mode === 'edit' && sourcePath", initiallyVisible: false),
        ] : [];
        $secondary = [];
        if ($editable && $field->shouldShowRegenerateButton() && $options['sourcePath'] !== null) {
            $secondary[] = new SegmentItem('regenerate', $labels['regenerate'], 'hugeicons-refresh', visibilityJs: 'canRegenerate()', initiallyVisible: (bool) $options['initialAutoSyncDisabled']);
        }
        $hasUrl = $field->getFullPermalinkUrl((string) $options['initialSlug']) !== null;
        if (! $field->isDisabled() && $field->shouldShowCopyButton() && ($field->hasUrlContext() || $field->hasCustomVisitUrl())) {
            $secondary[] = new SegmentItem('copy', $labels['copy'], 'hugeicons-copy-01', visibilityJs: "mode === 'view' && hasPermalink()", initiallyVisible: $hasUrl);
        }
        if (! $field->isDisabled() && $field->canVisitLink() && ($field->hasUrlContext() || $field->hasCustomVisitUrl())) {
            $secondary[] = new SegmentItem('visit', $labels['visit'], 'hugeicons-link-square-02', visibilityJs: "mode === 'view' && hasPermalink()", initiallyVisible: $hasUrl);
        }

        return new self(
            $options, SlugGenerator::toEditableSlug((string) $options['initialSlug'], $field->allowsHomepageSlug()),
            $host, $path, $chrome ? $field->getSlugLabelPostfix() : null,
            $host !== null && str_starts_with(strtolower($field->getUrlHost() ?? ''), 'https://'),
            ! $editable || $inline, SlugGenerator::shouldShowPermalinkSlugSeparator($path, $host), $primary, $secondary,
        );
    }
}
