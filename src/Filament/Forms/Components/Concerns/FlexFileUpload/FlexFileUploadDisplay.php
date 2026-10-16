<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarSize;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\FileIconCatalog;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadLayout;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadPresentation;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadRequirements;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use InvalidArgumentException;

/** Presentation options are independent of native transport and persistence. @mixin FileUpload */
trait FlexFileUploadDisplay
{
    protected string|Closure $flexFileUploadVariant = 'primary';

    protected bool|Closure $flexRemainingSlotsLabel = false;

    protected bool|Closure $flexUploadSummary = false;

    protected bool|Closure $flexInstantUpload = true;

    protected bool|Closure $flexPausableUploads = false;

    protected string|Closure|null $flexEmptyStateHint = null;

    protected string|Closure|null $flexDropzoneLabel = null;

    protected bool|Closure $flexRequireReplaceConfirmation = false;

    protected bool|Closure $flexCompactList = false;

    protected bool|Closure $flexCompactHeader = false;

    protected bool|Closure $flexShowFileIcon = true;

    protected UploadLayout|string|Closure|null $flexUploadLayout = null;

    protected string|Closure $flexGridActions = 'menu';

    protected int|Closure $flexGridColumns = 3;

    protected int|Closure $flexGridTileSize = 116;

    protected AvatarSize|int|string|Closure $flexAvatarSize = AvatarSize::Medium;

    protected int|Closure|null $flexPreviewThumbnailSize = null;

    /** Longest edge of the local display-only thumbnail; null retains the browser original. */
    public function previewThumbnailSize(int|Closure|null $pixels): static
    {
        $this->flexPreviewThumbnailSize = $pixels;

        return $this;
    }

    public function getPreviewThumbnailSize(): ?int
    {
        $pixels = $this->evaluate($this->flexPreviewThumbnailSize);
        if ($pixels !== null && (! is_int($pixels) || $pixels < 64 || $pixels > 2048)) {
            throw new InvalidArgumentException('previewThumbnailSize must be null or an integer between 64 and 2048.');
        }

        return $pixels;
    }

    public function remainingSlotsLabel(bool|Closure $condition = true): static
    {
        $this->flexRemainingSlotsLabel = $condition;

        return $this;
    }

    /** Start native processing when a file is selected; false waits for Upload or form submit. */
    public function instantUpload(bool|Closure $condition = true): static
    {
        $this->flexInstantUpload = $condition;

        return $this;
    }

    public function shouldUploadInstantly(): bool
    {
        return (bool) $this->evaluate($this->flexInstantUpload);
    }

    /** Resume restarts the native request; this does not enable byte-range/chunk continuation. */
    public function pausable(bool|Closure $condition = true): static
    {
        $this->flexPausableUploads = $condition;

        return $this;
    }

    public function isPausable(): bool
    {
        return ! $this->isAvatar() && (bool) $this->evaluate($this->flexPausableUploads);
    }

    /** Clear stale temporary-transfer errors, preserving ordinary field validation. */
    #[ExposedLivewireMethod]
    public function clearTemporaryUploadErrors(): void
    {
        if ($this->isDisabled()) {
            return;
        }

        $livewire = $this->getLivewire();
        $prefix = $this->getStatePath().'.';
        foreach (array_keys($livewire->getErrorBag()->getMessages()) as $key) {
            if (is_string($key) && str_starts_with($key, $prefix) && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', substr($key, strlen($prefix))) === 1) {
                $livewire->resetValidation($key);
            }
        }
    }

    public function uploadSummary(bool|Closure $condition = true): static
    {
        $this->flexUploadSummary = $condition;

        return $this;
    }

    public function emptyStateHint(string|Closure $hint): static
    {
        $this->flexEmptyStateHint = $hint;

        return $this;
    }

    public function dropzoneLabel(string|Closure $label): static
    {
        $this->flexDropzoneLabel = $label;

        return $this;
    }

    public function requireReplaceConfirmation(bool|Closure $condition = true): static
    {
        $this->flexRequireReplaceConfirmation = $condition;

        return $this;
    }

    public function showFileIcon(bool|Closure $condition = true): static
    {
        $this->flexShowFileIcon = $condition;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->flexFileUploadVariant = $variant;

        return $this;
    }

