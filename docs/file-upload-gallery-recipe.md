---
title: "A complete Post gallery"
description: "Build a relational gallery from migrations to create/edit forms, custom filenames, image conversions and an optional SQL conversion index."
icon: "database"
"og:image": "https://flex-fields.bjanczak.com/art/previews/post-gallery-save-reopen.webp"
"og:video": "https://flex-fields.bjanczak.com/art/previews/post-gallery-save-reopen.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
---

This recipe stores **one row per original file in `galleries`**, related to a `Post`. No upload array or JSON column is added to `posts`. The files live on a Laravel disk; SQL stores their disk-relative paths, original names, collection and order.

All files below are **example application code**. The complete maintained files also ship in `resources/recipes/post-gallery` inside the installed package. Their code blocks are generated from those same files, and the gallery mapping is exercised by the package tests. They are not migrations or models installed by FLEX. Use the examples in an existing authenticated Filament 5.6+ panel with FLEX registered. Adapt names instead of recreating tables your application already has.

## Watch the save and reopen flow

<video autoPlay muted loop playsInline controls preload="metadata"
  className="w-full aspect-video rounded-xl"
  src="/art/previews/post-gallery-save-reopen.mp4"
  poster="/art/previews/post-gallery-save-reopen.webp"
  width="1920" height="1080" aria-label="Post galleries: upload, save, reload and edit">
  <a href="/art/previews/post-gallery-save-reopen.mp4">Watch the gallery workflow.</a>
</video>

<Accordion title="Recording steps">
This silent recording shows two independent galleries on a Post: upload main and
interior images, save the record, reload it, change its title and reload again.
The first save is around 14 seconds, the fresh reload around 18 seconds, and the
second edited reload around 26 seconds. The source files and related SQL rows
persist across those reloads.
</Accordion>

This recording runs the recipe's actual page against synthetic SQLite records
and a local public disk. Three original files become three related rows. It shows
upload, save, a fresh reload, editing and another reload. It does not demonstrate
S3, authentication setup, queued processing or every failure path.

<script type="application/ld+json">
{JSON.stringify({
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe#article",
      "headline": "A complete Post gallery",
      "url": "https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe",
      "description": "Configure a relational Post gallery with explicit SQL persistence, filenames and preview conversions.",
      "inLanguage": "en",
      "video": { "@id": "https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe#video" }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe#video",
      "name": "Post galleries: upload, save and reopen",
      "description": "An actual Filament page uploads images to two related galleries, saves a Post, reloads the record and edits it again using synthetic data and a local disk.",
      "thumbnailUrl": ["https://flex-fields.bjanczak.com/art/previews/post-gallery-save-reopen.webp"],
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/post-gallery-save-reopen.mp4",
      "uploadDate": "2026-10-17T00:00:00+02:00",
      "duration": "PT28.8S",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": { "@id": "https://flex-fields.bjanczak.com/docs/file-upload-gallery-recipe#article" }
    }
  ]
}).replace(/</g, '\\u003c')}
</script>

## Which parts do I need?

| What you want | Follow |
| --- | --- |
| Save images to your own `galleries` table and edit them later | Steps 1–6. This is the complete basic recipe. |
| Keep native Resource create/edit pages | Steps 1–5 here, then [the Resource integration](/docs/file-upload-resource-recipe) instead of the custom page. |
| Generate thumbnails in a background worker | Add step 7. |
| Show saved files in your website or API | Read step 8 and its separate examples. |
| Keep an existing Spatie Media Library setup | Start with step 9 and the Spatie guide. Skip the custom-table migrations. |

The basic flow is **upload → save the post and gallery rows → reopen the saved record**. A finished upload alone does not save a database relationship.

The main example uses public JPEG, PNG and WebP images, synchronous named conversions and two independent galleries. It enables the image editor only for main photos and uses `appendFiles()` so ascending SQL order matches the visible order. It keeps removed physical files for a separate retention process. You can stop after step 6; the later sections are alternatives and optional additions.

## 1. Create the tables

The example assumes the application already has Laravel's `users` table. `user_id` supplies an explicit owner for the authorization examples.

{/* recipe-source: resources/recipes/post-gallery/database/migrations/2026_10_17_000001_create_posts_and_galleries.php */}
```php database/migrations/2026_10_17_000001_create_posts_and_galleries.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('galleries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('collection', 32);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['post_id', 'collection', 'disk', 'path']);
            $table->index(['post_id', 'collection', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('posts');
    }
};
```
{/* /recipe-source */}

