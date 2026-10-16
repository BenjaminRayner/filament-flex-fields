<?php

declare(strict_types=1);

// Real native forms and disposable disks. No host records or tests are modified.
use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieTagsField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableTabs;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\DiskUploadBridge;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEvent;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadLeaseRepository;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifest;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarPlaceholder;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\TagsFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\Http\SafeRemoteUrlValidator;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaAccessPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Contracts\MediaProcessor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFormat;
use Bjanczak\FilamentFlexFields\Support\Media\MediaExtensions;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\PreparedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Spatie\SpatieUploadBridge;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageRepository;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Tags\HasTags;
use Spatie\Tags\Tag;
use Spatie\Tags\TagsServiceProvider;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = Application::create(null, null, ['extra' => ['providers' => [BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class, BladeHugeiconsServiceProvider::class, SupportServiceProvider::class, ActionsServiceProvider::class,
    SchemasServiceProvider::class, FormsServiceProvider::class, FilamentFlexFieldsServiceProvider::class, LivewireServiceProvider::class]]]);
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error.PHP_EOL);
    exit(1);
});
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('u', 32)));
$app['config']->set('cache.default', 'array');
$app['config']->set('livewire.temporary_file_upload.disk', 'upload-probe-temp');
$app['config']->set('filament-flex-fields.media_capture.uploads.manifest_disk', 'upload-probe-manifests');
foreach (['upload-probe-temp', 'upload-probe-original', 'upload-probe-conversions', 'upload-probe-manifests'] as $disk) {
    $app['config']->set('filesystems.disks.'.$disk, ['driver' => 'local', 'root' => sys_get_temp_dir().'/fff-upload-probe/'.$disk]);
    Storage::fake($disk);
}
final class UploadMediaProbeForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public array $data = [];

    public static array $fields = [];

    public static ?Model $record = null;

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::$fields)->statePath('data')->model(self::$record);
    }

    public function persistAttachment(array $editorial = []): void
    {
        $field = $this->getSchema('form')->getComponent('attachment');
        foreach ($editorial as $key => $values) {
            $field->writeFileEditorialMetadata($key, $values);
        }
        $this->getSchema('form')->getState();
        $this->getSchema('form')->saveRelationships();
    }

    public function replaceAttachment(string $fileKey, string $replacedKey, ?array $editorial = null): void
    {
        $this->getSchema('form')->getComponent('attachment')->completeImageEditorReplacement($fileKey, $replacedKey, $editorial);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
$checks = 0;
function uploadCheck(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function uploadProbe(BaseFileUpload $field, UploadedFile $file): array
{
    UploadMediaProbeForm::$fields = [$field, FlexTextInput::make('metadata')->hidden()->dehydrated(false)];
    $key = (string) Str::uuid();
    $livewire = Livewire::test(UploadMediaProbeForm::class)->upload('data.attachment.'.$key, [$file]);
    $form = $livewire->instance()->getSchema('form');

    return [$livewire, $form, $form->getComponent('attachment'), $key];
}
$events = [];
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('shared')->multiple()
    ->storeMetadataIn('metadata')->deleteFileOnRemove()->openable()->downloadable()
    ->imageConversions([
        new ImageConversion('thumb', 16, 16, fit: ImageFit::Cover, disk: 'upload-probe-conversions', background: '#ffffff00'),
        new ImageConversion('display', 32, 32, format: ImageFormat::Jpeg, disk: 'upload-probe-conversions'),
    ])->previewConversion('thumb')->onUploadEvent(UploadEventType::Stored, function (UploadEvent $event) use (&$events): void {
        $events[] = $event;
    })->onUploadEvent(UploadEventType::Deleted, function (UploadEvent $event) use (&$events): void {
        $events[] = $event;
    });
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('photo.png', 64, 48));
$form->getState();
$path = array_values($field->getRawState())[0];
$repository = app(UploadManifestRepository::class);
$manifest = $repository->read('upload-probe-original', $path);
uploadCheck($manifest !== null && count($manifest->variants) === 2, 'All named conversions must have durable receipts.');
uploadCheck($manifest->original->file->width === 64 && $manifest->original->file->height === 48, 'Source dimensions must describe inspected bytes.');
uploadCheck($manifest->variants['thumb']->file->width === 16 && $manifest->variants['thumb']->file->height === 16, 'Conversion metadata must describe encoded bytes.');
uploadCheck($manifest->variants['thumb']->file->mimeType === 'image/webp', 'Thumbnail must use requested encoding.');
uploadCheck(Storage::disk('upload-probe-original')->exists($path), 'Original must remain preserved.');
uploadCheck(count(Storage::disk('upload-probe-conversions')->allFiles()) === 2, 'Conversions must use independent disks.');
$fresh = new UploadManifestRepository($app['encrypter']);
uploadCheck($fresh->read('upload-probe-original', $path)->variants['thumb']->identifier === $manifest->variants['thumb']->identifier, 'A new request must restore exact variant receipts.');
$encrypted = Storage::disk('upload-probe-manifests')->get(Storage::disk('upload-probe-manifests')->allFiles()[0]);
uploadCheck(! str_contains($encrypted, $path) && ! str_contains($encrypted, 'photo.png'), 'Private manifest must not contain plaintext paths or names.');
$metadata = $livewire->instance()->data['metadata'];
uploadCheck($metadata[$path]['mime'] === 'image/png' && $metadata[$path]['conversions']['thumb']['mime'] === 'image/webp', 'Metadata must separate source and derivatives.');
uploadCheck(count($events) === 1 && $events[0]->type === UploadEventType::Stored, 'Store notification must settle once.');
Storage::disk('upload-probe-original')->buildTemporaryUrlsUsing(fn (string $path): string => 'https://original.example.test/'.$path);
Storage::disk('upload-probe-conversions')->buildTemporaryUrlsUsing(fn (string $path): string => 'https://preview.example.test/'.$path);
$payload = app(DiskUploadBridge::class)->payload($field, $path, null);
uploadCheck(str_starts_with($payload['url'], 'https://preview.example.test/'), 'Preview must use conversion URL.');
uploadCheck(str_starts_with($payload['openableUrl'], 'https://original.example.test/'), 'Open action must use original URL.');
Storage::disk('upload-probe-original')->put('shared/neighbor.txt', 'unrelated');
$expectedBytes = array_sum(array_map(static fn ($receipt): int => $receipt->file->bytes, $manifest->receipts()));
$field->deleteUploadedFile($key);
uploadCheck(! Storage::disk('upload-probe-original')->exists($path) && Storage::disk('upload-probe-conversions')->allFiles() === [], 'Removal must delete complete cross-disk bundle.');
uploadCheck(Storage::disk('upload-probe-original')->exists('shared/neighbor.txt'), 'Removal must never scan a shared directory.');
uploadCheck($fresh->read('upload-probe-original', $path) !== null, 'Request snapshot cache is isolated.');
uploadCheck((new UploadManifestRepository($app['encrypter']))->read('upload-probe-original', $path) === null, 'Future request must see deleted manifest.');
uploadCheck($events[1]->bytesFreed === $expectedBytes, 'Freed-byte notification must count only successful receipt deletion.');
uploadCheck(($livewire->instance()->data['metadata'] ?? []) === [], 'Removing attachment must remove its metadata.');

$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('failure')
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')])
    ->mediaFinalizeUsing(static function (): void {
        throw new RuntimeException('Deliberate precommit rejection');
    });
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('rejected.png', 64, 48));
try {
    $form->getState();
    throw new RuntimeException('Precommit veto did not reject.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'finalizer_failed', 'Precommit veto must retain typed engine failure.');
}
uploadCheck(Storage::disk('upload-probe-original')->allFiles('failure') === [] && Storage::disk('upload-probe-conversions')->allFiles() === [], 'Rejected bundle must compensate original and variants.');
uploadCheck($field->getRawState()[$key] instanceof TemporaryUploadedFile, 'Failed persistence must keep native temporary file for retry.');

$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('notifications')
    ->onUploadEvent(UploadEventType::Stored, static function (): void {
        throw new RuntimeException('Deliberate listener failure');
    });
[$livewire, $form, $field] = uploadProbe($field, UploadedFile::fake()->image('listener.png', 16, 16));
$form->getState();
uploadCheck(count(Storage::disk('upload-probe-original')->allFiles('notifications')) === 1, 'Listener failure must not cause upload failure or duplicate write.');

$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('aggregate')->multiple()->maxSize(10)->maxTotalSizeKb(4);
UploadMediaProbeForm::$fields = [$field];
$livewire = Livewire::test(UploadMediaProbeForm::class)
    ->upload('data.attachment.'.Str::uuid(), [UploadedFile::fake()->createWithContent('one.txt', str_repeat('x', 3072))])
    ->upload('data.attachment.'.Str::uuid(), [UploadedFile::fake()->createWithContent('two.txt', str_repeat('y', 3072))]);
$form = $livewire->instance()->getSchema('form');
try {
    $form->getState();
    throw new RuntimeException('Aggregate size did not reject.');
} catch (ValidationException) {
    uploadCheck(Storage::disk('upload-probe-original')->allFiles('aggregate') === [], 'Aggregate limit must reject complete collection before any persistence.');
}

$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('scanner')
    ->mediaPolicy(new MediaPolicy(requireScanner: true));
[$livewire, $form] = uploadProbe($field, UploadedFile::fake()->image('scan.png', 16, 16));
try {
    $form->getState();
    throw new RuntimeException('Required scanner did not reject.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'scanner_unavailable', 'Required missing scanner must fail closed.');
}
uploadCheck(Storage::disk('upload-probe-original')->allFiles('scanner') === [], 'Required scanner failure must leave no artifacts.');

$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('optimize')
    ->optimizeImagesToWebp()->maxImageWidth(24)->maxImageHeight(24);
[$livewire, $form, $field] = uploadProbe($field, UploadedFile::fake()->image('compatibility.png', 64, 48));
$form->getState();
$path = array_values($field->getRawState())[0];
$manifest = $repository->read('upload-probe-original', $path);
uploadCheck($manifest->primary === 'optimized' && $manifest->primaryReceipt()->file->mimeType === 'image/webp', 'Legacy optimization must use engine conversion and stable primary state.');
uploadCheck($manifest->primaryReceipt()->file->width === 24 && $manifest->primaryReceipt()->file->height === 18, 'Legacy dimensions must use aspect-preserving engine geometry.');
uploadCheck(Storage::disk('upload-probe-original')->exists($manifest->original->path), 'Optimization must preserve source until explicit bundle removal.');

// Corrupt receipt data must fail closed, never turn into a guessed legacy deletion.
$manifestPath = null;
foreach (Storage::disk('upload-probe-manifests')->allFiles() as $candidate) {
    $hash = hash('sha256', 'upload-probe-original'."\0".$path);
    if (str_ends_with($candidate, $hash.'.receipt')) {
        $manifestPath = $candidate;
        break;
    }
}
$encrypted = Storage::disk('upload-probe-manifests')->get($manifestPath);
Storage::disk('upload-probe-manifests')->put($manifestPath, 'corrupt');
try {
    (new UploadManifestRepository($app['encrypter']))->read('upload-probe-original', $path);
    throw new RuntimeException('Corrupt manifest was accepted.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'manifest_invalid', 'Corrupt receipts must reject explicitly.');
}
Storage::disk('upload-probe-manifests')->put($manifestPath, $encrypted);

// A failed manifest write compensates the entire bundle and restores previous sidecar state.
$realManifestDisk = Storage::disk('upload-probe-manifests');
$failingManifestDisk = Mockery::mock($realManifestDisk);
$failingManifestDisk->shouldReceive('put')->once()->andReturn(false);
Storage::set('upload-probe-manifests', $failingManifestDisk);
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('journal-failure')->storeMetadataIn('metadata')
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')]);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('failed-journal.png', 64, 48));
$livewire->instance()->data['metadata'] = ['retained' => true];
try {
    $form->getState();
    throw new RuntimeException('Manifest write failure was ignored.');
} catch (MediaFailure) {
    uploadCheck(Storage::disk('upload-probe-original')->allFiles('journal-failure') === [] && Storage::disk('upload-probe-conversions')->allFiles() === [], 'Journal failure must compensate original and conversion.');
}
uploadCheck($livewire->instance()->data['metadata'] === ['retained' => true], 'Journal failure must restore previous metadata.');
uploadCheck($field->getRawState()[$key] instanceof TemporaryUploadedFile, 'Journal failure must preserve temporary upload.');
Storage::set('upload-probe-manifests', $realManifestDisk);

// Partial removal keeps the native item and receipts; retry skips already removed members.
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('retry')->deleteFileOnRemove()
    ->storeMetadataIn('metadata')->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')]);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('retry.png', 64, 48));
$form->getState();
$path = $field->getRawState()[$key];
$realOriginalDisk = Storage::disk('upload-probe-original');
$failingOriginalDisk = Mockery::mock($realOriginalDisk);
$failingOriginalDisk->shouldReceive('delete')->with($path)->once()->andReturn(false);
Storage::set('upload-probe-original', $failingOriginalDisk);
try {
    $field->deleteUploadedFile($key);
    throw new RuntimeException('Partial deletion failure was ignored.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'cleanup_failed', 'Removal must report the failed storage operation.');
}
uploadCheck($field->getRawState()[$key] === $path && $repository->read('upload-probe-original', $path) !== null, 'Failed removal must retain field state and receipts for retry.');
uploadCheck(Storage::disk('upload-probe-conversions')->allFiles() === [], 'Successful derivative removal must not be repeated.');
Storage::set('upload-probe-original', $realOriginalDisk);
$field->deleteUploadedFile($key);
uploadCheck(($field->getRawState() ?? []) === [] && ! $realOriginalDisk->exists($path), 'Retry must finish removal without losing state prematurely.');

// Every variant must pass access policy before the first member can be deleted.
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('authorization')->deleteFileOnRemove()
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')]);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('owned.png', 64, 48));
$form->getState();
$path = $field->getRawState()[$key];
$deny = new class implements MediaAccessPolicy
{
    public bool $active = true;

    public function authorize(string $action, MediaWrite $intent, ?MediaReceipt $receipt): void
    {
        if ($this->active && $action === 'delete' && ($intent->metadata['conversion'] ?? null) === 'thumb') {
            throw new MediaFailure('denied', 'Deliberate variant denial');
        }
    }
};
MediaExtensions::accessPolicy('upload-probe', fn () => $deny);
try {
    $field->deleteUploadedFile($key);
    throw new RuntimeException('Variant authorization was ignored.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'denied' && $realOriginalDisk->exists($path) && count(Storage::disk('upload-probe-conversions')->allFiles()) === 1, 'Denied variant must prevent deletion of every member.');
}
$deny->active = false;
$field->deleteUploadedFile($key);

// Preparing native state must not remove the previously accepted attachment.
$oldPath = 'replace/old.txt';
$realOriginalDisk->put($oldPath, 'old');
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('replace')->deleteReplacedFiles();
[$livewire, $form, $field] = uploadProbe($field, UploadedFile::fake()->createWithContent('new.txt', 'new'));
$newRaw = $field->getRawState();
$field->rawState(['old' => $oldPath]);
$field->snapshotBaselineFilePaths();
$field->rawState($newRaw);
$form->getState();
uploadCheck($realOriginalDisk->exists($oldPath), 'State dehydration must retain the previous attachment before record commit.');
$field->cleanupReplacedFilesAfterSave();
uploadCheck(! $realOriginalDisk->exists($oldPath), 'Explicit post-save cleanup must delete the abandoned owned attachment.');
$field->cleanupReplacedFilesAfterSave();
uploadCheck(count($realOriginalDisk->allFiles('replace')) === 1, 'Repeated post-save cleanup must preserve the current attachment.');
// Optional wrapper: real Media Library, real owned model, native conversions and full options.
$app['config']->set('database.default', 'upload-media-probe');
$app['config']->set('database.connections.upload-media-probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$app['config']->set('media-library.queue_conversions_by_default', false);
$app->register(MediaLibraryServiceProvider::class);
Illuminate\Support\Facades\Schema::create('upload_media_probe_records', function (Blueprint $table): void {
    $table->id();
    $table->text('attachment')->nullable();
    $table->json('editorial')->nullable();
    $table->timestamps();
});
$migration = dirname((new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2).'/database/migrations/create_media_table.php.stub';
(require $migration)->up();
final class UploadMediaProbeRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'upload_media_probe_records';

    protected $guarded = [];

    protected $casts = ['editorial' => 'array'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(16)->height(16)->format('webp')->nonQueued();
    }
}
$record = UploadMediaProbeRecord::create();
$spatieCalls = 0;
UploadMediaProbeForm::$record = $record;
$field = FlexSpatieMediaLibraryFileUpload::make('attachment')
    ->disk('upload-probe-original')->collection('photos')->conversionsDisk('upload-probe-conversions')->previewConversion('thumb')
    ->storeMetadataIn('metadata')->deleteFileOnRemove()->customProperties(['alt' => ['en' => 'A photograph', 'pl' => 'Zdjęcie']])
    ->spatieOptionsUsing(function ($options) use (&$spatieCalls) {
        return new SpatieOptions(collection: $options->collection,
            conversionsDisk: $options->conversionsDisk, customProperties: $options->customProperties,
            order: 7, configure: function ($adder) use (&$spatieCalls): void {
                $spatieCalls++;
                $adder->withAttributes(['name' => 'Configured photo']);
            });
    });
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('spatie.png', 64, 48));
$form->getState();
$form->saveRelationships();
$uuid = array_values($field->getRawState())[0];
$media = $record->fresh()->getFirstMedia('photos');
uploadCheck($media !== null && $media->uuid === $uuid && $spatieCalls === 1, 'Wrapper must store an owned native UUID with one options callback.');
uploadCheck($media->name === 'Configured photo' && $media->order_column === 7 && $media->getCustomProperty('alt.pl') === 'Zdjęcie', 'Wrapper must pass native FileAdder options and translated properties.');
uploadCheck($media->conversions_disk === 'upload-probe-conversions' && $media->hasGeneratedConversion('thumb'), 'Wrapper must let the model run conversions on its configured disk.');
uploadCheck($media->getCustomProperty('flex_capture.width') === 64 && strlen($media->getCustomProperty('flex_capture.sha256')) === 64, 'Wrapper must retain inspected metadata and integrity stamp.');
$payload = app(SpatieUploadBridge::class)->payload($field, $uuid);
uploadCheck(str_contains($payload['url'], 'thumb'), 'Wrapper preview must use the actual generated thumbnail.');
$stranger = UploadMediaProbeRecord::create();
$other = clone $field;
$other->model($stranger);
uploadCheck(app(SpatieUploadBridge::class)->payload($other, $uuid) === null, 'Wrapper must refuse another record UUID.');
$field->deleteStoredPathWithVariants($uuid);
uploadCheck($record->fresh()->getMedia('photos')->isEmpty(), 'Wrapper deletion must remove the owned row and variants through Media OS.');
UploadMediaProbeForm::$record = null;

// Accepted native state, lost-response retry and transactional rollback use disposable SQLite records.
$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$accepted = 0;
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('lease-retry')->deleteReplacedFiles()
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')])
    ->onUploadEvent(UploadEventType::Accepted, static function () use (&$accepted): void {
        $accepted++;
    });
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('stable.png', 48, 32));
$temporary = $field->getRawState()[$key];
$form->getState();
$path = $field->getRawState()[$key];
$result = $repository->read('upload-probe-original', $path);
$leaseKey = $result->context['metadata']['flex_upload_lease'];
$leases = app(UploadLeaseRepository::class);
$lease = $leases->read($leaseKey);
$stages = app(StageRepository::class);
uploadCheck($temporary->exists() && $lease->id === $result->original->operationId, 'A native lost-response retry must retain its source and stable operation identity.');
$field->rawState([$key => $temporary]);
$form->getState();
uploadCheck($field->getRawState()[$key] === $path && count($realOriginalDisk->allFiles('lease-retry')) === 1, 'Retry must reuse the original receipt without another write.');
uploadCheck(count(Storage::disk('upload-probe-conversions')->allFiles()) === 1, 'Retry must not create a second conversion set.');
$foreign = clone $field;
$foreign->mediaStagingOwnerUsing(static fn (): string => 'different-owner');
try {
    $foreign->abandonMediaUpload($leaseKey);
    throw new RuntimeException('Foreign lease owner was accepted.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'denied', 'Lease operations must reject another form owner.');
}
DB::beginTransaction();
$record->update(['attachment' => $path]);
$field->completeMediaUploadsAfterSave($record);
uploadCheck(! $leases->read($leaseKey)->accepted && Storage::disk($stages->options->disk)->exists($stages->payloadPath($lease->id)), 'Record acceptance must wait until DB commit.');
DB::commit();
uploadCheck($leases->read($leaseKey)->accepted && ! Storage::disk($stages->options->disk)->exists($stages->payloadPath($lease->id)) && $accepted === 1, 'After commit must accept once and release the retained source.');
$field->completeMediaUploadsAfterSave($record);
uploadCheck($accepted === 1, 'Repeated record confirmation must not duplicate acceptance notification.');
$field->deleteStoredPathWithVariants($path);
uploadCheck($leases->read($leaseKey)->removed, 'Confirmed removal must close its source lease.');

// A rolled-back DB transaction must restore the native retry state and remove all created variants.
$record->update(['attachment' => 'rollback/old.txt']);
$realOriginalDisk->put('rollback/old.txt', 'old accepted bytes');
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('rollback')->deleteReplacedFiles()
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')])->storeMetadataIn('metadata');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('rollback.png', 48, 32));
$newRaw = $field->getRawState();
$field->rawState(['old' => 'rollback/old.txt']);
$field->snapshotBaselineFilePaths();
$field->rawState($newRaw);
DB::beginTransaction();
DB::beginTransaction();
$form->getState();
$newPath = $field->getRawState()[$key];
$result = $repository->read('upload-probe-original', $newPath);
$record->update(['attachment' => $newPath]);
$field->completeMediaUploadsAfterSave($record);
DB::commit();
uploadCheck($realOriginalDisk->exists($newPath) && $realOriginalDisk->exists('rollback/old.txt'), 'Nested commit must retain old media until outer acceptance.');
DB::rollBack();
uploadCheck(! $realOriginalDisk->exists($newPath) && Storage::disk('upload-probe-conversions')->allFiles() === [], 'Outer rollback must compensate original and all conversions.');
uploadCheck($field->getRawState()[$key] instanceof TemporaryUploadedFile && $field->getRawState()[$key]->exists(), 'Rollback must restore native temporary state without selecting the file again.');
uploadCheck($record->fresh()->attachment === 'rollback/old.txt' && $realOriginalDisk->exists('rollback/old.txt'), 'Rollback must preserve the previous DB reference and stored file.');
uploadCheck(($livewire->instance()->data['metadata'] ?? []) === [], 'Rollback must clear metadata for its compensated upload.');
$record->refresh();
DB::beginTransaction();
$form->getState();
$retryPath = $field->getRawState()[$key];
$record->update(['attachment' => $retryPath]);
$field->completeMediaUploadsAfterSave($record);
DB::commit();
uploadCheck($realOriginalDisk->exists($retryPath) && ! $realOriginalDisk->exists('rollback/old.txt'), 'A retried commit must keep the new bundle and then remove the old accepted attachment.');
UploadMediaProbeForm::$record = null;

// Native Eloquent save integration confirms disk media without an explicit completion call.
$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('native-save');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('native.txt', 'native record lifecycle'));
$form->getState();
$nativePath = $field->getRawState()[$key];
$nativeLeaseKey = $repository->read('upload-probe-original', $nativePath)->context['metadata']['flex_upload_lease'];
$record->update(['attachment' => $nativePath]);
uploadCheck($leases->read($nativeLeaseKey)->accepted, 'Native model saves must accept referenced disk uploads automatically.');
$field->deleteStoredPathWithVariants($nativePath);

// Staged Spatie singleFile must defer destructive collection rules until DB commit.
$record = UploadMediaProbeRecord::create();
$oldMedia = $record->addMedia(UploadedFile::fake()->image('accepted.png', 48, 32))->toMediaCollection('portrait', 'upload-probe-original');
$oldUuid = $oldMedia->uuid;
$oldSpatiePath = $oldMedia->getPathRelativeToRoot();
UploadMediaProbeForm::$record = $record;
$field = FlexSpatieMediaLibraryFileUpload::make('attachment')->disk('upload-probe-original')->collection('portrait')
    ->conversionsDisk('upload-probe-conversions')->storeMetadataIn('metadata');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('replacement.png', 64, 48));
$nativeTemporary = $field->getRawState()[$key];
$field->rawState([$key => $nativeTemporary]);
DB::beginTransaction();
$form->getState();
$newUuid = $field->getRawState()[$key];
$newMedia = $record->media()->where('uuid', $newUuid)->firstOrFail();
$newSpatiePath = $newMedia->getPathRelativeToRoot();
uploadCheck($record->media()->where('uuid', $oldUuid)->exists() && $realOriginalDisk->exists($oldSpatiePath), 'Spatie singleFile must retain the old row and bytes before commit.');
DB::rollBack();
uploadCheck(! $record->media()->where('uuid', $newUuid)->exists() && ! $realOriginalDisk->exists($newSpatiePath), 'Spatie rollback must remove its newly created bytes and row.');
uploadCheck($record->media()->where('uuid', $oldUuid)->exists() && $realOriginalDisk->exists($oldSpatiePath), 'Spatie rollback must preserve the previously accepted media.');
uploadCheck($field->getRawState()[$key] instanceof TemporaryUploadedFile && $field->getRawState()[$key]->exists(), 'Spatie rollback must restore the temporary upload for retry.');
DB::beginTransaction();
$form->getState();
$committedUuid = $field->getRawState()[$key];
DB::commit();
uploadCheck($record->media()->where('collection_name', 'portrait')->count() === 1 && $record->media()->where('uuid', $committedUuid)->exists(), 'Spatie committed replacement must enforce its single-file collection.');
uploadCheck(! $realOriginalDisk->exists($oldSpatiePath), 'Spatie committed replacement must remove the old file only after DB commit.');
$field->deleteStoredPathWithVariants($committedUuid);
UploadMediaProbeForm::$record = null;

// Losing only the final binding checkpoint must preserve committed bytes and all receipts.
$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$bindingWrites = 0;
$checkpointDisk = Mockery::mock($realManifestDisk);
$checkpointDisk->shouldReceive('put')->andReturnUsing(function ($path, $contents, $options) use ($realManifestDisk, &$bindingWrites): bool {
    if (str_ends_with($path, '.upload') && ++$bindingWrites === 4) {
        return false;
    }

    return $realManifestDisk->put($path, $contents, $options);
});
Storage::set('upload-probe-manifests', $checkpointDisk);
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('last-checkpoint');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('checkpoint.txt', 'committed bytes'));
try {
    $form->getState();
    throw new RuntimeException('Post-commit binding checkpoint failure was ignored.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->cleanupFailed && $failure->reason === 'stage_ambiguous', 'A post-commit journal failure must remain uncertain rather than trigger receipt deletion.');
}
Storage::set('upload-probe-manifests', $realManifestDisk);
uploadCheck(count($realOriginalDisk->allFiles('last-checkpoint')) === 1, 'A post-commit journal failure must retain the committed original.');
$form->getState();
$checkpointPath = $field->getRawState()[$key];
uploadCheck($repository->read('upload-probe-original', $checkpointPath) !== null && count($realOriginalDisk->allFiles('last-checkpoint')) === 1, 'Checkpoint retry must keep the durable receipt and avoid a duplicate write.');
$record->update(['attachment' => $checkpointPath]);
$field->deleteStoredPathWithVariants($checkpointPath);

// Administrative reconciliation can restore both journals from independently located trusted receipts.
$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('reconciliation')
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')]);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('reconciled.png', 48, 32));
$temporary = $field->getRawState()[$key];
$form->getState();
$reconciledPath = $field->getRawState()[$key];
$reconciled = $repository->read('upload-probe-original', $reconciledPath);
$leaseKey = $reconciled->context['metadata']['flex_upload_lease'];
$lease = $leases->read($leaseKey);
$stage = $stages->load($lease->id);
$stage['state'] = 'promoting';
unset($stage['receipt']);
$stages->save($lease->id, $stage);
$lease->result = null;
$lease->expires = 1;
$leases->write($lease);
$repository->delete('upload-probe-original', $reconciledPath);
$pruning = $leases->prune(dryRun: true);
uploadCheck($pruning['uncertain'] > 0 && $leases->read($leaseKey) !== null, 'An expired binding with an interrupted core journal must be retained even without a result receipt.');
$recoveredEvents = 0;
$field->onUploadEvent(UploadEventType::Recovered, static function () use (&$recoveredEvents): void {
    $recoveredEvents++;
});
$recoveredPath = $field->recoverMediaUpload($leaseKey, $field->makeUploadMediaWrite($temporary), $reconciled);
uploadCheck($recoveredPath === $reconciledPath && $repository->read('upload-probe-original', $reconciledPath) !== null, 'Server reconciliation must restore the full native conversion manifest.');
uploadCheck(app(MediaStaging::class)->status($lease->id, $field->getMediaStagingOwner()) === 'committed', 'Reconciliation must complete the owned core lease.');
$repeatedRecovery = $field->recoverMediaUpload($leaseKey, $field->makeUploadMediaWrite($temporary), $reconciled);
uploadCheck($repeatedRecovery === $recoveredPath && $recoveredEvents === 1, 'Server recovery must be idempotent and notify once after completion.');
$field->rawState([$key => $temporary]);
$form->getState();
uploadCheck($field->getRawState()[$key] === $reconciledPath && count($realOriginalDisk->allFiles('reconciliation')) === 1, 'A recovered upload must reuse native state without another object.');
$record->update(['attachment' => $reconciledPath]);
$field->deleteStoredPathWithVariants($reconciledPath);
UploadMediaProbeForm::$record = null;

// A prepared source is processed once even if native submission loses its response.
$processor = new class implements MediaProcessor
{
    public int $calls = 0;

    public function process(PreparedMedia $file, MediaWrite $intent): PreparedMedia
    {
        $this->calls++;

        return $file;
    }
};
MediaExtensions::processor('upload-count', static fn () => $processor);
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('processor-once')->mediaProcessors(['upload-count']);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('processor.txt', 'processor bytes'));
$temporary = $field->getRawState()[$key];
$form->getState();
$processedPath = $field->getRawState()[$key];
$field->rawState([$key => $temporary]);
$form->getState();
uploadCheck($processor->calls === 1 && $field->getRawState()[$key] === $processedPath, 'Source processors must run once across staging, promotion and lost-response retry.');
$field->deleteStoredPathWithVariants($processedPath);

// Independent DB connections are not one distributed transaction.
$app['config']->set('database.connections.upload-other-probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
Illuminate\Support\Facades\Schema::connection('upload-other-probe')->create('upload_media_probe_records', function (Blueprint $table): void {
    $table->id();
    $table->text('attachment')->nullable();
    $table->timestamps();
});
$record = new UploadMediaProbeRecord;
$record->setConnection('upload-other-probe');
$record->save();
UploadMediaProbeForm::$record = $record;
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('separate-connection');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('separate.txt', 'separate DB bytes'));
DB::beginTransaction();
$form->getState();
$separatePath = $field->getRawState()[$key];
$record->update(['attachment' => $separatePath]);
DB::rollBack();
uploadCheck($record->fresh()->attachment === $separatePath && $realOriginalDisk->exists($separatePath), 'Rollback on another DB connection must not destroy separately committed referenced media.');
$field->deleteStoredPathWithVariants($separatePath);

// A rolled-back create can retry with a genuinely different model key.
DB::beginTransaction();
$record = UploadMediaProbeRecord::create();
$firstRecordKey = $record->getKey();
UploadMediaProbeForm::$record = $record;
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('create-retry');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('created.txt', 'new model bytes'));
$form->getState();
$createdPath = $field->getRawState()[$key];
$record->update(['attachment' => $createdPath]);
DB::rollBack();
uploadCheck(! $record->newQuery()->whereKey($firstRecordKey)->exists() && ! $realOriginalDisk->exists($createdPath) && $field->getRawState()[$key] instanceof TemporaryUploadedFile, 'Create rollback must compensate its upload and restore native source state.');
UploadMediaProbeRecord::create();
$next = UploadMediaProbeRecord::create();
uploadCheck($next->getKey() !== $firstRecordKey, 'Create retry fixture must use a different persisted model key.');
$field->model($next);
$form->getState();
$retriedCreatePath = $field->getRawState()[$key];
$next->update(['attachment' => $retriedCreatePath]);
uploadCheck($realOriginalDisk->exists($retriedCreatePath) && $next->fresh()->attachment === $retriedCreatePath, 'A rolled-back create lease must permit promotion for the next new model key.');
$field->deleteStoredPathWithVariants($retriedCreatePath);
UploadMediaProbeForm::$record = null;

// A native sidecar failure after publication must never erase committed receipts.
final class UploadPostPublicationProbeField extends FlexFileUpload
{
    public int $metadataWrites = 0;

    public function writeMetadataFromMediaReceipt(MediaReceipt $receipt, ?UploadManifest $manifest = null): void
    {
        if (++$this->metadataWrites === 2) {
            throw new RuntimeException('Post-publication sidecar failure');
        }
        parent::writeMetadataFromMediaReceipt($receipt, $manifest);
    }
}
$field = UploadPostPublicationProbeField::make('attachment')->disk('upload-probe-original')->directory('sidecar-checkpoint')->storeMetadataIn('metadata')
    ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')]);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('sidecar.png', 48, 32));
try {
    $form->getState();
    throw new RuntimeException('Post-publication sidecar failure was ignored.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->cleanupFailed && $failure->reason === 'upload_finalization_failed', 'Post-publication sidecar failure must preserve storage as uncertain.');
}
$sidecarSource = $realOriginalDisk->allFiles('sidecar-checkpoint')[0];
$sidecarResult = $repository->read('upload-probe-original', $sidecarSource);
uploadCheck($sidecarResult !== null && Storage::disk('upload-probe-conversions')->exists($sidecarResult->variants['thumb']->path), 'Post-publication sidecar failure must preserve the complete conversion manifest.');
$form->getState();
uploadCheck($field->getRawState()[$key] === $sidecarSource && count($realOriginalDisk->allFiles('sidecar-checkpoint')) === 1, 'Retry after sidecar failure must restore native state without another original or conversion.');
$field->deleteStoredPathWithVariants($sidecarSource);

final class SpatiePostPublicationProbeField extends FlexSpatieMediaLibraryFileUpload
{
    public int $metadataWrites = 0;

    public function writeMetadataFromMediaReceipt(MediaReceipt $receipt, ?UploadManifest $manifest = null): void
    {
        if (++$this->metadataWrites === 2) {
            throw new RuntimeException('Post-publication Spatie sidecar failure');
        }
        parent::writeMetadataFromMediaReceipt($receipt, $manifest);
    }
}
$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$field = SpatiePostPublicationProbeField::make('attachment')->disk('upload-probe-original')->collection('sidecar-checkpoint')->storeMetadataIn('metadata');
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('spatie-sidecar.png', 48, 32));
try {
    $form->getState();
    throw new RuntimeException('Post-publication Spatie sidecar failure was ignored.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->cleanupFailed && $failure->reason === 'upload_finalization_failed', 'Spatie post-publication failure must preserve its owned receipt for retry.');
}
$sidecarMedia = $record->media()->where('collection_name', 'sidecar-checkpoint')->firstOrFail();
$form->getState();
uploadCheck(array_values($field->getRawState())[0] === $sidecarMedia->uuid && $record->media()->where('collection_name', 'sidecar-checkpoint')->count() === 1, 'Spatie sidecar retry must reuse the owned UUID without a second native media row.');
$field->deleteStoredPathWithVariants($sidecarMedia->uuid);
UploadMediaProbeForm::$record = null;

// Optional configuration, native metadata actions and real shared translation composition.
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('editorial')
    ->fileMetadataEditor()->fileMetadataStatePath('editorial')->fileMetadataTranslations(['en', 'pl']);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('editorial.png', 48, 32));
$editorial = ['title' => ['en' => 'Photo', 'pl' => 'Zdjęcie'], 'alt' => ['en' => 'An image'], 'tags' => ['blue', 'sea']];
$field->writeFileEditorialMetadata($key, $editorial);
uploadCheck($field->readFileEditorialMetadata($key) === $editorial, 'Metadata must be editable before permanent storage.');
uploadCheck($field->getFileMetadataEditorSchema()[0] instanceof TranslatableTabs, 'Translated metadata must compose the real shared TranslatableTabs component.');
$livewire->set('data.editorial', [$key => $editorial]);
$livewire->call('mountAction', 'editFileMetadata', ['fileKey' => $key], ['schemaComponent' => $field->getKey()]);
uploadCheck(($livewire->instance()->mountedActions[0]['data']['title']['pl'] ?? null) === 'Zdjęcie', 'Native action must hydrate nested translated metadata.');
$livewire->set('mountedActions.0.data.title.en', 'Edited photo')->call('callMountedAction');
uploadCheck(($livewire->instance()->data['editorial'][$key]['title']['en'] ?? null) === 'Edited photo', 'Native metadata action must validate and save all locale fields.');
$field = $livewire->instance()->getSchema('form')->getComponent('attachment');
$state = $livewire->instance()->getSchema('form')->getState();
$path = $field->getRawState()[$key];
uploadCheck(($state['editorial'][$path]['title']['pl'] ?? null) === 'Zdjęcie', 'Disk metadata must dehydrate to the configured JSON companion with permanent identity.');
$editorialRecord = UploadMediaProbeRecord::create($state);
$form = $livewire->instance()->getSchema('form');
$form->model($editorialRecord);
$form->fill($editorialRecord->toArray());
$field = $form->getComponent('attachment');
$storedKey = array_search($path, $field->getRawState(), true);
uploadCheck($field->readFileEditorialMetadata($storedKey)['title']['pl'] === 'Zdjęcie', 'Disk edit must hydrate persisted companion translations using the permanent identity.');
$field->writeFileEditorialMetadata($storedKey, ['title' => ['pl' => 'After edit'], 'tags' => ['edited']]);
$editorialRecord->update($form->getState());
uploadCheck($editorialRecord->fresh()->editorial[$path]['title']['pl'] === 'After edit', 'Disk edit must save metadata without replacing existing bytes.');
// Rollback restores each file association, rather than assigning all metadata to the first temporary item.
$rollbackField = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('editorial-rollback')->multiple()
    ->fileMetadataEditor()->fileMetadataStatePath('editorial')->fileMetadataTranslations(['en', 'pl']);
[$rollbackLivewire, $rollbackForm, $rollbackField, $firstKey] = uploadProbe($rollbackField, UploadedFile::fake()->image('first.png', 48, 32));
$secondKey = (string) Str::uuid();
$rollbackLivewire->upload('data.attachment.'.$secondKey, [UploadedFile::fake()->image('second.png', 48, 32)]);
$rollbackForm = $rollbackLivewire->instance()->getSchema('form');
$rollbackField = $rollbackForm->getComponent('attachment');
$rollbackForm->model($editorialRecord);
$rollbackField->writeFileEditorialMetadata($firstKey, ['title' => ['en' => 'First']]);
$rollbackField->writeFileEditorialMetadata($secondKey, ['title' => ['en' => 'Second']]);
DB::beginTransaction();
$rollbackForm->getState();
DB::rollBack();
uploadCheck($rollbackField->getRawState()[$firstKey] instanceof TemporaryUploadedFile && $rollbackField->getRawState()[$secondKey] instanceof TemporaryUploadedFile, 'Editorial rollback must restore both native temporary uploads.');
uploadCheck($rollbackField->readFileEditorialMetadata($firstKey)['title']['en'] === 'First' && $rollbackField->readFileEditorialMetadata($secondKey)['title']['en'] === 'Second', 'Editorial rollback must preserve distinct per-file translations.');
$retryState = $rollbackForm->getState();
uploadCheck(count($retryState['editorial']) === 2 && array_column(array_column($retryState['editorial'], 'title'), 'en') === ['First', 'Second'], 'Retry must remap both metadata documents to their permanent identities.');
$field->rawState([]);
$state = $livewire->instance()->getSchema('form')->getState();
uploadCheck(($state['editorial'] ?? null) === [], 'Removing the last attachment must remove its editorial metadata from saved state.');
$customEditor = $field->getClone()->fileMetadataSchema([FlexTextInput::make('credit')->required()->maxLength(12)])
    ->fileMetadataTranslations(null);
try {
    $customEditor->validateFileEditorialMetadata(['credit' => str_repeat('x', 13)]);
    throw new RuntimeException('Custom native metadata length rule was bypassed.');
} catch (ValidationException) {
    uploadCheck(true, 'Native custom field constraints must also be revalidated at persistence.');
}
try {
    $customEditor->validateFileEditorialMetadata([]);
    throw new RuntimeException('Custom required metadata rule was bypassed.');
} catch (ValidationException) {
    uploadCheck(true, 'Native required metadata constraints must survive direct sidecar writes.');
}
$field->deleteStoredPathWithVariants($path);
try {
    $field->writeFileEditorialMetadata('foreign', $editorial);
    throw new RuntimeException('Unknown attachment metadata was accepted.');
} catch (MediaFailure $error) {
    uploadCheck($error->reason === 'file_missing', 'Metadata edits must belong to the current field attachment.');
}
try {
    $field->validateFileEditorialMetadata(['title' => ['unknown' => 'Injected locale']]);
    throw new RuntimeException('Unknown locale was accepted.');
} catch (ValidationException) {
    uploadCheck(true, 'Unknown metadata locales must be rejected.');
}
try {
    $field->validateFileEditorialMetadata(['flex_capture' => ['owner' => 'foreign']]);
    throw new RuntimeException('Internal metadata was accepted.');
} catch (ValidationException) {
    uploadCheck(true, 'Internal provenance cannot be edited through metadata.');
}
$avatar = FlexFileUpload::make('avatar')->avatar()->defaultAvatarUsing(static fn () => new AvatarPlaceholder('/avatars/seed.svg', 'Generated avatar'));
uploadCheck($avatar->getDefaultAvatar()?->url === '/avatars/seed.svg', 'Avatar providers must supply independently configurable presentation.');
UploadMediaProbeForm::$fields = [$avatar];
$avatarForm = Livewire::test(UploadMediaProbeForm::class)->instance()->getSchema('form');
$avatarForm->fill(['avatar' => null]);
$avatarHtml = $avatarForm->toHtml();
uploadCheck(str_contains($avatarHtml, 'src="/avatars/seed.svg"') && str_contains($avatarHtml, 'naturalWidth'), 'Default avatar must render its own image and handle delivery failure before Alpine initializes.');
uploadCheck(($avatarForm->getState()['avatar'] ?? null) === null, 'Default avatar artwork must not become a persisted upload value.');
$avatar->defaultAvatarEnabled(false);
uploadCheck($avatar->getDefaultAvatar() === null, 'Default avatar providers must be independently disableable.');
$avatar->defaultAvatarEnabled()->defaultAvatarUsing(static function (): never {
    throw new RuntimeException('Provider unavailable');
})
    ->defaultAvatarFallbackUsing(static fn () => '/avatars/fallback.png');
uploadCheck($avatar->getDefaultAvatar()?->url === '/avatars/fallback.png', 'Avatar provider failures must use the configured fallback.');
try {
    new AvatarPlaceholder('javascript:alert(1)');
    throw new RuntimeException('Unsafe avatar URI was accepted.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Unsafe avatar URI schemes must be rejected.');
}
$observed = [];
$field = FlexFileUpload::make('attachment')->onUploadTransportEvent(null, static function ($event) use (&$observed): void {
    $observed[] = $event;
});
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('transport.txt', 'transport bytes'));
$session = (string) Str::uuid();
$batch = [['type' => 'started', 'sequence' => 1, 'fileId' => 'client-one', 'source' => 'url'], ['type' => 'progress', 'sequence' => 2, 'fileId' => 'client-one', 'progress' => 71]];
$field->reportUploadTransportEvents($session, $batch);
$field->reportUploadTransportEvents($session, $batch);
uploadCheck(count($observed) === 2 && $observed[1]->progress === 71.0 && $observed[0]->source === 'url', 'PHP transport notifications must be typed and deduplicated per owned session.');
uploadCheck($field->getRawState()[$key] instanceof TemporaryUploadedFile, 'Transport observations must never promote files or change authoritative upload state.');
$field->disabled();
$field->reportUploadTransportEvents($session, [['type' => 'completed', 'sequence' => 3]]);
uploadCheck(count($observed) === 2, 'Disabled fields must not accept transport notifications.');
$field->disabled(false);
try {
    $field->reportUploadTransportEvents($session, [['type' => 'progress', 'sequence' => 3, 'progress' => 900]]);
    throw new RuntimeException('Invalid transport progress was accepted.');
} catch (ValidationException) {
    uploadCheck(true, 'Invalid transport payloads must fail validation.');
}

