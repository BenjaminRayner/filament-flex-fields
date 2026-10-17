---
title: "Gallery images in your website and API"
description: "Use saved gallery images outside Filament, optionally index native conversions in SQL, or use Spatie's existing conversion APIs."
icon: "images"
---

You can use an uploaded image anywhere in your application. It does **not** have to be rendered by a FLEX field.

Start with [A complete Post gallery](/docs/file-upload-gallery-recipe). This page continues that example and adds a website gallery and a JSON endpoint. The original files are already saved; this page does not upload or convert them again.

## Choose the simplest approach

| Your setup | What you need |
| --- | --- |
| Your own `galleries` table; display originals | [Read the saved disk and path](#display-an-original-without-indexing-conversions). No conversion table is needed. |
| Your own table; query `thumb` / `large` in SQL | [Add the optional index](#optional-make-named-conversions-queryable-in-sql), then use the website/API examples. |
| Spatie Media Library | [Use the existing media APIs](#spatie-use-the-existing-media-apis). Skip the native SQL index entirely. |

An API response being JSON does **not** mean you must store uploads in a JSON database column. This example reads relational rows and returns URLs to the caller.

**Do I need to modify FLEX?** No. These are application models, routes, presenters and controllers using the existing storage and conversion interfaces. The advanced native index reads FLEX upload receipts, so keep that bridge covered during package upgrades. Do not expose its repository or conversion journals as public endpoints.

## Display an original without indexing conversions

For the recipe's public disk, an already authorized post can supply its original images directly:

```php
use Illuminate\Support\Facades\Storage;

$images = $post->galleries()->where('collection', 'main')->orderBy('sort_order')->get();
$urls = $images->map(fn ($image): string => Storage::disk($image->disk)->url($image->path));
```

For example, a saved original `posts/7/main/photo-deck-<uuid>.jpg` is read from the configured disk. You do not need to mount a Filament page or initialize File Upload on the website.

## Optional: make named conversions queryable in SQL

Skip this section if you use Spatie or only need originals. A conversion index is useful when your own application needs queries such as “give me the `thumb` for each gallery image”, dimensions in an API, or eager-loaded image versions for a website grid. It is one possible integration, not a requirement for using images outside a form.

The tables then have a simple relationship:

```text
posts.id
  └─ galleries.post_id               one row per original image
       └─ gallery_conversions.gallery_id
            thumb                    one row per named version
            large
```

This index copies actual server-owned receipts. It does not generate new conversions, replace FLEX's private receipts, or accept derivative paths from the browser. A conversion filename cannot reliably be inferred from the original basename.

The following bridge is specific to native disk uploads and the public storage policy in the original recipe. Spatie uses its own `media` records and conversion metadata instead.

### Add the conversion table and indexer

```php database/migrations/2026_10_17_000002_create_gallery_conversions.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table): void {
            $table->boolean('conversions_indexed')->default(false)->index();
        });

        Schema::create('gallery_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
            $table->unique(['gallery_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_conversions');
        Schema::table('galleries', function (Blueprint $table): void {
            $table->dropColumn('conversions_indexed');
        });
    }
};
```

```php app/Models/GalleryConversion.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryConversion extends Model
{
    protected $fillable = ['name', 'disk', 'path', 'mime_type', 'bytes', 'width', 'height'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
```

Add this relationship to the existing `Gallery` class:

```php
public function conversions(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\GalleryConversion::class);
}
```

```php app/Support/SyncGalleryConversions.php
<?php

namespace App\Support;

use App\Models\Gallery;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Illuminate\Support\Facades\DB;

final class SyncGalleryConversions
{
    public function handle(Gallery $gallery): bool
    {
        $manifest = app(UploadManifestRepository::class)->read($gallery->disk, $gallery->path);
        if ($manifest === null) {
            // Legacy originals have no native FLEX conversion receipts to index.
            $gallery->forceFill(['conversions_indexed' => true])->save();

            return true;
        }

        $authority = new MediaWrite(
            kind: $manifest->context['kind'] ?? 'upload',
            disk: $gallery->disk,
            field: 'gallery_'.$gallery->collection,
            record: $gallery->post,
            policy: new MediaPolicy(visibility: 'public'),
            metadata: $manifest->context['metadata'] ?? [],
        );
        FlexMedia::os()->authorize('inspect', $authority, $manifest->original);
        $variants = $manifest->variants;
        $journal = $manifest->context['queued_images'] ?? null;

        if (is_string($journal)) {
            $snapshot = app(QueuedImageConversions::class)->snapshot(
                $journal, $manifest->original, $authority,
            );
            if ($snapshot['status'] !== 'ready') {
                return false;
            }
            $variants = $snapshot['variants'];
        }

        DB::transaction(function () use ($gallery, $variants): void {
            $row = Gallery::query()->where('disk', $gallery->disk)
                ->where('path', $gallery->path)->lockForUpdate()->find($gallery->getKey());
            if ($row === null) {
                return;
            }

            foreach ($variants as $name => $receipt) {
                $row->conversions()->updateOrCreate(['name' => $name], [
                    'disk' => $receipt->disk,
                    'path' => $receipt->path,
                    'mime_type' => $receipt->file->mimeType,
                    'bytes' => $receipt->file->bytes,
                    'width' => $receipt->file->width,
                    'height' => $receipt->file->height,
                ]);
            }

            $stale = $row->conversions();
            if ($variants !== []) {
                $stale->whereNotIn('name', array_keys($variants));
            }
            $stale->delete();
            $row->forceFill(['conversions_indexed' => true])->save();
        });

        return true;
    }
}
```

For synchronous conversions, optionally call the service immediately after the `$row = ...updateOrCreate(...)` statement in `save()`:

```php
app(\App\Support\SyncGalleryConversions::class)->handle($row);
```

For queued conversions the same call returns `false` until the worker finishes. A server-side reconciliation command covers both delayed processing and retries without depending on delivery of a browser event or a best-effort storage notification:

```php app/Console/Commands/IndexGalleryConversions.php
<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use App\Support\SyncGalleryConversions;
use Illuminate\Console\Command;
use Throwable;

class IndexGalleryConversions extends Command
{
    protected $signature = 'galleries:index-conversions';

    protected $description = 'Index ready native FLEX gallery conversions';

    public function handle(SyncGalleryConversions $sync): int
    {
        $failed = false;
        Gallery::query()->where('conversions_indexed', false)->with('post')
            ->chunkById(100, function ($rows) use ($sync, &$failed): void {
                foreach ($rows as $row) {
                    try {
                        $sync->handle($row);
                    } catch (Throwable $error) {
                        report($error);
                        $failed = true;
                    }
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
```

```php routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('galleries:index-conversions')->everyMinute()->withoutOverlapping();
```

Run Laravel's scheduler in production. Pending/failed conversions remain unindexed; the indexer does not retry conversion encoding itself. Repair/retry the conversion operation through your server-side recovery workflow. To rebuild an index after an intentional source/conversion migration, reset `conversions_indexed` for the affected rows and rerun the command.

The next examples assume this optional index has been installed and the `Gallery::conversions()` relationship added. They work while indexing is pending: missing thumbnails/viewer versions fall back to the original.

## Share one image representation between Blade and JSON

This small application class reads saved rows and produces display URLs. It performs no upload or conversion work. Controllers authorize the post first and eager-load conversions, so listing images does not run a query for every thumbnail. Disk names and storage paths remain on the server; consumers receive URLs.

```php app/Support/GalleryImageData.php
<?php

namespace App\Support;

use App\Models\Gallery;
use Illuminate\Support\Facades\Storage;

final class GalleryImageData
{
    public static function make(Gallery $gallery): array
    {
        $versions = $gallery->conversions->keyBy('name');
        $thumb = $versions->get('thumb');
        $large = $versions->get('large');

        return [
            'id' => $gallery->getKey(),
            'name' => $gallery->original_name,
            'original_url' => Storage::disk($gallery->disk)->url($gallery->path),
            'thumbnail_url' => Storage::disk($thumb?->disk ?? $gallery->disk)
                ->url($thumb?->path ?? $gallery->path),
            'viewer_url' => Storage::disk($large?->disk ?? $gallery->disk)
                ->url($large?->path ?? $gallery->path),
            'conversions' => $versions->map(fn ($version): array => [
                'url' => Storage::disk($version->disk)->url($version->path),
                'mime_type' => $version->mime_type,
                'width' => $version->width,
                'height' => $version->height,
            ])->all(),
        ];
    }
}
```

These URLs are for the recipe's **public** files: anyone with a URL can access its bytes. A login-protected JSON endpoint does not make a public disk private. For restricted attachments use private disks and authorized, expiring delivery URLs instead of `url()`. Never fall back to a public URL when signing fails, and never store an expiring URL as the database path.

## Add a website gallery and JSON endpoint

Use a Laravel API Resource to expose only the chosen image properties. See [Laravel API Resources](https://laravel.com/docs/13.x/eloquent-resources).

```php app/Http/Resources/GalleryImageResource.php
<?php

namespace App\Http\Resources;

use App\Support\GalleryImageData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return GalleryImageData::make($this->resource);
    }
}
```

The controller uses the original recipe's owner policy for both routes. It paginates images and eager-loads their conversion rows. This example allows the owner to view the gallery; replace `update` with your own `view` policy when other authorized users should have read access.

```php app/Http/Controllers/PostGalleryController.php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\GalleryImageResource;
use App\Models\Post;
use App\Support\GalleryImageData;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PostGalleryController extends Controller
{
    public function show(Post $post): View
    {
        $rows = $this->images($post)->paginate(24);

        return view('posts.gallery', [
            'post' => $post,
            'images' => $rows->getCollection()->map(
                fn ($gallery): array => GalleryImageData::make($gallery),
            ),
            'pagination' => $rows,
        ]);
    }

    public function data(Post $post): AnonymousResourceCollection
    {
        return GalleryImageResource::collection($this->images($post)->paginate(24));
    }

    private function images(Post $post): HasMany
    {
        Gate::authorize('update', $post);

        return $post->galleries()->where('collection', 'main')->where('disk', 'public')
            ->with('conversions')->orderBy('sort_order')->orderBy('id');
    }
}
```

```php routes/web.php
use App\Http\Controllers\PostGalleryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/posts/{post}/gallery', [PostGalleryController::class, 'show'])
        ->name('posts.gallery.show');
    Route::get('/posts/{post}/gallery.json', [PostGalleryController::class, 'data'])
        ->name('posts.gallery.data');
});
```

The JSON route uses your existing web session. For a token-based API, use your application's configured API authentication middleware and keep the same policy check. FLEX does not install or configure an API authentication package.

```blade resources/views/posts/gallery.blade.php
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $post->title }} — Gallery</title>
    </head>
    <body>
        <main>
            <h1>{{ $post->title }}</h1>

            @forelse ($images as $image)
                <figure>
                    <a href="{{ $image['viewer_url'] }}">
                        <img src="{{ $image['thumbnail_url'] }}"
                            alt="{{ $image['name'] }}" loading="lazy" decoding="async">
                    </a>
                    <figcaption>{{ $image['name'] }}</figcaption>
                    <a href="{{ $image['original_url'] }}">Open original</a>
                </figure>
            @empty
                <p>No images have been saved yet.</p>
            @endforelse

            {{ $pagination->links() }}
        </main>
    </body>
</html>
```

This intentionally minimal Blade view uses the filename as a fallback description. Use the editorial alt text you store for real content images. Add your website's layout and styling; this is not a Filament or FLEX UI renderer.

Generate both URLs using Laravel's route helpers:

```php
$galleryUrl = route('posts.gallery.show', $post);
$jsonUrl = route('posts.gallery.data', $post);
```

Opening the JSON URL while logged in returns Laravel's paginated `data`, `links` and `meta` response. Each image contains `original_url`, `thumbnail_url`, `viewer_url` and the named conversion metadata. The browser receives no raw upload state. Until a conversion is indexed, the corresponding display URL points to the original; rerunning the indexer updates later reads without changing the original gallery row.

## Spatie: use the existing media APIs

**With Spatie you normally do not need `gallery_conversions`, the native indexer or its scheduler.** Spatie saves each original as a `media` row related to your model. It tracks generated conversion names/status on that media record and resolves the actual conversion paths through its own APIs. It does not create a separate SQL row for every converted file.

Use `getUrl('thumb')` for a conversion and `hasGeneratedConversion('thumb')` before choosing it. See [Spatie's converted image documentation](https://spatie.be/docs/laravel-medialibrary/v11/converting-images/retrieving-converted-images). This alternative assumes the public `main` collection and `thumb`/`large` conversions from [the Spatie recipe](/docs/file-upload-gallery-recipe#9-alternative-use-spatie-collections).

An authorized JSON endpoint can use the existing media records directly:

```php app/Http/Controllers/SpatiePostGalleryController.php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SpatiePostGalleryController extends Controller
{
    public function __invoke(Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $images = $post->media()->where('collection_name', 'main')
            ->orderBy('order_column')->orderBy('id')->paginate(24)
            ->through(fn (Media $media): array => [
            'id' => $media->getKey(),
            'name' => $media->file_name,
            'original_url' => $media->getUrl(),
            'thumbnail_url' => $media->getUrl(
                $media->hasGeneratedConversion('thumb') ? 'thumb' : '',
            ),
            'viewer_url' => $media->getUrl(
                $media->hasGeneratedConversion('large') ? 'large' : '',
            ),
        ]);

        return response()->json($images);
    }
}
```

```php routes/web.php
use App\Http\Controllers\SpatiePostGalleryController;
use Illuminate\Support\Facades\Route;

Route::get('/posts/{post}/media-gallery.json', SpatiePostGalleryController::class)
    ->middleware('auth')->name('posts.media-gallery.data');
```

The owner-scoped `media()` query is paginated, so an existing large collection is not returned in one response. Native Spatie ordering, queued conversions and filename/path generators remain authoritative. For private media use authorized temporary URLs, not this public `getUrl()` example.

The same URLs can be used in your own Blade/JavaScript gallery. A separate conversion table is only useful if your application has an additional reporting or indexing requirement; it is not needed for normal Spatie website/API use.

## Verify your integration

1. Save two images and reload the edit page. Confirm the original gallery rows still belong to the same post.
2. Open the website and JSON routes as that owner. Confirm order, thumbnail URLs and larger viewer URLs.
3. With queued conversions, check the original fallback before processing and the converted URLs after processing/indexing.
4. Open either route as another user. The example policy must deny access.
5. Keep the storage paths in SQL. Do not save the public/temporary URLs returned by the presenter back into your models.