```bash
php artisan migrate
php artisan storage:link
```

Use private storage and authorized delivery instead when attachments must not be publicly accessible. The `public` disk here is a deliberate example choice, not an access-control mechanism.

## 2. Define both models

{/* recipe-source: resources/recipes/post-gallery/app/Models/Post.php */}
```php app/Models/Post.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = ['title'];

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }
}
```
{/* /recipe-source */}

{/* recipe-source: resources/recipes/post-gallery/app/Models/Gallery.php */}
```php app/Models/Gallery.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gallery extends Model
{
    protected $fillable = [
        'collection', 'disk', 'path', 'original_name', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
```
{/* /recipe-source */}

There is no `photos` cast: neither model stores the field's temporary path list as JSON. The relationship supplies `post_id` when a gallery row is created.

## 3. Authorize the post

{/* recipe-source: resources/recipes/post-gallery/app/Policies/PostPolicy.php */}
```php app/Policies/PostPolicy.php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function create(User $user): bool
    {
        return $user->exists;
    }

    public function update(User $user, Post $post): bool
    {
        return (string) $post->user_id === (string) $user->getKey();
    }
}
```
{/* /recipe-source */}

Laravel discovers this conventional policy. If your application disables discovery, register `Gate::policy(Post::class, PostPolicy::class)` in its provider. Add your own tenant and business authorization to this policy and the relation queries; do not trust a browser-supplied owner or tenant ID.

## 4. Generate the actual stored filename

This generator preserves a readable, sanitized stem and adds a UUID. The extension comes from the inspected MIME type, not the original extension. For example, `Summer Deck.JPG` becomes `photo-summer-deck-<uuid>.jpg` on the disk. The separate `original_name` column retains the upload's display name.

{/* recipe-source: resources/recipes/post-gallery/app/Support/GalleryFileName.php */}
```php app/Support/GalleryFileName.php
<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class GalleryFileName
{
    public function __invoke(TemporaryUploadedFile $file, string $attribute): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                $attribute => 'Upload a JPEG, PNG or WebP image.',
            ]),
        };

        $stem = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $stem = substr($stem === '' ? 'image' : $stem, 0, 60);

        return 'photo-'.$stem.'-'.Str::uuid().'.'.$extension;
    }
}
```
{/* /recipe-source */}

Use trusted application data for a different naming convention, such as `invoice-<uuid>.pdf`. Return a **basename only**; configure folders with `directory()`. Do not return an absolute path, unsanitized user text or a repeatedly reused fixed name. Media OS refuses to overwrite an existing destination. This callback names the stored source; generated conversions have their own names and must be identified by conversion name, not by guessing a filename suffix.

## 5. Map the relationship explicitly

This small **application-owned mapper** has one job: restore and synchronize two
known collections. It is not a new `relationship()` API or an automatic package
migration. The page calls it with validated, stored field state inside the post's
transaction. The page still authorizes the actor; the upload field still proves
existing paths. Other writers must use the same parent-lock convention.

It rejects duplicate paths, traversal, another collection's files and a path
already referenced by another post. Removed rows are detached; bytes are retained
for an application-owned retention process. A unique path is not a substitute for
authorization, and a SQL transaction does not roll back disk/S3 writes.