// Automatic recovery can be disabled without disabling staging or explicit reconciliation.
$field = FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('manual-recovery')->mediaAutoRecovery(false);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->createWithContent('recovery.txt', 'recoverable'));
$temporary = $field->getRawState()[$key];
$form->getState();
$path = $field->getRawState()[$key];
$manifest = $repository->read('upload-probe-original', $path);
$lease = $leases->read($manifest->context['metadata']['flex_upload_lease']);
$stage = $stages->load($lease->id);
$stage['state'] = 'promoting';
unset($stage['receipt']);
$stages->save($lease->id, $stage);
$field->rawState([$key => $temporary]);
try {
    $form->getState();
    throw new RuntimeException('Automatic recovery ignored its opt-out.');
} catch (MediaFailure $error) {
    uploadCheck($error->reason === 'stage_recovery_required' && $realOriginalDisk->exists($path), 'Disabled auto recovery must retain ambiguous storage for explicit reconciliation.');
}
uploadCheck($field->recoverMediaUpload($lease->key, $field->makeUploadMediaWrite($temporary), $manifest) === $path, 'Explicit server recovery must remain available with auto recovery disabled.');
$field->deleteStoredPathWithVariants($path);

$record = UploadMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
$field = FlexSpatieMediaLibraryFileUpload::make('attachment')->disk('upload-probe-original')->collection('editorial')->fileMetadataEditor()->fileMetadataTranslations(['en', 'pl'])
    ->customProperties(['application_property' => 'preserve']);
