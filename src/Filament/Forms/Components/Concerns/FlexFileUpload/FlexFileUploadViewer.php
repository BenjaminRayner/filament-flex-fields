<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin BaseFileUpload */
trait FlexFileUploadViewer
{
    protected bool|Closure $flexFileViewer = false;

    protected bool|Closure $flexViewerMetadata = true;

    protected bool|Closure $flexViewerMetadataEditable = false;

    protected ?Closure $flexViewerAction = null;

    protected string|Closure|null $flexViewerConversion = null;

    protected ?Closure $flexViewerUrl = null;

    public function fileViewer(bool|Closure $condition = true): static
    {
        $this->flexFileViewer = $condition;

        return $this;
    }

    /** Viewer source only; preview, image editor and download keep their own sources. */
    public function fileViewerConversion(string|Closure|null $conversion): static
    {
        $this->flexViewerConversion = $conversion;

        return $this;
    }

    public function getFileViewerConversion(): ?string
    {
        $name = $this->evaluate($this->flexViewerConversion);
        if ($name !== null && (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name) || $name === 'original')) {
            throw new InvalidArgumentException('Viewer conversion must be a named conversion or null for the original.');
        }

        return $name;
    }

    /** Optional existing large-preview URL resolver, after original ownership is proven. */
    public function fileViewerUrlUsing(?Closure $callback): static
    {
        $this->flexViewerUrl = $callback;

        return $this;
    }

    public function fileViewerMetadata(bool|Closure $condition = true): static
    {
        $this->flexViewerMetadata = $condition;

        return $this;
    }

    public function fileViewerEditableMetadata(bool|Closure $condition = true): static
    {
        $this->flexViewerMetadataEditable = $condition;

        return $this;
    }

    /** Extend the action with application FLEX fields or trusted schema views. */
    public function configureFileViewerActionUsing(?Closure $callback): static
    {
        $this->flexViewerAction = $callback;

        return $this;
    }

    public function hasFileViewer(): bool
    {
        return (bool) $this->evaluate($this->flexFileViewer);
    }

    public function hasFileViewerMetadata(): bool
    {
        return $this->hasFileMetadataEditor() && (bool) $this->evaluate($this->flexViewerMetadata);
    }

    public function canEditFileViewerMetadata(): bool
    {
        return $this->hasFileViewerMetadata() && ! $this->isDisabled() && (bool) $this->evaluate($this->flexViewerMetadataEditable);
    }

    public function registerFileViewerAction(): void
    {
        $this->registerActions([fn (): Action => $this->buildFileViewerAction()]);
    }

    private function buildFileViewerAction(): Action
    {
        $action = Action::make('viewFile')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.viewer.open'))
            ->icon('hugeicons-view')->visible(fn (): bool => $this->hasFileViewer())->modalWidth(Width::Screen)
            ->closeModalByClickingAway(false)->closeModalByEscaping(false)
            ->modalHeading('')->modalCloseButton(false)->modalSubmitAction(false)->modalCancelAction(false)->modalFooterActions([])
            ->extraModalOverlayAttributes(['class' => 'fff-upload-lightbox-overlay'])
            ->extraModalWindowAttributes(fn () => ['class' => 'fff-upload-lightbox-window', 'data-fff-loading-presentation' => 'lightbox', 'aria-label' => Translations::get('filament-flex-fields::default.file_upload.viewer.open')])
            ->schema(fn (array $arguments, \Filament\Actions\Action $action): array => [Grid::make(1)->extraAttributes([
                'class' => 'fff-upload-lightbox-layout'.($this->hasFileViewerMetadata() ? ' has-information' : ''),
            ])->schema([
                View::make('filament-flex-fields::forms.components.file-upload.viewer')->viewData([
                    'viewerField' => $this, 'viewerItems' => $this->getFileViewerItems(),
                    'viewerKey' => (string) ($arguments['fileKey'] ?? ''),
                    'viewerCancelParentActions' => FilamentApi::hasMethod(\Filament\Actions\Action::class, 'getParentActionsToCancelOnClose')
                        ? $action->getParentActionsToCancelOnClose() : false,
                ]),
                ...($this->hasFileViewerMetadata() ? [Group::make([
                    ...$this->getFileMetadataEditorSchema(),
                    ...($this->canEditFileViewerMetadata() ? [View::make('filament-flex-fields::forms.components.file-upload.viewer-save')] : []),
                ])->disabled(! $this->canEditFileViewerMetadata())->extraAttributes([
                    'class' => 'fff-upload-lightbox-information',
                    'wire:loading.attr' => 'inert',
                    'wire:target' => 'callMountedAction',
                ])] : []),
            ])])
            ->fillForm(function (array $arguments): array {
                $key = (string) ($arguments['fileKey'] ?? '');
                $this->fileViewerItem($key);

                return $this->hasFileViewerMetadata() ? $this->readFileEditorialMetadata($key) : [];
            })
            ->action(function (array $arguments, array $data): void {
                if ($this->canEditFileViewerMetadata()) {
                    $this->writeFileEditorialMetadata((string) ($arguments['fileKey'] ?? ''), $data);
                }
            });
        if ($this->flexViewerAction !== null) {
            $this->evaluate($this->flexViewerAction, ['action' => $action]);
        }

        return $action;
    }

    /** @return list<array{key: string, name: string, type: string, url: string}> */
    public function getFileViewerItems(): array
    {
        if (! $this->hasFileViewer()) {
            throw new MediaFailure('denied', 'The attachment viewer is disabled.');
        }
        $items = [];
        foreach (array_keys($this->getRawState() ?? []) as $key) {
            $item = $this->resolveFileViewerItem((string) $key);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array{key: string, name: string, type: string, url: string} */
    private function fileViewerItem(string $key): array
    {
        if (! $this->hasFileViewer()) {
            throw new MediaFailure('denied', 'The attachment viewer is disabled.');
        }

        return $this->resolveFileViewerItem($key) ?? throw new MediaFailure('file_missing', 'The image no longer belongs to this field.');
    }

    /** @return array{key: string, name: string, type: string, url: string}|null */
    private function resolveFileViewerItem(string $key): ?array
    {
        $file = ($this->getRawState() ?? [])[$key] ?? null;
        if ($file instanceof TemporaryUploadedFile) {
            $type = $file->getMimeType();
            if (! $file->isPreviewable() || ! in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'], true)) {
                return null;
            }

            return ['key' => $key, 'name' => $file->getClientOriginalName(), 'type' => $type, 'url' => $file->temporaryUrl()];
        }
        if (! is_string($file)) {
            return null;
        }
        // The same bridge performs owner/path authorization and private URL signing.
        $snapshot = $this->getClone()->openable()->previewUrlUsing(null);
        $payload = match (true) {
            $snapshot instanceof FlexSpatieMediaLibraryFileUpload => app(SpatieUploadBridge::class)->payload($snapshot, $file),
            $snapshot instanceof FlexFileUpload => app(DiskUploadBridge::class)->payload($snapshot, $file, $this->getStoredFileNames()),
            default => null,
        };
        if (! is_array($payload) || ! in_array($payload['type'], ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'], true) || ! is_string($payload['openableUrl'])) {
            return null;
        }

        $url = $payload['openableUrl'];
        if (($conversion = $this->getFileViewerConversion()) !== null) {
            $url = match (true) {
                $this instanceof FlexSpatieMediaLibraryFileUpload => app(SpatieUploadBridge::class)->conversionUrl($this, $file, $conversion),
                $this instanceof FlexFileUpload => app(DiskUploadBridge::class)->conversionUrl($this, $file, $conversion),
                default => null,
            } ?? $url;
        }
        if ($this->flexViewerUrl !== null) {
            $custom = $this->evaluate($this->flexViewerUrl, ['file' => $file, 'defaultUrl' => $url,
                'component' => $this, 'record' => $this->getRecord()]);
            if ($custom !== null) {
                $url = $this->validateFileClickUrl($custom);
            }
        }

        return ['key' => $key, 'name' => $payload['name'], 'type' => $payload['type'], 'url' => $url];
    }

    /** Save the current configured form before switching, without resetting the parent modal stack. */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function navigateFileViewer(string $key): bool
    {
        $target = $this->fileViewerItem($key);
        $livewire = $this->getLivewire();
        if (! $livewire instanceof HasActions || ! method_exists($livewire, 'getMountedAction')) {
            throw new MediaFailure('denied', 'This form does not support viewer actions.');
        }
        $action = $livewire->getMountedAction();
        if ($action?->getName() !== 'viewFile' || $action->getSchemaComponent() !== $this) {
            throw new MediaFailure('denied', 'The viewer does not belong to this field.');
        }
        $schemaName = $livewire->getMountedActionSchemaName();
        if ($schemaName === null) {
            throw new MediaFailure('denied', 'The viewer form is unavailable.');
        }
        $schema = $livewire->getSchema($schemaName);
        if (! $schema instanceof Schema) {
            throw new MediaFailure('denied', 'The viewer form is unavailable.');
        }
        if ($this->canEditFileViewerMetadata()) {
            $this->writeFileEditorialMetadata((string) ($action->getArguments()['fileKey'] ?? ''), $schema->getState());
        }
        $livewire->mergeMountedActionArguments(['fileKey' => $target['key']]);
        $schema->fill($this->hasFileViewerMetadata() ? $this->readFileEditorialMetadata($target['key']) : []);

        return true;
    }
}