{/* recipe-source: resources/recipes/post-gallery/app/Support/GalleryRows.php */}
```php app/Support/GalleryRows.php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Gallery;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Example application mapper, not an automatically installed FLEX service. */
final class GalleryRows
{
    private const COLLECTIONS = ['main', 'interior'];

    /** @return array{paths: list<string>, names: array<string, string>} */
    public function hydrate(Post $post, string $collection): array
    {
        $this->assertCollection($collection);
        $rows = $post->galleries()->where('collection', $collection)
            ->where('disk', 'public')->orderBy('sort_order')->orderBy('id')->get();

        return ['paths' => $rows->pluck('path')->all(), 'names' => $rows->pluck('original_name', 'path')->all()];
    }

    /**
     * Call with validated, stored field state inside the parent's save transaction.
     * Authorization belongs to the calling page/Resource, not to submitted IDs.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $names
     */
    public function sync(Post $post, string $collection, array $paths, array $names): void
    {
        $this->assertCollection($collection);
        if (! $post->exists || $post->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Save gallery rows inside the persisted post transaction.');
        }
        // Serialize changes for this parent. All application writers must use this convention.
        $locked = $post->newQuery()->lockForUpdate()->findOrFail($post->getKey());
        $prefix = 'posts/'.$locked->user_id.'/'.$collection.'/';
        if (! array_is_list($paths) || count($paths) > 12) {
            throw ValidationException::withMessages(['gallery_'.$collection => 'Submit at most twelve distinct stored files.']);
        }
        foreach ($paths as $path) {
            if (! is_string($path) || strlen($path) > 512 || ! str_starts_with($path, $prefix)
                || preg_match('~[\\\\\x00-\x1f\x7f]|(?:^|/)\.{1,2}(?:/|$)~', $path)
                || ! Storage::disk('public')->exists($path)
                || Gallery::query()->where('disk', 'public')->where('path', $path)
                    ->where(fn ($query) => $query->where('post_id', '!=', $post->getKey())->orWhere('collection', '!=', $collection))->exists()) {
                throw ValidationException::withMessages(['gallery_'.$collection => 'A file is not available to this gallery.']);
            }
        }
        if (count(array_unique($paths)) !== count($paths)) {
            throw ValidationException::withMessages(['gallery_'.$collection => 'Submit distinct stored files.']);
        }
        foreach ($paths as $position => $path) {
            $name = $names[$path] ?? basename($path);
            $post->galleries()->updateOrCreate(
                ['collection' => $collection, 'disk' => 'public', 'path' => $path],
                ['original_name' => Str::limit(is_string($name) ? $name : basename($path), 255, ''), 'sort_order' => $position],
            );
        }
        $removed = $post->galleries()->where('collection', $collection)->where('disk', 'public');
        if ($paths !== []) {
            $removed->whereNotIn('path', $paths);
        }
        // Deliberately detach rows only. A separate reference-aware retention job owns bytes.
        $removed->delete();
    }

    private function assertCollection(string $collection): void
    {
        if (! in_array($collection, self::COLLECTIONS, true)) {
            throw new LogicException('Unknown application gallery collection.');
        }
    }
}
```
{/* /recipe-source */}

### Reuse the field configuration

Both the page below and a Resource can reuse this application helper. The main
collection enables the image editor; the interior collection does not. Explicit
`appendFiles()` makes the displayed sequence match ascending SQL `sort_order`.
The source path stays unchanged when choosing a thumbnail or viewer conversion.

{/* recipe-source: resources/recipes/post-gallery/app/Support/GalleryFields.php */}
```php app/Support/GalleryFields.php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Post;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageFit;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Shared application configuration for a page or an existing Resource. */
final class GalleryFields
{
    public static function make(string $state, string $collection): FlexImageUpload
    {
        return FlexImageUpload::make($state)->key($state)
            ->multiple()->maxFiles(12)->maxSize(5120)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->uploadLayout('grid')->reorderable()->appendFiles()
            ->imageEditor($collection === 'main')
            ->disk('public')->visibility('public')
            ->directory('posts/'.Auth::id().'/'.$collection)
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => app(GalleryFileName::class)($file, 'data.'.$state),
            )
            ->storeFileNamesIn($state.'__names')
            ->imageConversions([
                new ImageConversion('thumb', 320, 320, fit: ImageFit::Cover),
                new ImageConversion('large', width: 1600, quality: 85),
            ])
            ->previewConversion('thumb')
            ->fileViewer()->fileViewerConversion('large')
            ->preventFilePathTampering(allowFilePathUsing: fn (?Post $record, string $file): bool => $record?->exists
                    && $record->galleries()->where('collection', $collection)
                        ->where('disk', 'public')->where('path', $file)->exists())
            ->mediaReferenceUsing(fn (Post $record, string $identifier): bool => $record->galleries()->where('collection', $collection)
                ->where('disk', 'public')->where('path', $identifier)->exists())
            ->deleteFileOnRemove(false)->deleteReplacedFiles(false)
            ->pruneOrphanedOnSave(false);
    }
}
```
{/* /recipe-source */}

### Build the create/edit page

This is a Filament panel page. Its optional `post` route parameter opens an existing record; omitting it creates a post. The ID is locked, and authorization runs again on every save and schema access. `gallery_main` and `gallery_interior` are **virtual form fields**, not `posts` columns.

