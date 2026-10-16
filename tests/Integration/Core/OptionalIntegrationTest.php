<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Integration\Core;

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieTagsField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\FieldComponentFactory;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingState;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaIngress;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaKind;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaStorageDriver;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\SpatieMediaRef;
use Bjanczak\FilamentFlexFields\Support\Slug\SpatieSlugIntegration;
use Bjanczak\FilamentFlexFields\Support\Translatable\SpatieTranslatableIntegration;
use Bjanczak\FilamentFlexFields\Tests\Support\SlugFieldSpatiePost;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Composer\InstalledVersions;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Tags\HasTags;
use Spatie\Tags\TagsServiceProvider;
use Spatie\Translatable\HasTranslations;
use Spatie\Translatable\TranslatableServiceProvider;

final class OptionalIntegrationTest extends TestCase
{
    public function test_installed_optional_packages_do_not_eagerly_construct_core_adapters(): void
    {
        foreach ([MediaIngress::class, MediaCaptureState::class, GeocodingState::class] as $service) {
            $this->assertFalse($this->app->resolved($service), $service.' was eagerly resolved.');
        }
    }

    protected function getPackageProviders($app): array
    {
        foreach (['filament/spatie-laravel-media-library-plugin', 'spatie/laravel-medialibrary',
            'spatie/laravel-translatable', 'spatie/laravel-sluggable', 'spatie/laravel-tags'] as $package) {
            if (! InstalledVersions::isInstalled($package)) {
                throw new RuntimeException("The optional core suite requires [{$package}]. See docs/core-runtime.md.");
            }
        }

        return [...parent::getPackageProviders($app), MediaLibraryServiceProvider::class,
            TranslatableServiceProvider::class, TagsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('core_records', static function (Blueprint $table): void {
            $table->id();
            $table->json('title')->nullable();
            $table->timestamps();
        });
        $migrationPath = dirname((new \ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2)
            .'/database/migrations/create_media_table.php.stub';
        (require $migrationPath)->up();
        $tagMigrationPath = dirname((new \ReflectionClass(TagsServiceProvider::class))->getFileName(), 2)
            .'/database/migrations/create_tag_tables.php.stub';
        (require $tagMigrationPath)->up();
        config()->set('filesystems.disks.core-media', ['driver' => 'local', 'root' => sys_get_temp_dir().'/fff-core-media']);
        Storage::fake('core-media');
        config()->set('media-library.disk_name', 'core-media');
        config()->set('media-library.queue_conversions_by_default', false);
    }

    public function test_media_ingress_round_trip_preserves_original_and_rejects_callback_failure(): void
    {
        $this->assertInstanceOf(SpatieMediaLibraryFileUpload::class, FlexSpatieMediaLibraryFileUpload::make('file'));
        $record = CoreMediaRecord::create();
        $context = new MediaContext(kind: MediaKind::Upload, driver: MediaStorageDriver::Spatie,
            record: $record, collection: 'documents', disk: 'core-media', field: 'document', filename: 'document.txt');
        $contents = 'original stream content';
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);
        $payload = MediaPayload::fromStream($stream, 'document.txt', 'text/plain');
        fclose($stream);
        try {
            $ref = FlexMedia::persist($payload, $context);
            $this->assertInstanceOf(SpatieMediaRef::class, $ref);
            $this->assertSame($contents, Storage::disk('core-media')->get($ref->relativePath()));
            $this->assertSame($contents, file_get_contents($payload->localPath));
            $this->assertSame('document', $ref->media()->getCustomProperty('flex_capture.field'));
            $this->assertNotEmpty($ref->identifier());
            $this->assertSame(1, $record->media()->count());
            try {
                FlexMedia::persist($payload, $context, static fn () => throw new RuntimeException('callback failed'));
                $this->fail('A failing callback must propagate its error.');
            } catch (MediaFailure $exception) {
                $this->assertSame('finalizer_failed', $exception->reason);
                $this->assertSame('callback failed', $exception->getPrevious()?->getMessage());
            }
            $this->assertSame(1, $record->media()->count());
            $this->assertSame($contents, Storage::disk('core-media')->get($ref->relativePath()));
        } finally {
            $payload->close();
        }
    }

    public function test_media_ingress_rejects_unsaved_models_and_real_post_persist_scan_failure(): void
    {
        $payload = MediaPayload::fromInlineString('original', 'document.txt', 'text/plain');
        $context = new MediaContext(kind: MediaKind::Upload, driver: MediaStorageDriver::Spatie,
            record: new CoreMediaRecord, disk: 'core-media');
        try {
            FlexMedia::persist($payload, $context);
            $this->fail('An unsaved Spatie record must be rejected.');
        } catch (MediaFailure $exception) {
            $this->assertSame('spatie_record_missing', $exception->reason);
        }
        $record = CoreMediaRecord::create();
        $context = new MediaContext(kind: MediaKind::Upload, driver: MediaStorageDriver::Spatie,
            record: $record, disk: 'core-media');
        config()->set('filament-flex-fields.media_capture.scan_before_persist', false);
        $scanned = 0;
        MediaCaptureOs::registerVirusScanCallback(static function (string $path) use (&$scanned): bool {
            $scanned++;
            self::assertFileExists($path);

            return false;
        });
        $this->assertNull(FlexMedia::persist($payload, $context));
        $this->assertSame(1, $scanned);
        $this->assertSame(0, $record->media()->count());
    }

    public function test_translation_boundary_reads_only_persisted_locales_without_mutation(): void
    {
        $record = new CoreTranslatedRecord;
        $record->setTranslations('title', ['en' => 'Original', 'pl' => 'Tłumaczenie']);
        $record->save();
        $record = $record->fresh();
        app()->setLocale('de');
        config()->set('app.fallback_locale', 'en');
        $attributes = $record->getAttributes();
        $this->assertTrue(SpatieTranslatableIntegration::modelUsesTranslatable($record, 'title'));
        $this->assertSame(['en' => 'Original', 'pl' => 'Tłumaczenie'], SpatieTranslatableIntegration::translations($record, 'title'));
        $this->assertSame($attributes, $record->getAttributes());
        $this->assertFalse($record->isDirty());
        $record->setTranslation('title', 'pl', 'Nowe');
        $record->save();
        $this->assertSame(['en' => 'Original', 'pl' => 'Nowe'], SpatieTranslatableIntegration::translations($record->fresh(), 'title'));
    }

    public function test_slug_boundary_previews_create_and_edit_without_writing_models(): void
    {
        Schema::create('slug_field_posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('slug');
            $table->timestamps();
        });
        $existing = SlugFieldSpatiePost::create(['title' => 'Collision']);
        $candidate = new SlugFieldSpatiePost(['title' => 'Draft']);
        $this->assertSame('collision-1', SpatieSlugIntegration::generate('Collision', $candidate, sourceField: 'title'));
        $this->assertFalse($candidate->exists);
        $this->assertSame('Draft', $candidate->title);
        $this->assertSame('collision', SpatieSlugIntegration::generate('Collision', $existing, sourceField: 'title'));
        $this->assertSame('edited', SpatieSlugIntegration::generate('Edited', $existing, sourceField: 'title'));
        $this->assertSame('Collision', $existing->fresh()->title);
        $this->assertSame('collision', $existing->fresh()->slug);
        $this->assertSame(1, SlugFieldSpatiePost::count());
    }