[$livewire, $form, $field, $key] = uploadProbe($field, UploadedFile::fake()->image('spatie-editorial.png', 48, 32));
$field->writeFileEditorialMetadata($key, $editorial);
$form->getState();
$form->saveRelationships();
$uuid = array_values($field->getRawState())[0];
$media = $record->media()->where('uuid', $uuid)->firstOrFail();
uploadCheck($media->getCustomProperty('editorial.title.pl') === 'Zdjęcie' && $media->getCustomProperty('application_property') === 'preserve', 'Native Spatie acceptance must save translations and preserve application properties.');
$storedKey = array_search($uuid, $field->getRawState(), true);
$field->writeFileEditorialMetadata($storedKey, ['title' => ['pl' => 'Zmienione'], 'tags' => ['edited']]);
$field->completeMediaUploadsAfterSave($record);
$media->refresh();
uploadCheck($media->getCustomProperty('editorial.title.pl') === 'Zmienione' && $media->getCustomProperty('flex_capture.sha256') !== null, 'Existing owned Spatie metadata edits must preserve integrity provenance.');
$field->deleteStoredPathWithVariants($uuid);
UploadMediaProbeForm::$record = null;

// Early URL admission uses real field rules and must release both temporary files on rejection.
$app->instance(SafeRemoteUrlValidator::class, new class extends SafeRemoteUrlValidator
{
    public function approvedAddresses(string $url): array
    {
        return parse_url($url, PHP_URL_HOST) === 'upload.example.test' ? ['93.184.216.34'] : [];
    }
});
$image = imagecreatetruecolor(32, 24);
ob_start();
imagepng($image);
$bytes = ob_get_clean().str_repeat("\0", 2048);
imagedestroy($image);
Http::fake(['https://upload.example.test/*' => fn () => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);
foreach ([
    static fn ($field) => $field->minImageDimensions(64, 48),
    static fn ($field) => $field->maxImageDimensions(16, 16),
    static fn ($field) => $field->minSize(10),
    static fn ($field) => $field->rule('dimensions:min_width=64'),
    static fn ($field) => $field->maxSize(1),
] as $configure) {
    $field = $configure(FlexFileUpload::make('attachment')->imagesOnly()->allowUrlUpload());
    UploadMediaProbeForm::$fields = [$field];
    $livewire = Livewire::test(UploadMediaProbeForm::class);
    $field = $livewire->instance()->getSchema('form')->getComponent('attachment');
    $before = Storage::disk('upload-probe-temp')->allFiles();
    try {
        $field->importUploadedFileFromUrl('https://upload.example.test/image.png');
        throw new RuntimeException('Remote image bypassed native file rules.');
    } catch (ValidationException) {
        uploadCheck(true, 'URL imports must reject native dimension, minimum size and custom rules before admission.');
    }
    uploadCheck(Storage::disk('upload-probe-temp')->allFiles() === $before, 'Rejected imports must remove bytes and companion JSON.');
}
UploadMediaProbeForm::$fields = [FlexFileUpload::make('attachment')->imagesOnly()->allowUrlUpload()->multiple()->maxParallelUploads(3)->minImageDimensions(16, 16)->maxSize(5)];
$livewire = Livewire::test(UploadMediaProbeForm::class);
$field = $livewire->instance()->getSchema('form')->getComponent('attachment');
$payload = $field->importUploadedFileFromUrl('https://upload.example.test/image.png');
uploadCheck(($field->getRawState() ?? []) === [], 'Validated remote staging must not mutate native field state before commit.');
uploadCheck($field->getMaxParallelUploads() === 3 && str_contains($livewire->html(false), 'maxParallelUploads: 3'), 'Configured concurrency must reach the native FilePond transport.');
$field->minImageDimensions(64, 48);
try {
    $field->commitAlternateSourceStagingUpload($payload['stagingFilename']);
    throw new RuntimeException('Staging commit bypassed updated image limits.');
} catch (ValidationException) {
    uploadCheck(($field->getRawState() ?? []) === [], 'Alternate source commit must revalidate before changing form state.');
}
$field->discardAlternateSourceStagingUpload($payload['stagingFilename']);
uploadCheck(! Storage::disk('upload-probe-temp')->exists('livewire-tmp/'.$payload['stagingFilename'].'.json'), 'Discard must remove companion URL metadata.');
$editor = FlexFileUpload::make('attachment')->fileMetadataEditor()->fileMetadataStatePath('editorial')
    ->fileMetadataSchema([FlexTextInput::make('alt')->maxLength(250)])
    ->fileMetadataTranslations(['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch'], fields: ['alt']);
uploadCheck(! FlexFileUpload::make('plain')->hasFileMetadataEditor(), 'File information editor must remain opt-in.');
uploadCheck(count($editor->getFileMetadataTemplates()) === 1 && array_keys($editor->getFileMetadataLocales()) === ['pl', 'en', 'de'], 'Alt-only editing and exactly three chosen languages must be configurable.');
$localized = FlexFileUpload::make('attachment')->disk('upload-probe-original')->fileMetadataEditor()->fileMetadataStatePath('editorial')
    ->fileMetadataSchema([FlexTextInput::make('alt')->maxLength(250), FlexTextInput::make('credit'), TagsField::make('tags')->maxTags(20)])
    ->fileMetadataTranslations(['en' => 'English', 'pl' => 'Polski', 'de' => 'Deutsch'], fields: ['alt'])
    ->fileMetadataDefaultLocale('pl')->fileMetadataFallback()->fileMetadataRequiredInDefaultLocale(['alt'])
    ->fileMetadataValidationRules(['alt.en' => ['max:5'], 'credit' => ['required', 'max:10']]);
[$localizedLivewire, $localizedForm, $localized, $localizedKey] = uploadProbe($localized, UploadedFile::fake()->image('localized.png', 32, 24));
$localizedData = ['alt' => ['pl' => 'Polski opis', 'en' => '', 'de' => null], 'credit' => 'Owner', 'tags' => ['shared']];
uploadCheck($localized->validateFileEditorialMetadata($localizedData) === $localizedData, 'Only the configured main locale is required; other locales remain optional.');
$localized->writeFileEditorialMetadata($localizedKey, $localizedData);
uploadCheck($localized->getFileEditorialText($localizedKey, 'alt', 'de') === 'Polski opis', 'Authorized display fallback must resolve the configured primary language.');
uploadCheck($localized->readFileEditorialMetadata($localizedKey) === $localizedData, 'Fallback must not copy default text into missing locale values.');
$localized->fileMetadataFallback(false);
uploadCheck($localized->getFileEditorialText($localizedKey, 'alt', 'en') === null, 'Display fallback must be independently disableable.');
foreach ([['alt' => ['en' => 'Valid'], 'credit' => 'Owner'], ['alt' => ['pl' => 'Opis', 'en' => 'Too long'], 'credit' => 'Owner'], ['alt' => ['pl' => 'Opis']], ['alt' => ['pl' => 'Opis'], 'credit' => 'Owner', 'tags' => array_map(fn (int $index): string => 'tag-'.$index, range(1, 21))]] as $invalid) {
    try {
        $localized->validateFileEditorialMetadata($invalid);
        throw new RuntimeException('Localized metadata validation was bypassed.');
    } catch (ValidationException) {
        uploadCheck(true, 'Default-locale required, custom language/shared rules and maxTags must hold on direct writes.');
    }
}
$localizedLivewire->set('data.editorial', [$localizedKey => $localizedData]);
$localizedLivewire->call('mountAction', 'editFileMetadata', ['fileKey' => $localizedKey], ['schemaComponent' => $localized->getKey()]);
$actionSchema = $localizedLivewire->instance()->getSchema('mountedActionSchema0');
$localeTabs = $actionSchema->getComponents()[0];
uploadCheck($localeTabs instanceof TranslatableTabs && $localeTabs->getActiveTab() === 2, 'The editor must activate the configured main locale even when it is not first.');
uploadCheck(count($actionSchema->getComponents()) === 3 && $actionSchema->getComponents()[2] instanceof TagsField, 'Shared tags must render once outside translation tabs.');
$localizedLivewire->set('mountedActions.0.data.alt.pl', '')->call('callMountedAction')->assertHasErrors(['mountedActions.0.data.alt.pl' => 'required']);
uploadCheck(($localizedLivewire->instance()->data['editorial'][$localizedKey]['alt']['pl'] ?? null) === 'Polski opis', 'Invalid main-locale action must preserve existing metadata.');
$localizedLivewire->set('mountedActions.0.data.alt.pl', 'Opis')->set('mountedActions.0.data.alt.en', 'abcdef')->call('callMountedAction')->assertHasErrors(['mountedActions.0.data.alt.en' => 'max']);
uploadCheck(true, 'Custom locale rules must show errors on the matching mounted action input.');
$localizedLivewire->set('mountedActions.0.data.alt.en', '')->call('callMountedAction')->assertHasNoErrors();
uploadCheck(($localizedLivewire->instance()->data['editorial'][$localizedKey]['alt']['pl'] ?? null) === 'Opis', 'Valid default-only action must save with other locales empty.');
try {
    $editor->fileMetadataTranslations(['en', 'pl'], fields: ['tags']);
    throw new RuntimeException('Translated metadata tags were accepted.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Tags may never be duplicated into locale tabs.');
}
try {
    $editor->fileMetadataDefaultLocale('fr')->getFileMetadataDefaultLocale();
    throw new RuntimeException('Unknown default language was accepted.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Main locale must belong to the configured languages.');
}
$editor->fileMetadataDefaultLocale(null);
$editor->fileMetadataSchema([TextInput::make('alt')]);
try {
    $editor->getFileMetadataTemplates();
    throw new RuntimeException('Native Filament renderer entered the FLEX metadata editor.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Editorial forms must reject non-FLEX field renderers.');
}

// Optional native conversion queue: original publication and record commit precede worker encoding.
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionJournal;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\GenerateImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Date;

config()->set('filament-flex-fields.media_capture.images.queue.journal_disk', 'upload-probe-manifests');
config()->set('queue.connections.upload-conversions-probe', ['driver' => 'database', 'connection' => 'upload-media-probe', 'table' => 'upload_probe_jobs', 'queue' => 'probe-native', 'retry_after' => 120, 'after_commit' => true]);
Illuminate\Support\Facades\Schema::create('upload_probe_jobs', function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
final class UploadDiskQueuedProbeRecord extends Model
{
    protected $table = 'upload_media_probe_records';

    protected $guarded = [];
}
$queueManager = Queue::getFacadeRoot();
Queue::fake([GenerateImageConversions::class]);
$record = UploadDiskQueuedProbeRecord::create();
UploadMediaProbeForm::$record = $record;
[$livewire, $form, $field, $key] = uploadProbe(FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('queued-disk')->visibility('public')->imageEditor()
    ->queueImageConversions()->imageConversionQueue(new ConversionQueueOptions('upload-conversions-probe', 'probe-native'))
    ->imageConversions([new ImageConversion('worker-thumb', 16, 16, disk: 'upload-probe-conversions')])->previewConversion('worker-thumb'), UploadedFile::fake()->image('disk-queued.png', 32, 24));
$before = count(Storage::disk('upload-probe-conversions')->allFiles());
$form->getState();
$queuedPath = array_values($field->getRawState())[0];
$manifest = app(UploadManifestRepository::class)->read('upload-probe-original', $queuedPath);
$batchId = $manifest->context['queued_images'];
$journal = app(ConversionJournal::class);
$queueImages = app(QueuedImageConversions::class);
uploadCheck($manifest->variants === [] && $manifest->primary === 'original' && $journal->read($batchId)['status'] === 'awaiting_save', 'Native async publication must preserve original path and prepare a durable separate journal.');
uploadCheck(Queue::pushed(GenerateImageConversions::class)->isEmpty() && $before === count(Storage::disk('upload-probe-conversions')->allFiles()), 'Native queued conversion must not encode or dispatch before record acceptance.');
DB::beginTransaction();
$record->attachment = $queuedPath;
$record->save();
$field->completeMediaUploadsAfterSave($record);
uploadCheck(Queue::pushed(GenerateImageConversions::class)->isEmpty(), 'Native conversion job must wait for the enclosing transaction.');
DB::rollBack();
uploadCheck(Queue::pushed(GenerateImageConversions::class)->isEmpty() && $journal->read($batchId)['status'] === 'awaiting_save', 'Rollback must not enqueue or strand a native conversion journal.');
$record->refresh();
$record->attachment = $queuedPath;
$record->save();
$field->completeMediaUploadsAfterSave($record);
$jobs = Queue::pushed(GenerateImageConversions::class);
uploadCheck($jobs->count() === 1 && $journal->read($batchId)['status'] === 'queued', 'Verified saved reference must dispatch exactly one native conversion job.');
$field->completeMediaUploadsAfterSave($record);
uploadCheck(Queue::pushed(GenerateImageConversions::class)->count() === 1, 'Repeated saved-record hooks must not duplicate dispatched native jobs.');
$payload = app(DiskUploadBridge::class)->payload($field, $queuedPath, null);
uploadCheck($payload['processingStatus'] === 'queued' && str_contains($payload['url'], $queuedPath), 'Pending disk conversions must display the original, never a missing thumbnail.');
uploadCheck(str_contains($field->getImageEditorSource(array_key_first($field->getRawState()))['url'], $queuedPath), 'Native editor must resolve an original while its conversions are queued.');
$pendingIndex = $field->getMediaProcessingFiles();
uploadCheck(array_values($pendingIndex)[0]['processingStatus'] === 'queued', 'Renderless scoped refresh must return the owned pending attachment.');
$queuedMarkup = $form->toHtml();
uploadCheck(str_contains($queuedMarkup, 'disk-queued.png') && str_contains($queuedMarkup, 'Processing previews'), 'Actual FLEX list renderer must retain the file and distinguish queued preview processing from upload progress.');
// Use the real database broker and Laravel worker, including serialization, instead of calling handle directly.
Queue::swap($queueManager);
Queue::connection('upload-conversions-probe')->push($jobs->first());
uploadCheck(DB::table('upload_probe_jobs')->count() === 1, 'Native job must serialize into the durable broker.');
app('queue.worker')->runNextJob('upload-conversions-probe', 'probe-native', new WorkerOptions(sleep: 0, timeout: 60, maxTries: 3, force: true));
uploadCheck(DB::table('upload_probe_jobs')->count() === 0 && $journal->read($batchId)['status'] === 'ready', 'Real Laravel worker must acknowledge the native conversion job.');
$converted = $journal->read($batchId)['variants']['worker-thumb'];
uploadCheck($converted['disk'] === 'upload-probe-conversions' && Storage::disk($converted['disk'])->exists($converted['path']), 'Native worker must write the bounded conversion on its explicitly configured disk.');
$payload = app(DiskUploadBridge::class)->payload($field, $queuedPath, null);
uploadCheck($payload['processingStatus'] === 'ready' && str_contains($payload['url'], 'worker-thumb') && $record->fresh()->attachment === $queuedPath, 'Ready preview must change URL without changing the saved source identity.');
uploadCheck(! str_contains($field->getImageEditorSource(array_key_first($field->getRawState()))['url'], 'worker-thumb'), 'Native editor must keep using original after the worker publishes a preview.');
$count = count(Storage::disk('upload-probe-conversions')->allFiles());
$queueImages->process($batchId);
uploadCheck($count === count(Storage::disk('upload-probe-conversions')->allFiles()), 'Duplicate native worker delivery must not create duplicate conversions.');
$field->deleteStoredPathWithVariants($queuedPath);
uploadCheck($journal->read($batchId)['status'] === 'cancelled' && ! Storage::disk($converted['disk'])->exists($converted['path']) && ! Storage::disk('upload-probe-original')->exists($queuedPath), 'Deleting native async media must cancel its journal and remove original plus derived members.');
$queueImages->process($batchId);
uploadCheck(! Storage::disk($converted['disk'])->exists($converted['path']), 'Delayed delivery after removal must not recreate conversions.');
// Fail before encoding; retaining original and durable retry state is required.
[$livewire, $form, $field] = uploadProbe(FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('queued-failure')->queueImageConversions()
    ->imageConversionQueue(new ConversionQueueOptions('upload-conversions-probe', 'probe-native'))
    ->imageConversions([new ImageConversion('worker-thumb', 16, 16)]), UploadedFile::fake()->image('failure.png', 32, 24));
Queue::fake([GenerateImageConversions::class]);
$form->getState();
$failurePath = array_values($field->getRawState())[0];
$failureManifest = app(UploadManifestRepository::class)->read('upload-probe-original', $failurePath);
$failureId = $failureManifest->context['queued_images'];
$record->attachment = $failurePath;
$record->save();
$field->completeMediaUploadsAfterSave($record);
$bytes = Storage::disk('upload-probe-original')->get($failurePath);
Storage::disk('upload-probe-original')->put($failurePath, 'replaced source');
try {
    $queueImages->process($failureId);
    throw new RuntimeException('Changed original was converted.');
} catch (MediaFailure $error) {
    uploadCheck($journal->read($failureId)['status'] === 'retrying' && Storage::disk('upload-probe-original')->exists($failurePath), 'Integrity failure must preserve original and mark retry state outside the UI upload state.');
}
$queueImages->failed($failureId, new RuntimeException('exhausted'));
uploadCheck(app(DiskUploadBridge::class)->payload($field, $failurePath, null)['processingStatus'] === 'failed', 'Exhausted conversion retries must expose a processing failure while retaining attachment.');
Storage::disk('upload-probe-original')->put($failurePath, $bytes);
$queueImages->retry($failureId, $failureManifest->original, $field->makeUploadMediaWrite());
uploadCheck($journal->read($failureId)['status'] === 'queued', 'Authorized administrative retry must recover a failed or lost dispatch.');
$queueImages->process($failureId);
uploadCheck($journal->read($failureId)['status'] === 'ready' && $journal->read($failureId)['error'] === null, 'Successful retry must clear the processing error.');
$field->deleteStoredPathWithVariants($failurePath);
// Queue registration is optional even when native staging is disabled.
[$livewire, $form, $field] = uploadProbe(FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('queued-no-staging')->mediaStaging(false)->queueImageConversions()
    ->imageConversionQueue(new ConversionQueueOptions('upload-conversions-probe', 'probe-native'))
    ->imageConversions([new ImageConversion('worker-thumb', 16, 16)]), UploadedFile::fake()->image('no-staging.png', 32, 24));
$form->getState();
$pendingPath = array_values($field->getRawState())[0];
$pendingManifest = app(UploadManifestRepository::class)->read('upload-probe-original', $pendingPath);
$pendingId = $pendingManifest->context['queued_images'];
$record->attachment = $pendingPath;
$record->save();
uploadCheck($journal->read($pendingId)['status'] === 'queued', 'Disabling staging must not disable saved-record conversion participation.');
$field->deleteStoredPathWithVariants($pendingPath);
$queueImages->process($pendingId);
uploadCheck($journal->read($pendingId)['status'] === 'cancelled' && $journal->read($pendingId)['variants'] === [], 'Removing before encoding must not create any derivative.');
// Rollback of permanent publication compensates both source and queued journal.
[$livewire, $form, $field] = uploadProbe(FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('queued-rollback')->queueImageConversions()
    ->imageConversionQueue(new ConversionQueueOptions('upload-conversions-probe', 'probe-native'))
    ->imageConversions([new ImageConversion('worker-thumb', 16, 16)]), UploadedFile::fake()->image('rollback.png', 32, 24));
DB::beginTransaction();
$form->getState();
$rollbackPath = array_values($field->getRawState())[0];
$rollbackManifest = app(UploadManifestRepository::class)->read('upload-probe-original', $rollbackPath);
$rollbackId = $rollbackManifest->context['queued_images'];
DB::rollBack();
uploadCheck(! Storage::disk('upload-probe-original')->exists($rollbackPath) && $journal->read($rollbackId)['status'] === 'cancelled', 'Publication rollback must compensate the source and cancel its worker plan.');
// Standalone Media OS shares the same queue contract, outside a Filament schema.
$source = UploadedFile::fake()->image('standalone.png', 40, 30);
$intent = new MediaWrite(disk: 'upload-probe-original', directory: 'queued-core', policy: new MediaPolicy(visibility: 'public'));
$batch = $queueImages->store(MediaSource::path($source->getRealPath(), 'standalone.png'), $intent, [new ImageConversion('web', 20, 20)], new ConversionQueueOptions('upload-conversions-probe', 'probe-native'));
uploadCheck($queueImages->snapshot($batch->id, $batch->original, $intent)['status'] === 'queued', 'Core API must expose the stable source plus authorized pending status.');
try {
    $queueImages->snapshot($batch->id, $failureManifest->original, $intent);
    throw new RuntimeException('Foreign journal accepted.');
} catch (MediaFailure $error) {
    uploadCheck($error->reason === 'denied', 'Snapshots must reject an original from another journal.');
}
$queueImages->process($batch->id);
uploadCheck($queueImages->snapshot($batch->id, $batch->original, $intent)['variants']['web']->file->width === 20, 'Standalone queued Media OS must run the same bounded image engine.');
$queueImages->cancel($batch->id, $intent);
FlexMedia::os()->delete($batch->original, $intent);
// In-memory clock advance verifies bounded retention without expiring active journals.
Date::setTestNow(now()->addDays(8));
$pruned = $journal->pruneCancelled(now()->subDays(7)->getTimestamp());
Date::setTestNow();
uploadCheck($pruned >= 5 && $journal->read($batch->id) === null, 'Expired cancellation journals must be pruned only after every object is gone.');
try {
    (new ConversionQueueOptions('sync'))->connectionName();
    throw new RuntimeException('Sync queue accepted.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Background mode must reject synchronous transports.');
}
config()->set('queue.connections.upload-too-short', ['driver' => 'database', 'retry_after' => 10]);
try {
    (new ConversionQueueOptions('upload-too-short', timeout: 60))->connectionName();
    throw new RuntimeException('Unsafe visibility timeout accepted.');
} catch (InvalidArgumentException) {
    uploadCheck(true, 'Worker timeout must be lower than retry_after.');
}
Queue::swap($queueManager);
UploadMediaProbeForm::$record = null;

// Prove native Spatie queued conversions do no encoding during permanent publication.
$app['events']->listen(JobExceptionOccurred::class, static function ($event): void {
    fwrite(STDERR, (string) $event->exception.PHP_EOL);
});
final class UploadQueuedMediaProbeRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'upload_media_probe_records';

    protected $guarded = [];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('worker-thumb')->width(16)->height(16)->format('webp')->queued();
    }
}
Queue::fake([PerformConversionsJob::class]);
config()->set('media-library.queue_connection_name', 'database');
config()->set('media-library.queue_name', 'probe-conversions');
$record = UploadQueuedMediaProbeRecord::create();
UploadMediaProbeForm::$record = $record;
[$livewire, $form, $field, $key] = uploadProbe(FlexSpatieMediaLibraryFileUpload::make('attachment')->collection('queued')->disk('upload-probe-original')->visibility('public')->previewConversion('worker-thumb')->imageEditor(), UploadedFile::fake()->image('queued.png', 32, 24));
$form->getState();
$field->completeMediaUploadsAfterSave($record);
$queuedUuid = array_values($field->getRawState())[0];
$media = app(SpatieUploadBridge::class)->find($field, $queuedUuid);
uploadCheck(! $media->hasGeneratedConversion('worker-thumb'), 'Queued conversion must not block the upload request with image encoding.');
$jobs = Queue::pushed(PerformConversionsJob::class);
uploadCheck($jobs->count() === 1 && $jobs->first()->queue === 'probe-conversions' && $jobs->first()->connection === 'database', 'Native conversions must use the configured queue and connection.');
$payload = app(SpatieUploadBridge::class)->payload($field, $queuedUuid);
uploadCheck($payload['processingStatus'] === 'queued' && ! str_contains($payload['url'], 'worker-thumb'), 'Spatie pending preview must fall back to the existing original.');
uploadCheck(! str_contains($field->getImageEditorSource(array_key_first($field->getRawState()))['url'], 'worker-thumb'), 'Spatie editor must resolve the original while conversions are queued.');
Queue::swap($queueManager);
config()->set('queue.connections.database', config('queue.connections.upload-conversions-probe'));
Queue::connection('database')->push($jobs->first(), '', 'probe-conversions');
app('queue.worker')->runNextJob('database', 'probe-conversions', new WorkerOptions(sleep: 0, timeout: 60, maxTries: 3, force: true));
uploadCheck(DB::table('upload_probe_jobs')->count() === 0, 'Real Laravel worker must acknowledge the native Spatie conversion job.');
$media->refresh();
uploadCheck($media->hasGeneratedConversion('worker-thumb') && Storage::disk($media->conversions_disk)->exists($media->getPathRelativeToRoot('worker-thumb')), 'Worker execution must generate the conversion after upload acceptance.');
$payload = app(SpatieUploadBridge::class)->payload($field, $queuedUuid);
uploadCheck($payload['processingStatus'] === null && str_contains($payload['url'], 'worker-thumb'), 'Spatie completed preview must switch from original to generated conversion.');
uploadCheck(! str_contains($field->getImageEditorSource(array_key_first($field->getRawState()))['url'], 'worker-thumb'), 'Spatie editor must keep using original after queued conversion generation.');
$field->deleteStoredPathWithVariants($queuedUuid);
UploadMediaProbeForm::$record = null;

