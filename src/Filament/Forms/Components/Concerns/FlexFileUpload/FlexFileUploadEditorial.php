<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableTabs;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle\UploadRecordLifecycle;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Metadata\EditorialMetadata;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** @mixin BaseFileUpload */
trait FlexFileUploadEditorial
{
    use FlexFileUploadEditorialTranslations;

    protected bool|Closure $flexMetadataEditor = false;

    protected string|Closure|null $flexEditorialStatePath = null;

    /** @var list<Field>|Closure|null */
    protected array|Closure|null $flexEditorialSchema = null;

    /** @var array<string, mixed> */
    protected array $flexEditorialRules = [];

    protected ?Closure $flexEditorialAction = null;

    public function fileMetadataEditor(bool|Closure $condition = true): static
    {
        $this->flexMetadataEditor = $condition;

        return $this;
    }

    /** Disk fields require a declared application JSON attribute; Spatie can use native properties only. */
    public function fileMetadataStatePath(string|Closure $path): static
    {
        $this->flexEditorialStatePath = $path;

        return $this;
    }

    /** @param list<Field>|Closure $schema */
    public function fileMetadataSchema(array|Closure $schema): static
    {
        $this->flexEditorialSchema = $schema;

        return $this;
    }

    /** @param array<string, mixed> $rules */
    public function fileMetadataValidationRules(array $rules): static
    {
        $this->flexEditorialRules = $rules;

        return $this;
    }

    /** Customize the FLEX action (modal width, labels, slide-over), preserving its validation and save handler. */
    public function configureFileMetadataActionUsing(?Closure $callback): static
    {
        $this->flexEditorialAction = $callback;

        return $this;
    }

    public function hasFileMetadataEditor(): bool
    {
        return (bool) $this->evaluate($this->flexMetadataEditor);
    }

    public function getFileMetadataStatePath(bool $absolute = false): string
    {
        $path = $this->evaluate($this->flexEditorialStatePath);
        if ($path === null && ! $this instanceof FlexSpatieMediaLibraryFileUpload) {
            throw new InvalidArgumentException('Configure fileMetadataStatePath() with a JSON model attribute for disk uploads.');
        }
        $path ??= $this->getName().'_file_metadata';
        if (! is_string($path) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/D', $path) !== 1 || str_contains($path, '..')) {
            throw new InvalidArgumentException('File metadata requires a valid sibling state path.');
        }
        if ($path === $this->getName() || $path === $this->getStoreMetadataInPath()) {
            throw new InvalidArgumentException('Editorial metadata must have a separate state path from upload and technical metadata.');
        }

        return $absolute ? implode('.', array_filter([$this->getContainer()->getStatePath(), $path], static fn (?string $part): bool => filled($part))) : $path;
    }

    /** @return list<Field> */
    public function getFileMetadataTemplates(): array
    {
        $schema = $this->evaluate($this->flexEditorialSchema) ?? [
            FlexTextInput::make('title')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.title'))->maxLength(4000),
            FlexTextInput::make('alt')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.alt'))->maxLength(4000),
            FlexTextareaField::make('caption')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.caption'))->maxLength(4000)->rows(3),
            FlexTextInput::make('copyright')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.copyright'))->maxLength(4000),
            TagsField::make('tags')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.tags'))->maxTags(50)->rules(['array', 'max:50'])->nestedRecursiveRules(['string', 'max:80', 'distinct']),
        ];
        if (! is_array($schema) || $schema === [] || count($schema) > 32) {
            throw new InvalidArgumentException('File metadata schema requires 1–32 field templates.');
        }
        $names = [];
        foreach ($schema as $template) {
            if (! $template instanceof Field || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $template->getName()) !== 1) {
                throw new InvalidArgumentException('File metadata templates must use FLEX field renderers and simple relative names.');
            }
            try {
                $view = $template->getView();
            } catch (\LogicException $error) {
                throw new InvalidArgumentException('File metadata templates must use FLEX field renderers.', previous: $error);
            }
            if (! str_starts_with($view, 'filament-flex-fields::')) {
                throw new InvalidArgumentException('File metadata templates must use FLEX field renderers.');
            }
            if (isset($names[$template->getName()])) {
                throw new InvalidArgumentException('File metadata field names must be unique.');
            }
            if ($template instanceof TagsField && in_array($template->getName(), $this->flexEditorialTranslated, true)) {
                throw new InvalidArgumentException('Tags fields must remain shared across metadata locales.');
            }
            if ($template->getName() !== 'tags' && $template instanceof TagsField) {
                throw new InvalidArgumentException('The metadata tags field must use the tags key.');
            }
            $names[$template->getName()] = true;
        }

        $this->getFileMetadataDefaultLocale();
        foreach ($this->flexEditorialRequiredInDefaultLocale as $name) {
            if (! isset($names[$name]) || ! in_array($name, $this->flexEditorialTranslated, true) || $this->getFileMetadataLocales() === []) {
                throw new InvalidArgumentException('Default-locale requirements must reference configured translated fields.');
            }
        }