    public function test_schema_factory_opt_in_resolves_real_tag_adapter_and_leaves_default_independent(): void
    {
        $factory = app(FieldComponentFactory::class);
        $plain = $factory->makeComponent(new FlexFieldDefinition('plain', 'Plain', FieldType::Tags));
        $adapter = $factory->makeComponent(new FlexFieldDefinition('topics', 'Topics', FieldType::Tags,
            config: ['use_spatie_tags' => true, 'spatie_tag_type' => 'skills']));
        $this->assertInstanceOf(TagsField::class, $plain);
        $this->assertNotInstanceOf(FlexSpatieTagsField::class, $plain);
        $this->assertInstanceOf(FlexSpatieTagsField::class, $adapter);
        $this->assertSame('skills', $adapter->getSpatieTagType());
        $record = CoreMediaRecord::create();
        $record->syncTagsWithType(['PHP', 'Laravel'], $adapter->getSpatieTagType());
        $this->assertSame(['Laravel', 'PHP'], $record->tagsWithType('skills')->pluck('name')->sort()->values()->all());
        $this->assertSame('Spatie\\Tags\\Tag', $adapter->model($record)->getTagClassName());
    }
}

final class CoreMediaRecord extends Model implements HasMedia
{
    use HasTags;
    use InteractsWithMedia;

    protected $table = 'core_records';

    protected $guarded = [];
}

final class CoreTranslatedRecord extends Model
{
    use HasTranslations;

    public array $translatable = ['title'];

    protected $table = 'core_records';

    protected $guarded = [];
}