{/* recipe-source: resources/recipes/post-gallery/app/Filament/Pages/PostGallery.php */}
```php app/Filament/Pages/PostGallery.php
<?php

namespace App\Filament\Pages;

use App\Models\Post;
use App\Support\GalleryFields;
use App\Support\GalleryRows;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class PostGallery extends Page
{
    protected static ?string $slug = 'post-gallery/{post?}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.post-gallery';

    #[Locked]
    public ?int $postId = null;

    public array $data = [];

    private const GALLERIES = [
        'gallery_main' => 'main',
        'gallery_interior' => 'interior',
    ];

    public function mount(?int $post = null): void
    {
        $this->postId = $post;
        $record = $this->authorizedPost();
        $data = ['title' => $record?->title ?? ''];
        foreach (self::GALLERIES as $state => $collection) {
            $items = $record === null ? ['paths' => [], 'names' => []]
                : app(GalleryRows::class)->hydrate($record, $collection);
            $data[$state] = $items['paths'];
            $data[$state.'__names'] = $items['names'];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->authorizedPost();

        return $schema
            ->model($record ?? Post::class)
            ->operation($record === null ? 'create' : 'edit')
            ->statePath('data')
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                $this->galleryField('gallery_main', 'main')->label('Main photos'),
                $this->galleryField('gallery_interior', 'interior')->label('Interior photos'),
            ]);
    }

    private function galleryField(string $state, string $collection): FlexImageUpload
    {
        return GalleryFields::make($state, $collection);
    }

    public function save(): void
    {
        $record = $this->authorizedPost();
        $data = $this->form->getState();

        $post = DB::transaction(function () use ($record, $data): Post {
            $post = $record === null
                ? new Post
                : Post::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($post->exists) {
                Gate::authorize('update', $post);
            } else {
                Gate::authorize('create', Post::class);
                $post->user_id = Auth::id();
            }

            $post->title = $data['title'];
            $post->save();

            foreach (self::GALLERIES as $state => $collection) {
                app(GalleryRows::class)->sync(
                    $post, $collection,
                    array_values($data[$state] ?? []),
                    $data[$state.'__names'] ?? [],
                );
            }

            $this->form->model($post);
            foreach (array_keys(self::GALLERIES) as $state) {
                $this->form->getComponent($state)->completeMediaUploadsAfterSave($post);
            }

            return $post;
        });

        Notification::make()->title('Gallery saved')->success()->send();
        $this->redirect(static::getUrl(['post' => $post->getKey()]), navigate: true);
    }

    private function authorizedPost(): ?Post
    {
        abort_unless(Auth::check(), 403);

        if ($this->postId === null) {
            Gate::authorize('create', Post::class);

            return null;
        }

        $post = Post::query()->findOrFail($this->postId);
        Gate::authorize('update', $post);

        return $post;
    }
}
```
{/* /recipe-source */}

`getState()` validates and stores temporary uploads. The SQL transaction saves only explicitly selected attributes and scoped gallery rows. `completeMediaUploadsAfterSave()` runs its reference checks and acceptance work **after commit**, using the mounted field instance. Keep FLEX's own relationship hooks intact; do not replace them with `saveRelationshipsUsing()` to save these rows.

`allowFilePathUsing` authorizes existing paths during reads and validation. `mediaReferenceUsing` checks that a saved gallery row actually references the file. Neither callback creates that row. Browser state must never supply `post_id`, the storage disk, conversion paths or an arbitrary gallery model to update.