        return array_values($schema);
    }

    /** @return list<Component> */
    public function getFileMetadataEditorSchema(): array
    {
        $templates = array_map(static fn (Field $field): Field => $field->getClone(), $this->getFileMetadataTemplates());
        $locales = $this->getFileMetadataLocales();
        if ($locales === []) {
            return array_map(fn (Field $field): Field => $this->configureEditorialField($field, $field->getName()), $templates);
        }
        $translated = array_values(array_filter($templates, fn (Field $field): bool => in_array($field->getName(), $this->flexEditorialTranslated, true)));
        $shared = array_values(array_filter($templates, fn (Field $field): bool => ! in_array($field->getName(), $this->flexEditorialTranslated, true)));

        $defaultIndex = array_search($this->getFileMetadataDefaultLocale(), array_keys($locales), true);
        $tabs = TranslatableTabs::make('file_metadata_locales')->locales($locales)->schema($translated)
            ->activeTab(($defaultIndex === false ? 0 : $defaultIndex) + 1)
            ->modifyFieldsUsing(function (Field $field): void {
                $this->configureEditorialField($field, $field->getName());
            });
        $shared = array_map(fn (Field $field): Field => $this->configureEditorialField($field, $field->getName()), $shared);

        return [...($translated === [] ? [] : [$tabs]), ...$shared];
    }

    public function registerFileMetadataAction(): void
    {
        $this->registerActions([fn (): Action => $this->buildFileMetadataAction()]);
    }

    private function buildFileMetadataAction(): Action
    {
        $action = Action::make('editFileMetadata')->label(fn () => Translations::get('filament-flex-fields::default.file_upload.metadata.edit'))
            ->icon('hugeicons-file-edit')->visible(fn (): bool => $this->hasFileMetadataEditor())
            ->disabled(fn (): bool => $this->isDisabled())->schema(fn (): array => $this->getFileMetadataEditorSchema())
            ->fillForm(fn (array $arguments): array => $this->readFileEditorialMetadata((string) ($arguments['fileKey'] ?? '')))
            ->action(function (array $arguments, array $data, Schema $schema): void {
                try {
                    $this->writeFileEditorialMetadata((string) ($arguments['fileKey'] ?? ''), $data);
                } catch (ValidationException $error) {
                    $messages = [];
                    foreach ($error->errors() as $path => $errors) {
                        $relative = str_starts_with($path, 'metadata.') ? substr($path, 9) : $path;
                        $messages[implode('.', array_filter([$schema->getStatePath(), $relative]))] = $errors;
                    }
                    throw ValidationException::withMessages($messages);
                }
            });
        if ($this->flexEditorialAction !== null) {
            $this->evaluate($this->flexEditorialAction, ['action' => $action]);
        }

        return $action;
    }

    private function editorialFile(string $key, bool $writing = true): TemporaryUploadedFile|string
    {
        if (! $this->hasFileMetadataEditor() || ($writing && $this->isDisabled())) {
            throw new MediaFailure('denied', 'File metadata editing is disabled.');
        }
        $file = ($this->getRawState() ?? [])[$key] ?? null;
        if (! $file instanceof TemporaryUploadedFile && ! is_string($file)) {
            throw new MediaFailure('file_missing', 'The attachment no longer belongs to this field.');
        }
        if (is_string($file)) {
            if ($this instanceof FlexSpatieMediaLibraryFileUpload) {
                app(SpatieUploadBridge::class)->authorizeMetadata($this, $file);
            } else {
                app(DiskUploadBridge::class)->authorizeMetadata($this, $file);
            }
        }

        return $file;
    }

    /** @return array<string, mixed> */
    public function readFileEditorialMetadata(string $fileKey): array
    {
        $file = $this->editorialFile($fileKey, false);
        $id = is_string($file) ? $file : $fileKey;
        $all = ($this->getGetCallback())($this->getFileMetadataStatePath()) ?? [];
        $data = is_array($all) && is_array($all[$id] ?? null) ? $all[$id] : [];
        if ($data === [] && is_string($file) && $this instanceof FlexSpatieMediaLibraryFileUpload) {
            $stored = app(SpatieUploadBridge::class)->find($this, $file)?->getCustomProperty('editorial', []);
            $data = is_array($stored) ? $stored : [];
        }

        // Existing drafts must remain editable when a new required rule is introduced.
        return $this->validateFileEditorialMetadata($data, false);
    }

    /** @param array<string, mixed> $data */
    public function writeFileEditorialMetadata(string $fileKey, array $data): void
    {
        $file = $this->editorialFile($fileKey);
        $data = $this->validateFileEditorialMetadata($data);
        $path = $this->getFileMetadataStatePath();
        $all = ($this->getGetCallback())($path) ?? [];
        $all = is_array($all) ? $all : [];
        $all[is_string($file) ? $file : $fileKey] = $data;
        ($this->getSetCallback())($path, $all);
        app(UploadRecordLifecycle::class)->track($this);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateFileEditorialMetadata(array $data, bool $withFieldRules = true): array
    {
        $templates = $this->getFileMetadataTemplates();
        $locales = array_keys($this->getFileMetadataLocales());
        $rules = [];
        foreach ($withFieldRules ? $templates : [] as $template) {
            $paths = in_array($template->getName(), $this->flexEditorialTranslated, true) && $locales !== []
                ? array_map(fn (string $locale): string => $template->getName().'.'.$locale, $locales)
                : [$template->getName()];
            foreach ($paths as $path) {
                $clone = $template->getClone()->container($this->getContainer())->statePath($path);
                $this->configureEditorialField($clone, $path, false);
                $native = [];
                $clone->dehydrateValidationRules($native);
                $prefix = $this->getContainer()->getStatePath();
                foreach ($native as $key => $value) {
                    $relative = filled($prefix) ? substr($key, strlen($prefix) + 1) : $key;
                    $rules[$relative] = $value;
                }
            }
        }

        return EditorialMetadata::validate($data, array_map(static fn (Field $field): string => $field->getName(), $templates),
            $this->flexEditorialTranslated, $locales, $withFieldRules ? [...$rules, ...$this->flexEditorialRules] : []);
    }

    /** Move metadata to its permanent native identity without changing receipt/configuration fingerprints. */
    public function promoteFileEditorialMetadata(TemporaryUploadedFile $file, string $identifier): void
    {
        if (! $this->hasFileMetadataEditor()) {
            return;
        }
        $path = $this->getFileMetadataStatePath();
        $all = ($this->getGetCallback())($path) ?? [];
        $all = is_array($all) ? $all : [];
        foreach ($this->getRawState() ?? [] as $key => $value) {
            if ($value instanceof TemporaryUploadedFile && $value->getFilename() === $file->getFilename() && isset($all[$key])) {
                $all[$identifier] = $this->validateFileEditorialMetadata($all[$key]);
                unset($all[$key]);
            }
        }
        ($this->getSetCallback())($path, $all);
    }

    /** @return array<string, array<string, mixed>> */
    public function getFileEditorialState(): array
    {
        $all = ($this->getGetCallback())($this->getFileMetadataStatePath()) ?? [];
        $result = [];
        foreach ($this->getRawState() ?? [] as $key => $file) {
            $id = is_string($file) ? $file : $key;
            if (is_array($all) && isset($all[$id]) && is_array($all[$id])) {
                $result[$id] = $this->validateFileEditorialMetadata($all[$id]);
            }
        }

        return $result;
    }

    public function restoreFileEditorialMetadata(string $identifier, TemporaryUploadedFile $temporary): void
    {
        if (! $this->hasFileMetadataEditor()) {
            return;
        }
        $path = $this->getFileMetadataStatePath();
        $all = ($this->getGetCallback())($path) ?? [];
        if (! is_array($all) || ! isset($all[$identifier])) {
            return;
        }
        foreach ($this->getRawState() ?? [] as $key => $file) {
            if ($file instanceof TemporaryUploadedFile && $file->getFilename() === $temporary->getFilename()) {
                $all[$key] = $all[$identifier];
                break;
            }
        }
        unset($all[$identifier]);
        ($this->getSetCallback())($path, $all);
    }

    /** Native companion state participates in disk model dehydration only when explicitly configured. */
    /** @param array<string, mixed> $state
     * @param-out array<array-key, mixed> $state
     */
    public function dehydrateState(array &$state, bool $isDehydrated = true): void
    {
        parent::dehydrateState($state, $isDehydrated);
        if ($isDehydrated && $this->isDehydrated() && $this->hasFileMetadataEditor() && $this->flexEditorialStatePath !== null) {
            Arr::set($state, $this->getFileMetadataStatePath(true), $this->getFileEditorialState());
        }
    }

    /** Called after record commit, preserving unrelated Spatie properties and internal provenance. */
    public function syncFileEditorialMetadata(): void
    {
        if (! $this->hasFileMetadataEditor() || ! $this instanceof FlexSpatieMediaLibraryFileUpload) {
            return;
        }
        foreach ($this->getFileEditorialState() as $uuid => $data) {
            app(SpatieUploadBridge::class)->authorizeMetadata($this, $uuid);
            $media = app(SpatieUploadBridge::class)->find($this, $uuid) ?? throw new MediaFailure('denied', 'Media owner changed before metadata persistence.');
            if ($media->getCustomProperty('editorial') !== $data) {
                $media->setCustomProperty('editorial', $data);
                if (! $media->save()) {
                    throw new MediaFailure('metadata_write_failed', 'Unable to persist owned media metadata.');
                }
            }
        }
    }
}
