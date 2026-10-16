<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns;

use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadImageProcessor;
use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadMimePresets;
use Bjanczak\FilamentFlexFields\Support\FileUpload\ScopedDirectoryResolver;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaKind;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaStorageDriver;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\DiskMediaRef;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentIdResolver;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentPruner;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorAttachmentVariantGenerator;
use Bjanczak\FilamentFlexFields\Support\RichEditor\RichEditorImageVariant;
use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @mixin RichEditor
 */
trait InteractsWithFlexRichEditorFileAttachments
{
    protected bool|Closure $flexRichEditorOptimizeImages = false;

    protected bool|Closure $flexRichEditorOptimizeImagesToWebp = false;

    protected int|Closure|null $flexRichEditorMaxImageWidth = null;

    protected int|Closure|null $flexRichEditorMaxImageHeight = null;

    protected int|Closure|null $flexRichEditorMaxImageLongEdge = null;

    protected bool|Closure $flexRichEditorStripExif = true;

    /**
     * @var array<string, array<string, mixed>|RichEditorImageVariant>|Closure|null
     */
    protected array|Closure|null $flexRichEditorImageVariants = [
        'thumb' => ['max_long_edge' => 250, 'webp' => true],
        'large' => ['max_long_edge' => 1500, 'webp' => true, 'master' => true],
    ];

    protected bool|Closure $flexRichEditorPruneOrphanedAttachments = true;

    public function imagesOnly(): static
    {
        return $this->fileAttachmentsAcceptedFileTypes(FileUploadMimePresets::images());
    }

    public function maxAttachmentSizeKb(int|Closure $size): static
    {
        return $this->fileAttachmentsMaxSize($size);
    }

    public function optimizeImages(bool|Closure $condition = true): static
    {
        $this->flexRichEditorOptimizeImages = $condition;

        return $this;
    }

    public function optimizeImagesToWebp(bool|Closure $condition = false): static
    {
        $this->flexRichEditorOptimizeImagesToWebp = $condition;

        return $this;
    }

    public function maxImageWidth(int|Closure $width): static
    {
        $this->flexRichEditorMaxImageWidth = $width;

        return $this;
    }

    public function maxImageHeight(int|Closure $height): static
    {
        $this->flexRichEditorMaxImageHeight = $height;

        return $this;
    }

    public function maxImageLongEdge(int|Closure $pixels): static
    {
        $this->flexRichEditorMaxImageLongEdge = $pixels;

        return $this;
    }

