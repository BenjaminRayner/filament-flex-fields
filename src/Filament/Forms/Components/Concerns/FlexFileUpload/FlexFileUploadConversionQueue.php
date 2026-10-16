<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;

/** @mixin BaseFileUpload */
trait FlexFileUploadConversionQueue
{
    protected bool|Closure $flexQueueImageConversions = false;

    protected ConversionQueueOptions|Closure|null $flexImageConversionQueue = null;

    /** Background encoding is optional; original path remains the stable persisted value. */
    public function queueImageConversions(bool|Closure $condition = true): static
    {
        $this->flexQueueImageConversions = $condition;

        return $this;
    }

    public function imageConversionQueue(ConversionQueueOptions|Closure|null $options): static
    {
        $this->flexImageConversionQueue = $options;

        return $this;
    }

    public function shouldQueueImageConversions(): bool
    {
        $enabled = (bool) $this->evaluate($this->flexQueueImageConversions);
        if ($enabled && $this instanceof FlexSpatieMediaLibraryFileUpload) {
            throw new InvalidArgumentException('Spatie uses registerMediaConversions()->queued() on the model.');
        }

        return $enabled;
    }

    public function getImageConversionQueue(): ConversionQueueOptions
    {
        $options = $this->evaluate($this->flexImageConversionQueue) ?? new ConversionQueueOptions;
        if (! $options instanceof ConversionQueueOptions) {
            throw new InvalidArgumentException('imageConversionQueue must return ConversionQueueOptions.');
        }

        return $options;
    }

    /** Existing field authorization is applied independently to every returned attachment.
     * @return array<array-key, array<string, mixed>|null>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getMediaProcessingFiles(): array
    {
        return $this->getUploadedFiles() ?? [];
    }
}