Removing a tile and saving removes its relation row. This example deliberately retains the underlying bytes, including conversions. Canceling or failing a form save must not destroy the previously saved gallery. Configure a separate, reference-aware retention workflow for physical deletion; SQL cascades alone do not delete disk objects. See [removal and replacement](/docs/file-upload-storage-and-validation#removal-and-replacement).

## 6. Render and register the page

{/* recipe-source: resources/recipes/post-gallery/resources/views/filament/pages/post-gallery.blade.php */}
```blade resources/views/filament/pages/post-gallery.blade.php
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-6"
            wire:loading.attr="disabled" wire:target="save">
            Save post and galleries
        </x-filament::button>
    </form>
</x-filament-panels::page>
```
{/* /recipe-source */}

Default Filament page discovery finds `App\Filament\Pages\PostGallery`. If your panel does not discover that directory, add it to the panel's existing page list:

```php
->pages([
    // Keep the panel's other registered pages.
    \App\Filament\Pages\PostGallery::class,
])
```

Generate links through Filament so panel prefixes and domains remain correct:

```php
use App\Filament\Pages\PostGallery;

$createUrl = PostGallery::getUrl();
$editUrl = PostGallery::getUrl(['post' => $post->getKey()]);
```

Save a post, follow its edit URL, reload, and check both galleries, original filenames, order, thumbnails and the viewer. The `posts` row contains `title` and `user_id`; each original file has its own `galleries` row. Selecting a preview conversion does not replace the original path stored in SQL.

## 7. Optional: generate conversions in a worker

The main recipe creates `thumb` and `large` synchronously. To move encoding out of the save request, add these options to `galleryField()` after `imageConversions()`:

```php
->queueImageConversions()
->imageConversionQueue(
    new \Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions(
        connection: 'database',
        queue: 'media-conversions',
        tries: 3,
        timeout: 60,
    ),
)
```

Use an existing durable `database` queue with its Laravel job tables and `retry_after` greater than 60 seconds. Configure shared storage for the originals, staging, manifests and conversion journals if web and worker processes run on different machines. Then run:

```bash
php artisan queue:work database --queue=media-conversions --timeout=60
```

FLEX dispatches conversions only after the saved relation references the original and the transaction commits. While conversion work is pending, the original remains available as the preview fallback. Failed work retains the original. The original SQL path remains stable. See [worker configuration and recovery](/docs/media-os-configuration#conversion-worker).

## 8. Use images outside the form

Saved images can be displayed on your website or returned by your API without mounting File Upload. To display originals, read each gallery row's `disk` and `path`.

A separate SQL row per conversion is **optional**. Add it when you want your own database to answer queries for named versions such as `thumb` and `large`. Creating an API does not, by itself, require that extra table.

Follow [Gallery images in your website and API](/docs/file-upload-gallery-api) for the optional conversion index, a complete Blade gallery, an authorized JSON endpoint and the simpler Spatie alternative. These are application examples; no changes to FLEX's runtime are required.

## 9. Alternative: use Spatie collections

Use this alternative when Spatie should own media persistence. It writes to Spatie's `media` table and model relationship, rather than mirroring the same files into this recipe's `galleries` and `gallery_conversions` tables.

Install the optional dependencies and publish their migrations using the [Spatie setup guide](/docs/media-os-spatie). On the existing `Post` model, implement `HasMedia`, add `InteractsWithMedia`, and define collections/conversions:

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    // Keep your existing attributes and application relationships.

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')->useDisk('public');
        $this->addMediaCollection('interior')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 320, 320)
            ->format('webp')->performOnCollections('main', 'interior')->queued();
        $this->addMediaConversion('large')->width(1600)
            ->format('webp')->performOnCollections('main', 'interior')->queued();
    }
}
```

In a normal Filament Resource form attached to that authorized `Post`:

```php
use App\Support\GalleryFileName;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

FlexSpatieMediaLibraryFileUpload::make('main_photos')
    ->collection('main')->multiple()->maxFiles(12)->maxSize(5120)->visibility('public')
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->uploadLayout('grid')->reorderable()
    ->getUploadedFileNameForStorageUsing(
        fn (TemporaryUploadedFile $file): string => app(GalleryFileName::class)($file, 'data.main_photos'),
    )
    ->previewConversion('thumb')->fileViewer()->fileViewerConversion('large');

FlexSpatieMediaLibraryFileUpload::make('interior_photos')
    ->collection('interior')->multiple()->maxFiles(12)->visibility('public')
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->previewConversion('thumb')->fileViewer()->fileViewerConversion('large');
```

Use native Resource create/edit relationship persistence for these fields. Do not reuse the custom `galleries` save loop from step 5. Do not add JSON columns for `main_photos`/`interior_photos`, and do not enable native disk `imageConversions()`/`queueImageConversions()` on the Spatie fields. Spatie owns its queued conversions, path generator and file naming configuration; keep those existing model/package settings. It stores each original as a `media` row and tracks its generated conversions as metadata on that row. It does not require an extra `gallery_conversions` row per version. Use its native methods to retrieve conversion URLs.

Outside the form, use the same authorized model and native Spatie APIs:

```php
$media = $post->getFirstMedia('main');
$url = $media === null ? null : $media->getUrl(
    $media->hasGeneratedConversion('thumb') ? 'thumb' : '',
);
```

For private Spatie files use its authorized temporary delivery configuration. See [Spatie conversions, queue and storage configuration](/docs/media-os-spatie).

For complete website and JSON examples with Spatie, see [Use Spatie outside the form](/docs/file-upload-gallery-api#spatie-use-the-existing-media-apis). No additional conversion index is needed.