    public function stripExif(bool|Closure $condition = true): static
    {
        $this->flexRichEditorStripExif = $condition;

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>|RichEditorImageVariant>|Closure  $variants
     */
    public function imageVariants(array|Closure $variants): static
    {
        $this->flexRichEditorImageVariants = $variants;

        return $this;
    }

    public function pruneOrphanedAttachmentsOnSave(bool|Closure $condition = true): static
    {
        $this->flexRichEditorPruneOrphanedAttachments = $condition;

        return $this;
    }

    public function scopedAttachmentDirectory(string|Closure $prefix = 'rich-editor'): static
    {
        return $this->fileAttachmentsDirectory(function () use ($prefix): string {
            $record = $this->getRecord();

            return ScopedDirectoryResolver::resolve(
                (string) $this->evaluate($prefix),
                $record instanceof Model ? $record : null,
                Auth::id(),
            );
        });
    }

    public function shouldOptimizeRichEditorImages(): bool
    {
        return (bool) $this->evaluate($this->flexRichEditorOptimizeImages);
    }

    public function shouldOptimizeRichEditorImagesToWebp(): bool
    {
        return (bool) $this->evaluate($this->flexRichEditorOptimizeImagesToWebp);
    }

    public function getFlexRichEditorMaxImageWidth(): ?int
    {
        $width = $this->evaluate($this->flexRichEditorMaxImageWidth);

        return is_int($width) ? $width : null;
    }

    public function getFlexRichEditorMaxImageHeight(): ?int
    {
        $height = $this->evaluate($this->flexRichEditorMaxImageHeight);

        return is_int($height) ? $height : null;
    }

    public function getFlexRichEditorMaxImageLongEdge(): ?int
    {
        $pixels = $this->evaluate($this->flexRichEditorMaxImageLongEdge);

        return is_int($pixels) ? $pixels : null;
    }

    public function shouldStripExifRichEditorAttachments(): bool
    {
        return (bool) $this->evaluate($this->flexRichEditorStripExif);
    }

    public function shouldPruneOrphanedRichEditorAttachments(): bool
    {
        return (bool) $this->evaluate($this->flexRichEditorPruneOrphanedAttachments);
    }

    /**
     * @return list<RichEditorImageVariant>
     */
    public function getFlexRichEditorImageVariants(): array
    {
        $variants = $this->evaluate($this->flexRichEditorImageVariants);

        if (! is_array($variants) || $variants === []) {
            return [];
        }

        return RichEditorImageVariant::normalizeCollection($variants);
    }

    public function shouldProcessRichEditorAttachmentImages(): bool
    {
        if ($this->getFlexRichEditorImageVariants() !== []) {
            return true;
        }

        return $this->shouldOptimizeRichEditorImages()
            || $this->shouldOptimizeRichEditorImagesToWebp()
            || $this->getFlexRichEditorMaxImageWidth()
            || $this->getFlexRichEditorMaxImageHeight()
            || $this->getFlexRichEditorMaxImageLongEdge();
    }

    public function usesRichEditorFileAttachmentProvider(): bool
    {
        return $this->getFileAttachmentProvider() !== null;
    }

    public function usesSpatieRichEditorFileAttachmentProvider(): bool
    {
        $provider = $this->getFileAttachmentProvider();

        return $provider !== null
            && str_contains($provider::class, 'SpatieMediaLibrary');
    }

    public function getFileAttachmentProvider(): ?FileAttachmentProvider
    {
        if (! isset($this->container)) {
            return null;
        }

        return parent::getFileAttachmentProvider();
    }

    public function registerFlexRichEditorFileAttachmentHooks(): void
    {
        $this->saveUploadedFileAttachmentUsing(function (TemporaryUploadedFile $file): mixed {
            return $this->persistFlexRichEditorFileAttachment($file);
        });

        $this->beforeStateDehydrated(function (): void {
            $this->pruneFlexRichEditorOrphanedAttachments();
        }, shouldUpdateValidatedStateAfter: true);
    }

    public function persistFlexRichEditorFileAttachment(TemporaryUploadedFile $file): mixed
    {
        $payload = MediaPayload::fromTemporaryUploadedFile($file);
        $context = (new MediaContext(
            kind: MediaKind::RichAttachment,
            driver: $this->usesSpatieRichEditorFileAttachmentProvider()
                ? MediaStorageDriver::Spatie
                : MediaStorageDriver::Disk,
            field: $this->getName(),
            record: ($record = $this->getRecord()) instanceof Model ? $record : null,
            disk: $this->getFileAttachmentsDiskName(),
            directory: $this->getFileAttachmentsDirectory(),
        ))->withCorrelationId((string) Str::uuid());

        if (! FlexMedia::ingress()->passesPrePersistScan($payload, $context)) {
            return null;
        }

        $file = $this->prepareRichEditorAttachmentForProviderSave($file);

        if (filled($savedFile = $this->defaultSaveUploadedFileAttachment($file))) {
            if (is_string($savedFile) && ! $this->usesSpatieRichEditorFileAttachmentProvider()) {
                $ref = new DiskMediaRef($this->getFileAttachmentsDiskName(), $savedFile);

                if (! FlexMedia::ingress()->assertCleanStored($ref, $context)) {
                    return null;
                }
            }

            return $savedFile;
        }

        $path = $file->store(
            $this->getFileAttachmentsDirectory() ?? '',
            $this->getFileAttachmentsDiskName(),
        );

        if (! is_string($path) || $path === '') {
            return null;
        }

        if ($this->getFileAttachmentsVisibility() === 'public') {
            rescue(fn () => $this->getFileAttachmentsDisk()->setVisibility($path, 'public'), report: false);
        }

        try {
            $path = $this->postProcessStoredRichEditorAttachment($path);
        } catch (\Throwable $error) {
            // This attachment was created by this operation; a failed conversion
            // must not leave it behind as an unreferenced permanent upload.
            (new RichEditorAttachmentPruner)->deleteMastersWithVariants($this->getFileAttachmentsDisk(), [$path]);
            throw $error;
        }
        $ref = new DiskMediaRef($this->getFileAttachmentsDiskName(), $path);

        if (! FlexMedia::ingress()->assertCleanStored($ref, $context)) {
            (new RichEditorAttachmentPruner)->deleteMastersWithVariants($this->getFileAttachmentsDisk(), [$path]);

            return null;
        }

        return $path;
    }

    /**
     * Display URL via Media Ingress signed-URL capsule when available.
     */
    public function resolveFlexRichEditorAttachmentSignedUrl(string $path): ?string
    {
        return FlexMedia::signedUrl($this->getFileAttachmentsDiskName(), $path, [
            'field' => $this->getName(),
            'visibility' => $this->getFileAttachmentsVisibility(),
            'kind' => MediaKind::RichAttachment->value,
        ]);
    }

    public function postProcessStoredRichEditorAttachment(string $path): string
    {
        $variants = $this->getFlexRichEditorImageVariants();

        if ($variants !== []) {
            $manifest = (new RichEditorAttachmentVariantGenerator)->generate(
                $this->getFileAttachmentsDisk(),
                $path,
                $variants,
            );

            return $manifest->master;
        }

        if ($this->shouldPostProcessRichEditorAttachmentOnDisk()) {
            return $this->makeRichEditorImageProcessor(allowWebp: true)->process(
                $this->getFileAttachmentsDisk(),
                $path,
            );
        }

        return $path;
    }

    public function pruneFlexRichEditorOrphanedAttachments(): void
    {
        if (! isset($this->container)) {
            return;
        }

        if (! $this->shouldPruneOrphanedRichEditorAttachments()) {
            return;
        }

        if ($this->usesRichEditorFileAttachmentProvider()) {
            return;
        }

        $keptIds = RichEditorAttachmentIdResolver::fromContent($this->getState());
        $originalIds = $this->getOriginalFileAttachmentPaths();
        $removedIds = array_values(array_diff($originalIds, $keptIds));

        if ($removedIds === []) {
            return;
        }

        (new RichEditorAttachmentPruner)->deleteMastersWithVariants(
            $this->getFileAttachmentsDisk(),
            $removedIds,
        );
    }

    protected function prepareRichEditorAttachmentForProviderSave(TemporaryUploadedFile $file): TemporaryUploadedFile
    {
        if (! $this->usesRichEditorFileAttachmentProvider() || $this->usesSpatieRichEditorFileAttachmentProvider() || ! $this->shouldPreProcessRichEditorAttachmentTemp()) {
            return $file;
        }

        $absolutePath = $file->getRealPath();

        if (! is_file($absolutePath)) {
            return $file;
        }

        $processor = $this->resolvePreProcessRichEditorImageProcessor();
        $processor->processLocalPath($absolutePath);

        return $file;
    }

    protected function shouldPreProcessRichEditorAttachmentTemp(): bool
    {
        if ($this->getFlexRichEditorImageVariants() !== []) {
            return true;
        }

        return $this->shouldOptimizeRichEditorImages()
            || $this->getFlexRichEditorMaxImageWidth()
            || $this->getFlexRichEditorMaxImageHeight()
            || $this->getFlexRichEditorMaxImageLongEdge();
    }

    protected function shouldPostProcessRichEditorAttachmentOnDisk(): bool
    {
        if ($this->usesRichEditorFileAttachmentProvider()) {
            return false;
        }

        return $this->shouldProcessRichEditorAttachmentImages();
    }

    protected function resolvePreProcessRichEditorImageProcessor(): FileUploadImageProcessor
    {
        $masterVariant = collect($this->getFlexRichEditorImageVariants())
            ->first(fn (RichEditorImageVariant $variant): bool => $variant->master);

        if ($masterVariant instanceof RichEditorImageVariant) {
            return $masterVariant->toImageProcessor();
        }

        return $this->makeRichEditorImageProcessor(allowWebp: false);
    }

    protected function makeRichEditorImageProcessor(bool $allowWebp): FileUploadImageProcessor
    {
        return new FileUploadImageProcessor(
            optimizeImages: $this->shouldOptimizeRichEditorImages(),
            optimizeImagesToWebp: $allowWebp && $this->shouldOptimizeRichEditorImagesToWebp(),
            maxImageWidth: $this->getFlexRichEditorMaxImageWidth(),
            maxImageHeight: $this->getFlexRichEditorMaxImageHeight(),
            maxImageLongEdge: $this->getFlexRichEditorMaxImageLongEdge(),
            stripExif: $this->shouldStripExifRichEditorAttachments(),
        );
    }
}
