<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin BaseFileUpload */
trait FlexFileUploadMedia
{
    use FlexFileUploadConversionQueue;
    use FlexFileUploadMediaEvents;
    use FlexFileUploadMediaLifecycle;
    use FlexFileUploadMediaMetadata;
    use FlexFileUploadStaging;

    protected MediaPolicy|Closure|null $flexMediaPolicy = null;

    protected string|Closure $flexMediaAdapter = 'disk';

    /** @var list<string>|Closure */
    protected array|Closure $flexMediaProcessors = [];

    /** @var array<string, mixed>|Closure */
    protected array|Closure $flexMediaMetadata = [];

    /** @var list<ImageConversion>|Closure */
    protected array|Closure $flexImageConversions = [];

    protected ImageLimits|Closure|null $flexImageLimits = null;

    protected string|Closure|null $flexPreviewConversion = null;

    protected ?Closure $flexPreviewUrl = null;

    protected ?Closure $flexMediaFinalize = null;

    protected ?Closure $flexMediaWrite = null;

    public function mediaPolicy(MediaPolicy|Closure|null $policy): static
    {
        $this->flexMediaPolicy = $policy;

        return $this;
    }

    public function mediaAdapter(string|Closure $adapter): static
    {
        $this->flexMediaAdapter = $adapter;

        return $this;
    }

    /** @param list<string>|Closure $processors */
    public function mediaProcessors(array|Closure $processors): static
    {
        $this->flexMediaProcessors = $processors;

        return $this;
    }

    /** @param array<string, mixed>|Closure $metadata */
    public function mediaMetadata(array|Closure $metadata): static
    {
        $this->flexMediaMetadata = $metadata;

        return $this;
    }

    /** @param list<ImageConversion>|Closure $conversions */
    public function imageConversions(array|Closure $conversions): static
    {
        $this->flexImageConversions = $conversions;

        return $this;
    }

    public function imageLimits(ImageLimits|Closure|null $limits): static
    {
        $this->flexImageLimits = $limits;

        return $this;
    }

    public function previewConversion(string|Closure|null $conversion): static
    {
        $this->flexPreviewConversion = $conversion;

        return $this;
    }

    /** Override only the saved-file thumbnail, after the storage bridge has authorized its original. */
    public function previewUrlUsing(?Closure $callback): static
    {
        $this->flexPreviewUrl = $callback;

        return $this;
    }

    public function resolveUploadPreviewUrl(string $file, ?string $defaultUrl): ?string
    {
        if ($this->flexPreviewUrl === null) {
            return $defaultUrl;
        }
        $url = $this->evaluate($this->flexPreviewUrl, ['file' => $file, 'defaultUrl' => $defaultUrl,
            'component' => $this, 'record' => $this->getRecord()]);
        if ($url === null) {
            return $defaultUrl;
        }

        return $this->validateFileClickUrl($url);
    }

    /** Pre-publication veto. After-commit side effects belong to onUploadEvent. */
    public function mediaFinalizeUsing(?Closure $callback): static
    {
        $this->flexMediaFinalize = $callback;

        return $this;
    }

    /** Configure the complete typed write without replacing the field/record ownership boundary. */
    public function mediaWriteUsing(?Closure $callback): static
    {
        $this->flexMediaWrite = $callback;

        return $this;
    }

    public function finalizeUploadMedia(MediaReceipt $receipt, MediaWrite $intent): void
    {
        if ($this->flexMediaFinalize !== null) {
            $this->evaluate($this->flexMediaFinalize, ['receipt' => $receipt, 'intent' => $intent]);
        }
    }

    public function getMediaPolicy(): MediaPolicy
    {
        $policy = $this->evaluate($this->flexMediaPolicy) ?? app(MediaDefaults::class)->policy();
        if (! $policy instanceof MediaPolicy) {
            throw new InvalidArgumentException('mediaPolicy must return MediaPolicy.');
        }
        $max = $this->getMaxSize();
        $min = $this->getMinSize();
        $types = $this->getAcceptedFileTypes() ?? [];

        // Keep the explicit engine policy and native field restrictions independently enforced.
        return new MediaPolicy(
            maxBytes: $max === null ? $policy->maxBytes : min($policy->maxBytes, $max * 1024),
            minBytes: max($policy->minBytes, ($min ?? 0) * 1024),
            mimeTypes: $policy->mimeTypes === [] ? array_values($types) : $policy->mimeTypes,
            requireScanner: $policy->requireScanner, scanBeforeWrite: $policy->scanBeforeWrite,
            scanAfterWrite: $policy->scanAfterWrite, verifyIntegrity: $policy->verifyIntegrity,
            visibility: $this->flexMediaPolicy === null ? $this->getVisibility() : $policy->visibility, quarantineDisk: $policy->quarantineDisk,
        );
    }

    public function makeUploadMediaWrite(?TemporaryUploadedFile $file = null, ?string $adapter = null): MediaWrite
    {
        $record = $this->getRecord();

        $intent = new MediaWrite(
            adapter: $adapter ?? $this->evaluate($this->flexMediaAdapter), kind: 'upload',
            disk: $this->getDiskName(), directory: (string) $this->getDirectory(),
            filename: $file === null ? null : $this->getUploadedFileNameForStorage($file),
            field: $this->getName(), record: $record instanceof Model ? $record : null,
            policy: $this->getMediaPolicy(), metadata: $file === null ? [] : $this->evaluate($this->flexMediaMetadata, ['file' => $file]),
            processors: $file === null ? [] : $this->evaluate($this->flexMediaProcessors, ['file' => $file]),
        );
        if ($file !== null && $this->flexMediaWrite !== null) {
            $configured = $this->evaluate($this->flexMediaWrite, ['intent' => $intent, 'file' => $file]);
            if (! $configured instanceof MediaWrite || $configured->field !== $intent->field || $configured->record !== $intent->record
                || ($adapter !== null && $configured->adapter !== $adapter)) {
                throw new InvalidArgumentException('mediaWriteUsing must preserve the field, record and fixed adapter.');
            }

            return $configured;
        }

        return $intent;
    }

    /** @return list<ImageConversion> */
    public function getImageConversions(): array
    {
        $conversions = $this->evaluate($this->flexImageConversions);
        $names = [];
        foreach ($conversions as $conversion) {
            if (! $conversion instanceof ImageConversion || isset($names[$conversion->name])) {
                throw new InvalidArgumentException('imageConversions needs unique typed ImageConversion values.');
            }
            $names[$conversion->name] = true;
        }

        return array_values($conversions);
    }

    public function getImageLimits(): ImageLimits
    {
        $limits = $this->evaluate($this->flexImageLimits) ?? ImageLimits::fromConfig();
        if (! $limits instanceof ImageLimits) {
            throw new InvalidArgumentException('imageLimits must return ImageLimits.');
        }

        return $limits;
    }

    public function getPreviewConversion(): ?string
    {
        return $this->evaluate($this->flexPreviewConversion);
    }
}