    public function uploadLayout(UploadLayout|string|Closure $layout): static
    {
        $this->flexUploadLayout = $layout;

        return $this;
    }

    public function gridColumns(int|Closure $columns): static
    {
        $this->flexGridColumns = $columns;

        return $this;
    }

    public function avatarSize(AvatarSize|int|string|Closure $pixels): static
    {
        $this->flexAvatarSize = $pixels;

        return $this;
    }

    public function gridTileSize(int|Closure $pixels): static
    {
        $this->flexGridTileSize = $pixels;

        return $this;
    }

    /** Named sizes share the same geometry; gridTileSize() remains the exact-pixel API. */
    public function gridSize(string|Closure $size): static
    {
        return $this->gridTileSize(function () use ($size): int {
            return match ($this->evaluate($size)) {
                'sm' => 96,
                'md' => 116,
                default => throw new InvalidArgumentException('Grid size must be sm or md.'),
            };
        });
    }

    public function gridActions(string|Closure $presentation): static
    {
        $this->flexGridActions = $presentation;

        return $this;
    }

    public function getGridActions(): string
    {
        $mode = $this->evaluate($this->flexGridActions);
        if (! in_array($mode, ['below', 'inside', 'menu'], true)) {
            throw new InvalidArgumentException('Grid actions must be below, inside or menu.');
        }

        return $mode === 'inside' ? 'menu' : $mode;
    }

    public function compactList(bool|Closure $condition = true): static
    {
        $this->flexCompactList = $condition;

        return $this->panelLayout(fn (): ?string => $this->shouldUseCompactList() ? 'compact' : null);
    }

    /** Condense the file dropzone independently of list/grid item presentation. */
    public function compactHeader(bool|Closure $condition = true): static
    {
        $this->flexCompactHeader = $condition;

        return $this;
    }

    public function shouldUseCompactHeader(): bool
    {
        return ! $this->isAvatar() && (bool) $this->evaluate($this->flexCompactHeader);
    }

    public function isUploadAtCapacity(): bool
    {
        $state = $this->getRawState();
        $max = $this->getMaxFiles();

        return $this->isMultiple() && $max !== null && is_array($state) && count($state) >= $max;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->flexFileUploadVariant);
        if (! in_array($variant, ['primary', 'secondary', 'flat'], true)) {
            throw new InvalidArgumentException('Upload variant must be primary, secondary or flat.');
        }