// Optional real Spatie Tags checks run when the existing isolated integration harness is loaded.
if (class_exists(Tag::class)) {
    $app->register(TagsServiceProvider::class);
    $migration = dirname((new ReflectionClass(TagsServiceProvider::class))->getFileName(), 2).'/database/migrations/create_tag_tables.php.stub';
    (require $migration)->up();
    class UploadTagProbeRecord extends Model
    {
        use HasTags;

        protected $table = 'upload_media_probe_records';

        protected $guarded = [];
    }
    $configuredTags = (new TagsFieldConfigurator)->configureTagsField(FlexSpatieTagsField::make('configured'), [
        'spatie_tag_type' => 'skills', 'spatie_tag_locale' => 'pl', 'spatie_tag_fallback_locale' => 'en', 'spatie_tag_search_limit' => 5, 'max_tags' => 20,
    ]);
    uploadCheck($configuredTags->getTagLocale() === 'pl' && $configuredTags->getTagFallbackLocale() === 'en' && $configuredTags->getTagSearchLimit() === 5 && $configuredTags->getMaxTags() === 20, 'Data-driven schemas must expose the same optional Spatie locale and tag-limit configuration.');
    $record = UploadTagProbeRecord::create();
    $tag = Tag::findOrCreate('Photography', 'skills', 'en');
    $tag->setTranslation('name', 'pl', 'Fotografia')->save();
    Tag::findOrCreate('Fotografia other type', 'other', 'pl');
    $field = FlexSpatieTagsField::make('tags')->type('skills')->tagLocale('pl')->tagSearchLimit(1);
    UploadMediaProbeForm::$fields = [$field];
    UploadMediaProbeForm::$record = $record;
    $livewire = Livewire::test(UploadMediaProbeForm::class);
    $form = $livewire->instance()->getSchema('form');
    $field = $form->getComponent('tags');
    uploadCheck($field->searchTagSuggestions('Foto') === ['Fotografia'], 'Spatie suggestions must search and return the chosen locale, never raw JSON or another type.');
    $field->modifyTagSearchQueryUsing(fn ($query) => $query->where('id', -1));
    uploadCheck($field->searchTagSuggestions('Foto') === [], 'Custom suggestion scope must be respected.');
    $field->modifyTagSearchQueryUsing(null);
    $form->fill([]);
    $form->getComponent('tags')->state(['Fotografia', 'Nowy']);
    $form->saveRelationships();
    $record->refresh();
    uploadCheck($record->tagsWithType('skills')->count() === 2 && $record->tagsWithType('skills')->contains('id', $tag->id), 'Locale-aware tag save must reuse existing tags and create only missing chosen-language tags.');
    uploadCheck(Tag::findFromString('Nowy', 'skills', 'pl') !== null && app()->getLocale() === 'en', 'Tag locale configuration must not change application-wide locale.');
    $form->fill([]);
    uploadCheck($form->getComponent('tags')->getState() === ['Fotografia', 'Nowy'], 'Edit must hydrate translated tag names into the FLEX renderer.');
    uploadCheck(str_contains($livewire->html(false), 'fff-tags-field'), 'Optional Spatie integration must retain the FLEX Tags renderer.');
    $fallbackTag = Tag::findOrCreate('Shared fallback', 'skills', 'en');
    $record->syncTagsWithType([$fallbackTag], 'skills');
    $beforeTags = Tag::count();
    $field->tagLocale('de')->tagFallbackLocale('en');
    $form->fill([]);
    uploadCheck($field->getState() === ['Shared fallback'], 'Missing tag locale must display the configured fallback without repeating tags.');
    uploadCheck($field->searchTagSuggestions('Shared') === ['Shared fallback'], 'Tag suggestions must include fallback names only when the requested translation is missing.');
    $form->saveRelationships();
    $record->refresh();
    uploadCheck(Tag::count() === $beforeTags && $record->tagsWithType('skills')->sole()->id === $fallbackTag->id, 'Saving fallback tag labels must preserve IDs and never create per-language duplicates.');
    uploadCheck($fallbackTag->fresh()->getTranslation('name', 'de', false) === '', 'Saving a fallback tag must not manufacture a translation.');
    foreach (['ar', 'bg', 'cs', 'da', 'el', 'es', 'et', 'fi', 'fr', 'he', 'hu', 'it', 'ja', 'ko', 'lt', 'lv', 'nl', 'pt', 'ro', 'sv'] as $locale) {
        $field->tagLocale($locale);
        $form->fill([]);
        $form->saveRelationships();
    }
    uploadCheck(Tag::count() === $beforeTags && count($fallbackTag->fresh()->getTranslations('name')) === 1, 'Twenty locale saves must retain one shared tag without fabricating translated selections.');
    $field->tagLocale('de');
    $newTagRecord = UploadTagProbeRecord::create();
    $newTagRecord->syncTagsWithType([Tag::findOrCreate('Retained type', 'other', 'en')], 'other');
    $beforeTags = Tag::count();
    $form->model($newTagRecord)->fill([]);
    $field->state(['Shared fallback']);
    $form->saveRelationships();
    uploadCheck(Tag::count() === $beforeTags && $newTagRecord->fresh()->tagsWithType('skills')->sole()->id === $fallbackTag->id, 'A new record must resolve fallback names from the catalog without creating duplicates.');
    uploadCheck($newTagRecord->fresh()->tagsWithType('other')->count() === 1, 'Typed synchronization must preserve other tag collections.');
    $form->model($record)->fill([]);
    $field->state(['Shared fallback', 'Neu']);
    $form->saveRelationships();
    uploadCheck(Tag::findFromString('Neu', 'skills', 'de') !== null, 'Create and edit must still create new tags in the configured locale.');
    $field->maxTags(20)->state(array_map(fn (int $index): string => 'tag-'.$index, range(1, 21)));
    try {
        $form->getState();
        throw new RuntimeException('Spatie tag limit was bypassed.');
    } catch (ValidationException) {
        uploadCheck(true, 'Optional Spatie wrapper must retain authoritative FLEX maxTags validation.');
    }
    UploadMediaProbeForm::$record = null;
}

// Editor replacement resolves originals, retains owned bytes until acceptance,
// and transfers configured draft metadata without weakening final validation.
UploadMediaProbeForm::$record = null;
[$editorLivewire, $editorForm, $editorField, $editorKey] = uploadProbe(
    FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('image-editor')
        ->imageEditor()->deletable(false)->fileMetadataEditor()->fileMetadataStatePath('editorial')
        ->fileMetadataSchema([FlexTextInput::make('alt')->required()->maxLength(100)])
        ->imageConversions([new ImageConversion('thumb', 16, 16, disk: 'upload-probe-conversions')])->previewConversion('thumb'),
    UploadedFile::fake()->image('before-edit.png', 64, 48),
);
$temporary = $editorField->getRawState()[$editorKey];
$editorField->writeFileEditorialMetadata($editorKey, ['alt' => 'Original description']);
$snapshot = $editorField->captureImageEditorFile($editorKey);
uploadCheck($editorField->getRawState()[$editorKey] === $temporary && $temporary->exists(), 'Editor capture must preserve the original association and retry source even when native deletion is disabled.');
uploadCheck($snapshot['editorial'] === ['alt' => 'Original description'], 'Editor must capture draft editorial values before replacement.');
try {
    $editorField->inheritImageEditorMetadata('foreign', $snapshot['editorial']);
    throw new RuntimeException('Foreign editor metadata inheritance was accepted.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'file_missing', 'Editor metadata inheritance must authorize the current file key.');
}
$editorField->rawState([$editorKey => $temporary]);
$editorField->inheritImageEditorMetadata($editorKey, $snapshot['editorial']);
uploadCheck($editorField->readFileEditorialMetadata($editorKey) === $snapshot['editorial'], 'Editor replacement must retain configured metadata.');
$editorLivewire->call('persistAttachment', [$editorKey => $snapshot['editorial']]);
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editorPath = $editorField->getRawState()[$editorKey];
$editorManifest = app(UploadManifestRepository::class)->read('upload-probe-original', $editorPath);
$editorSource = $editorField->getImageEditorSource($editorKey);
$editorPreview = app(DiskUploadBridge::class)->payload((clone $editorField)->openable(), $editorPath, null);
uploadCheck($editorSource['url'] === $editorPreview['openableUrl'] && $editorSource['url'] !== $editorPreview['url'], 'Stored image editing must resolve original bytes instead of its 16px thumbnail.');
$snapshot = $editorField->captureImageEditorFile($editorKey);
$newEditorKey = (string) Str::uuid();
$editorField->rawState([$editorKey => $editorPath, $newEditorKey => $temporary]);
$editorField->completeImageEditorReplacement($newEditorKey, $editorKey, $snapshot['editorial']);
uploadCheck(array_keys($editorField->getRawState()) === [$newEditorKey] && $editorField->readFileEditorialMetadata($newEditorKey) === $snapshot['editorial'], 'Replacement must update draft association and metadata only after the new temporary image exists.');
$editorField->completeImageEditorReplacement($newEditorKey, $editorKey, $snapshot['editorial']);
uploadCheck(array_keys($editorField->getRawState()) === [$newEditorKey], 'An uncertain editor completion can be repeated without detaching the replacement.');
uploadCheck($editorField->reconcileImageEditorReplacement($newEditorKey, $editorKey) && array_keys($editorField->getRawState()) === [$newEditorKey], 'A lost completion response must recognize acceptance without deleting its image.');
$editorField->rawState([$editorKey => $editorPath, $newEditorKey => $temporary]);
uploadCheck(! $editorField->reconcileImageEditorReplacement($newEditorKey, $editorKey) && array_keys($editorField->getRawState()) === [$editorKey], 'A rejected completion must remove only the unaccepted temporary association even when deletion is disabled.');
uploadCheck(Storage::disk('upload-probe-original')->exists($editorPath) && $temporary->exists(), 'Reconciliation must retain stored originals and temporary retry bytes.');
$beforeKey = (string) Str::uuid();
$afterKey = (string) Str::uuid();
$editorField->rawState([$beforeKey => $temporary, $editorKey => $editorPath, $afterKey => $temporary, $newEditorKey => $temporary]);
$editorField->completeImageEditorReplacement($newEditorKey, $editorKey, $snapshot['editorial']);
uploadCheck(array_keys($editorField->getRawState()) === [$beforeKey, $newEditorKey, $afterKey], 'Editor replacement must preserve the original attachment order.');
uploadCheck(Storage::disk('upload-probe-original')->exists($editorPath) && Storage::disk('upload-probe-conversions')->exists($editorManifest->variants['thumb']->path), 'Opening/replacing a draft must never delete the persisted original or its conversions.');
$editorField->rawState([$editorKey => $editorPath]);
$editedKey = (string) Str::uuid();
$editorLivewire->upload('data.attachment.'.$editedKey, [UploadedFile::fake()->image('edited-export.png', 80, 40)]);
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editorLivewire->call('replaceAttachment', $editedKey, $editorKey, $snapshot['editorial'])->call('persistAttachment');
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editedPath = $editorField->getRawState()[$editedKey];
$editedManifest = app(UploadManifestRepository::class)->read('upload-probe-original', $editedPath);
uploadCheck($editedPath !== $editorPath && $editedManifest->original->file->width === 80 && $editedManifest->original->file->height === 40, 'Editor replacement persistence must store its new source dimensions.');
uploadCheck($editedManifest->variants['thumb']->file->width === 16 && $editedManifest->variants['thumb']->file->height === 8 && $editedManifest->variants['thumb']->path !== $editorManifest->variants['thumb']->path, 'All configured native conversions must be regenerated from edited bytes instead of reusing the old thumbnail.');
uploadCheck(Storage::disk('upload-probe-original')->exists($editorPath), 'Default retention must not accidentally delete a previous stored image.');

$editorField->rawState([$editorKey => $temporary]);
$editorField->imageEditor(false);
try {
    $editorField->captureImageEditorFile($editorKey);
    throw new RuntimeException('Disabled image editor endpoint was accepted.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'denied', 'Editor endpoints must honor PHP feature configuration.');
}
$editorField->imageEditor()->disabled();
try {
    $editorField->inheritImageEditorMetadata($editorKey, ['alt' => 'Injected']);
    throw new RuntimeException('Disabled field editor inheritance was accepted.');
} catch (MediaFailure $failure) {
    uploadCheck($failure->reason === 'denied', 'Editor endpoints must reject disabled field mutations.');
}
UploadMediaProbeForm::$record = UploadMediaProbeRecord::create();
[$editorLivewire, $editorForm, $editorField, $editorKey] = uploadProbe(
    FlexSpatieMediaLibraryFileUpload::make('attachment')->disk('upload-probe-original')->collection('editor-replace')
        ->imageEditor()->deletable(false)->conversionsDisk('upload-probe-conversions')->previewConversion('thumb'),
    UploadedFile::fake()->image('spatie-before-edit.png', 64, 48),
);
$spatieEditorTemporary = $editorField->getRawState()[$editorKey];
$editorLivewire->call('persistAttachment');
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editorKey = array_key_first($editorField->getRawState());
$editorUuid = $editorField->getRawState()[$editorKey];
$editorMedia = app(SpatieUploadBridge::class)->find($editorField, $editorUuid);
$editorSource = $editorField->getImageEditorSource($editorKey);
$editorPreview = app(SpatieUploadBridge::class)->payload((clone $editorField)->openable(), $editorUuid);
uploadCheck($editorSource['url'] === $editorPreview['openableUrl'] && $editorSource['url'] !== $editorPreview['url'], 'Spatie editor must also resolve original bytes instead of previewConversion.');
$editorField->captureImageEditorFile($editorKey);
$newEditorKey = (string) Str::uuid();
$editorField->rawState([$editorKey => $editorUuid, $newEditorKey => $spatieEditorTemporary]);
$editorField->completeImageEditorReplacement($newEditorKey, $editorKey);
uploadCheck(array_keys($editorField->getRawState()) === [$newEditorKey], 'Spatie editor replacement must swap only the accepted temporary draft association.');
uploadCheck($editorMedia->fresh() !== null && Storage::disk($editorMedia->disk)->exists($editorMedia->getPathRelativeToRoot()), 'Spatie image editor detachment must preserve the record and bytes until form acceptance.');
$editorField->rawState([$editorKey => $editorUuid]);
$editedKey = (string) Str::uuid();
$editorLivewire->upload('data.attachment.'.$editedKey, [UploadedFile::fake()->image('spatie-edited-export.png', 80, 40)]);
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editorLivewire->call('replaceAttachment', $editedKey, $editorKey)->call('persistAttachment');
$editorForm = $editorLivewire->instance()->getSchema('form');
$editorField = $editorForm->getComponent('attachment');
$editedUuid = array_values($editorField->getRawState())[0];
$editedMedia = app(SpatieUploadBridge::class)->find($editorField, $editedUuid);
uploadCheck($editedUuid !== $editorUuid && $editedMedia->hasGeneratedConversion('thumb') && Storage::disk($editedMedia->conversions_disk)->exists($editedMedia->getPathRelativeToRoot('thumb')), 'Spatie relationship save must create a new media identity and regenerate its native conversion after editing.');

UploadMediaProbeForm::$record = null;

// Edited source bytes must follow both real worker pipelines after a second save.
foreach (['disk', 'spatie'] as $adapter) {
    $jobClass = $adapter === 'disk' ? GenerateImageConversions::class : PerformConversionsJob::class;
    Queue::fake([$jobClass]);
    $record = $adapter === 'disk' ? UploadMediaProbeRecord::create() : UploadQueuedMediaProbeRecord::create();
    UploadMediaProbeForm::$record = $record;
    $configured = $adapter === 'disk'
        ? FlexFileUpload::make('attachment')->disk('upload-probe-original')->directory('edited-worker')
            ->queueImageConversions()->imageConversionQueue(new ConversionQueueOptions('upload-conversions-probe', 'probe-editor-disk'))
            ->imageConversions([new ImageConversion('worker-thumb', 16, 16)])->previewConversion('worker-thumb')
        : FlexSpatieMediaLibraryFileUpload::make('attachment')->disk('upload-probe-original')->collection('edited-worker')->previewConversion('worker-thumb');
    [$editorLivewire, $editorForm, $editorField] = uploadProbe($configured->imageEditor(), UploadedFile::fake()->image('worker-before.png', 64, 48));
    $editorLivewire->call('persistAttachment');
    $editorField = $editorLivewire->instance()->getSchema('form')->getComponent('attachment');
    $oldKey = array_key_first($editorField->getRawState());
    $newKey = (string) Str::uuid();
    $editorLivewire->upload('data.attachment.'.$newKey, [UploadedFile::fake()->image('worker-edited.png', 80, 40)])
        ->call('replaceAttachment', $newKey, $oldKey)->call('persistAttachment');
    $editorField = $editorLivewire->instance()->getSchema('form')->getComponent('attachment');
    $identity = array_values($editorField->getRawState())[0];
    if ($adapter === 'disk') {
        $record->attachment = $identity;
        $record->save();
        $editorField->completeMediaUploadsAfterSave($record);
    }
    $payload = $adapter === 'disk' ? app(DiskUploadBridge::class)->payload($editorField, $identity, null) : app(SpatieUploadBridge::class)->payload($editorField, $identity);
    $pendingPreview = $payload['url'];
    uploadCheck($payload['processingStatus'] === 'queued', ucfirst($adapter).' edited image must retain its original preview while its conversion is queued.');
    $job = Queue::pushed($jobClass)->last();
    uploadCheck($job !== null, ucfirst($adapter).' edited image must dispatch its own conversion plan.');
    Queue::swap($queueManager);
    $connection = $adapter === 'disk' ? 'upload-conversions-probe' : 'database';
    $queue = $adapter === 'disk' ? 'probe-editor-disk' : 'probe-conversions';
    Queue::connection($connection)->push($job, '', $queue);
    app('queue.worker')->runNextJob($connection, $queue, new WorkerOptions(sleep: 0, timeout: 60, maxTries: 3, force: true));
    $payload = $adapter === 'disk' ? app(DiskUploadBridge::class)->payload($editorField, $identity, null) : app(SpatieUploadBridge::class)->payload($editorField, $identity);
    uploadCheck($payload['processingStatus'] === ($adapter === 'disk' ? 'ready' : null) && $payload['url'] !== $pendingPreview, ucfirst($adapter).' edited preview must switch to its fresh derivative after actual worker execution.');
    if ($adapter === 'disk') {
        $variant = $queueImages->snapshot(app(UploadManifestRepository::class)->read('upload-probe-original', $identity)->context['queued_images'], app(UploadManifestRepository::class)->read('upload-probe-original', $identity)->original, $editorField->makeUploadMediaWrite())['variants']['worker-thumb'];
        $dimensions = getimagesizefromstring(Storage::disk($variant->disk)->get($variant->path));
    } else {
        $media = app(SpatieUploadBridge::class)->find($editorField, $identity);
        $dimensions = getimagesizefromstring(Storage::disk($media->conversions_disk)->get($media->getPathRelativeToRoot('worker-thumb')));
        $sourceDimensions = getimagesizefromstring(Storage::disk($media->disk)->get($media->getPathRelativeToRoot()));
        $jobMedia = (new ReflectionProperty($job, 'media'))->getValue($job);
        uploadCheck($sourceDimensions[0] === 80 && $sourceDimensions[1] === 40 && $jobMedia->uuid === $identity, 'Spatie worker must target the new edited source identity and bytes.');
    }
    uploadCheck($dimensions[0] === ($adapter === 'disk' ? 16 : 32) && $dimensions[1] === ($adapter === 'disk' ? 8 : 16), ucfirst($adapter).' worker must honor its configured native conversion sizing.');
}
UploadMediaProbeForm::$record = null;

Mockery::close();

echo 'Media Upload contract: '.$checks.' checks passed. Native forms, cross-disk conversions, encrypted receipts, notifications, aggregate policy, scanner, compensation. No live S3 certification.'.PHP_EOL;