        return $variant;
    }

    public function shouldShowRemainingSlotsLabel(): bool
    {
        return (bool) $this->evaluate($this->flexRemainingSlotsLabel);
    }

    public function shouldShowUploadSummary(): bool
    {
        return (bool) $this->evaluate($this->flexUploadSummary);
    }

    public function shouldRequireReplaceConfirmation(): bool
    {
        return (bool) $this->evaluate($this->flexRequireReplaceConfirmation);
    }

    public function shouldShowFileIcon(): bool
    {
        return (bool) $this->evaluate($this->flexShowFileIcon);
    }

    public function shouldUseCompactList(): bool
    {
        return (bool) $this->evaluate($this->flexCompactList);
    }

    public function getEmptyStateHint(): ?string
    {
        return $this->evaluate($this->flexEmptyStateHint);
    }

    public function getDropzoneLabel(): ?string
    {
        return $this->evaluate($this->flexDropzoneLabel);
    }

    public function getUploadRequirements(): UploadRequirements
    {
        return new UploadRequirements(
            types: ($types = $this->getAcceptedFileTypes()) === null ? null : array_values($types), extensions: $this->getAllowedExtensions(), mimeTypeMap: $this->getMimeTypeMap(),
            minSize: $this->getMinSize(), maxSize: $this->getMaxSize(),
            minFiles: $this->getMinFiles(), maxFiles: $this->getMaxFiles(), maxTotalSize: $this->getMaxTotalSizeKb(),
            dimensions: $this->getUploadValidationDimensions(), required: $this->isRequired(),
        );
    }

    public function getEffectivePlaceholder(): ?string
    {
        return $this->getDropzoneLabel() ?? $this->getEmptyStateHint() ?? $this->getPlaceholder();
    }

    public function getRemainingSlotsLabel(): ?string
    {
        $max = $this->getMaxFiles();
        if (! $this->shouldShowRemainingSlotsLabel() || $max === null) {
            return null;
        }

        return Translations::get('filament-flex-fields::default.file_upload.remaining_slots', [
            'remaining' => max(0, $max - count($this->normalizeFilePaths($this->getRawState()))), 'max' => $max,
        ]);
    }

    public function getUploadPresentation(): UploadPresentation
    {
        $configured = $this->evaluate($this->flexUploadLayout);
        $layout = $configured instanceof UploadLayout ? $configured : ($configured === null ? null : UploadLayout::tryFrom($configured));
        if ($configured !== null && $layout === null) {
            throw new InvalidArgumentException('Invalid upload layout.');
        }
        $layout = $this->isAvatar() ? UploadLayout::Avatar : ($layout ?? ($this->shouldUseCompactList()
            ? UploadLayout::Compact : (str_contains($this->getPanelLayout() ?? '', 'grid') ? UploadLayout::Grid : UploadLayout::List)));

        $avatar = $this->evaluate($this->flexAvatarSize);
        if (is_string($avatar)) {
            $avatar = AvatarSize::tryFrom($avatar) ?? throw new InvalidArgumentException('Avatar size must be sm, md, lg or pixels.');
        }

        return new UploadPresentation($layout, $this->evaluate($this->flexGridColumns), $avatar instanceof AvatarSize ? $avatar->pixels() : $avatar, $this->evaluate($this->flexGridTileSize));
    }

    /** @return array<string, mixed> */
    public function getFlexFileUploadAlpineConfiguration(): array
    {
        return [...$this->getUploadSourceAlpineConfiguration(), ...$this->getUploadPresentation()->forField($this),
            'showUploadSummary' => $this->shouldShowUploadSummary(),
            'instantUpload' => $this->shouldUploadInstantly(),
            'isPausable' => $this->isPausable(),
            'requireReplaceConfirmation' => $this->shouldRequireReplaceConfirmation(),
            'replaceConfirmationMessage' => Translations::get('filament-flex-fields::default.file_upload.replace_confirmation'),
            'summaryTemplate' => Translations::get('filament-flex-fields::default.file_upload.summary'),
            'remainingSlotsTemplate' => $this->shouldShowRemainingSlotsLabel() && $this->getMaxFiles() !== null
                ? Translations::get('filament-flex-fields::default.file_upload.remaining_slots') : null,
            'maxFiles' => $this->getMaxFiles(), 'showFileIcon' => $this->shouldShowFileIcon(),
            'isAvatar' => $this->isAvatar(),
            'previewThumbnailSize' => $this->getPreviewThumbnailSize(),
            'previewThumbnailMaxPixels' => min($this->getImageLimits()->sourcePixels, intdiv($this->getImageLimits()->workingBytes, 16)),
            'transportEvents' => $this->getUploadTransportConfiguration(),
            'hasFileViewer' => $this->hasFileViewer(),
            'hasUploadedFileClick' => $this->hasUploadedFileClick(),
            'fileClickPreview' => $this->shouldPreviewFileOnClick(),
            'hasFileViewerMetadata' => $this->hasFileViewerMetadata(),
            'hasFileMetadataEditor' => $this->hasFileMetadataEditor(),
            'fileIcons' => FileIconCatalog::configuration(),
            'hasImageEditor' => $this->isImageEditorExplicitlyEnabled() && ! $this->isDisabled(),
            'canEditSvgs' => $this->canEditSvgs(), 'maxBytes' => ($this->getMaxSize() ?? 25600) * 1024,
        ];
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return array_values(array_filter([
            'fff-flex-file-upload', 'fff-flex-file-upload--grid-actions-'.$this->getGridActions(), 'fff-flex-file-upload--'.$this->getSize(), 'fff-flex-file-upload--'.$this->getVariant(),
            'fff-flex-file-upload--'.$this->getUploadPresentation()->layout->value,
            $this->shouldShowFileIcon() ? 'fff-flex-file-upload--show-file-icon' : null,
            $this->shouldUseCompactList() ? 'fff-flex-file-upload--compact-list' : null,
            $this->shouldShowFocusOutline() ? 'has-focus-outline' : null,
            $this->hasUploadSourceTabs() ? 'fff-flex-file-upload--source-tabs' : null,
            $this->isReorderable() ? 'fff-flex-file-upload--reorderable' : null,
        ]));
    }
}
