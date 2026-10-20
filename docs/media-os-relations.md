---
title: "Save gallery files in related SQL rows"
sidebarTitle: "Related galleries"
description: "Map a disk-backed upload to an existing hasMany gallery, with explicit authorization, ordering, rollback and stale-edit detection."
icon: "database"
---

Use `HasManyMediaCollection` when a post, product or another model owns an existing
`hasMany` gallery: **one SQL row per original file**. No upload JSON attribute is
added to the parent, and no package-owned media model or migration is installed.

The adapter handles scoped reading, synchronization, order, original names,
duplicate-reference checks and stale edits. Your application supplies its model,
disk, directory and authorization. It does not upload files, convert images,
accept staged uploads or delete physical files.

For the complete first-time table, model and field setup, start with
[the Post gallery recipe](/docs/file-upload-gallery-recipe). This page replaces
its application-owned row synchronization with the package adapter. The original
recipe remains available for applications that deliberately own their mapper.

## 1. Keep a unique reference to each original

Use the recipe's `posts` and `galleries` tables and models. Add a unique index on
the original's disk and path:

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('galleries', function (Blueprint $table): void {
    $table->unique(['disk', 'path']);
});
```

The adapter checks for competing references; the database index closes the race
between writers attaching the same object to different parents. Resolve existing
duplicates before applying this migration. If your application intentionally
shares one original between several gallery rows, keep a reference-aware custom
mapper instead of using this exclusive-ownership adapter.

Do not make `sort_order` unique: reordering updates rows inside one transaction.
The parent and gallery must use the **same database connection**. The initial
adapter supports ordinary `hasMany` relations without soft deletes, joins,
grouping, limits or pagination. Mapping column names must be distinct simple SQL
identifiers and cannot replace primary keys, foreign keys or timestamps.

## 2. Define your collection once

This factory uses the recipe's existing `Post` policy. Both reading and saving
the editor require update permission. Adapt the callback if your application
has different view/edit rules.

```php app/Support/PostMediaCollections.php
<?php

namespace App\Support;

use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PostMediaCollections
{
    /** @param list<string> $admittedNewPaths */
    public static function make(string $collection, array $admittedNewPaths = []): HasManyMediaCollection
    {
        if (! in_array($collection, ['main', 'interior'], true)) {
            throw new InvalidArgumentException('Unknown post gallery.');
        }

        return new HasManyMediaCollection(
            relation: 'galleries',
            disk: 'public',
            collection: $collection,
            directory: fn (Model $post): string => 'posts/'.$post->user_id.'/'.$collection,
            authorize: function (Model $post, string $operation): bool {
                Gate::authorize('update', $post);

                return true;
            },
            admit: fn (Model $post, string $path, ?Model $existing): bool =>
                $existing !== null || in_array($path, $admittedNewPaths, true),
            maxItems: 12,
        );
    }
}
```

`admittedNewPaths` must come from the mounted upload field's validated, stored
`getState()` result, with its existing-path protection intact. Do not pass raw
request paths or merely validate that a string starts with a directory.
For a server-only workflow, admit paths from receipts your authorized service
actually created, after checking their owner and destination.

Directory membership and physical file existence are additional checks; neither
proves who uploaded a new file. The adapter calls your admission callback even
when a submitted path already matches the saved state.

The callback receives the existing related row only when it already belongs to
the same parent, collection and disk. The adapter also rejects a disk/path claimed
outside that scoped set, including references hidden by related-model scopes.
It never transfers that other row or removes its file.

### Use different existing column names

```php
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationColumns;

// Pass this as the constructor's columns argument.
$columns = new MediaRelationColumns(
    disk: 'storage_disk',
    path: 'object_key',
    collection: 'gallery_group',
    originalName: 'client_filename',
    order: 'position',
);
```

The adapter sets the parent foreign key through the relationship. The browser
never supplies that key, a row ID, disk, collection or conversion path. A path is
limited to 512 bytes; original names accept bounded UTF-8 text and are shortened
to 255 characters. The configured limit is between 1 and 1000 originals.

## 3. Read paths, names and a revision

```php
use App\Support\PostMediaCollections;

$snapshot = PostMediaCollections::make('main')->read($post);

$data['gallery_main'] = $snapshot->paths;
$data['gallery_main__names'] = $snapshot->names;
$this->mediaRevisions['main'] = $snapshot->revision;
```

Keep the revision captured when the form opens. On a Livewire page, protect the
revision map from client changes:

```php
use Livewire\Attributes\Locked;

#[Locked]
public array $mediaRevisions = [];
```

Hydrate `main` and `interior` separately. The revision identifies the scoped rows,
their order and original names. It is a conflict detector, **not** authorization
or a file receipt. Reads always query the authorized parent and related rows;
they do not depend on a previously loaded `$post->galleries` collection.

For the recipe's existing-file/reference callbacks, you can use:

```php
PostMediaCollections::make('main')->references($post, $path);
```

Keep `preventFilePathTampering()` and `mediaReferenceUsing()` on the upload field.
They still protect field reads and commit-aware upload acceptance. Keep the
recipe's retention settings when detached files should remain on disk.

## 4. Save references inside the parent's transaction

The paths below are the **stored field state**, after form validation and upload
storage. They are not `TemporaryUploadedFile` objects.

```php
use App\Support\PostMediaCollections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

$state = $this->form->getState();
$openedRevisions = $this->mediaRevisions;

$committedRevisions = DB::transaction(function () use ($post, $state, $openedRevisions): array {
    // Keep your existing authorized parent update here, in the same transaction.
    Gate::authorize('update', $post);
    $post->update(['title' => $state['title']]);

    $revisions = [];
    foreach (['main', 'interior'] as $collection) {
        $field = 'gallery_'.$collection;
        $paths = array_values($state[$field] ?? []);
        $adapter = PostMediaCollections::make($collection, $paths);
        $saved = $adapter->sync(
            $post,
            paths: $paths,
            names: $state[$field.'__names'] ?? [],
            expectedRevision: $openedRevisions[$collection],
        );
        $revisions[$collection] = $saved->revision;

        // Custom save page only. The mounted field completes after commit.
        $this->form->getComponent($field)->completeMediaUploadsAfterSave($post);
    }

    return $revisions;
});

// Update the editor baseline only after every part of the SQL save commits.
$this->mediaRevisions = $committedRevisions;
```

Use the parent's actual connection if it is not the application's default:
`$post->getConnection()->transaction(...)`. Never catch a conflict inside the
parent transaction and then commit a partly updated parent. Let it abort that
save and ask the user to reload the gallery.

For a newly created parent, authorize creation and persist the parent inside its
transaction first. Obtain its empty collection revision with `read($post)`, then
call `sync()` and attach the new model with `$this->form->model($post)` before
completing its mounted upload fields. There is no parent ID to trust from the
browser. An edit must use
the revision captured when the form opened; reading a new revision immediately
before an edit save would bypass stale-edit detection.

The adapter locks and reauthorizes the current parent before writing. On MySQL,
it also uses current locking reads for the gallery rows, so an earlier
`REPEATABLE READ` snapshot cannot hide a competing committed edit. All other
gallery writers must follow the same parent-lock convention. Keep the unique
disk/path index even when they do.

### Native Filament Resource pages

Use the same `read()` in `mutateFormDataBeforeFill()` and the same `sync()` inside
`handleRecordUpdate()` or `handleRecordCreation()`, with
`$hasDatabaseTransactions = true`. Preserve existing Resource authorization.
Do not add the manual completion call above: native `RecordSaved` handling already
accepts referenced uploads after commit.

If a Resource updates its revision map before its outer save transaction ends,
schedule that map update through `$post->getConnection()->afterCommit(...)`.
Do not leave uncommitted revisions in component state after a rolled-back save.
See [the complete Resource example](/docs/file-upload-resource-recipe) for native
page hooks and field configuration.

## Conflicts, retries and removal

| Outcome | Behavior |
| --- | --- |
| First valid save | Related originals are inserted or updated and ordered. Existing row IDs stay stable. |
| Another editor changed the collection | `MediaFailure` with reason `relation_conflict`; reload before applying a different state. |
| Response was lost after a successful save | Replaying the exact already-committed paths/names is a no-op after authorization and admission checks. |
| Save or a model event fails | Relation changes roll back with the SQL transaction. The adapter never compensates by deleting disk objects. |
| A tile is removed and saved | Its related row is detached. Physical originals and conversions remain for your reference-aware retention workflow. |

Model observers and custom callbacks are application code; keep destructive file
operations out of pre-commit model events. SQL and storage are separate systems.
A SQL cascade does not delete files, and a database rollback does not roll back
earlier upload or conversion work.

## Conversions and Spatie

Named native conversions remain in their existing server-owned receipts and
conversion journals. This adapter stores one row per **original**, rather than
guessing variant filenames or accepting derivative paths from the browser.
For an optional SQL conversion index and website/API examples, see
[Gallery images in your website and API](/docs/file-upload-gallery-api).

For Spatie Media Library, keep its own media model, collections, conversions and
FLEX Spatie field. Do not mirror the same attachment into this adapter's rows.
Spatie's polymorphic media relationship is outside this initial `hasMany` contract.
See [Spatie media](/docs/media-os-spatie).

## Map file metadata explicitly

Add a nullable JSON `metadata` column to your existing `galleries` table and an Eloquent `'metadata' => 'array'` cast to `Gallery`. This stores a file's captions or alt text on its gallery row; the form's file list still uses separate relational records.

```php
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationColumns;

// In your existing HasManyMediaCollection configuration:
columns: new MediaRelationColumns(metadata: 'metadata'),
```

Pass application-validated metadata keyed by the admitted original path as the last argument to `sync()`:

```php
$next = $collection->sync(
    $post, $paths, $originalNames, $expectedRevision,
    metadata: [$paths[0] => ['alt' => ['en' => 'The yacht at sunset']]],
);
```

Omitting a path's metadata preserves its current value. An explicit `[$path => []]` clears it. Metadata participates in conflict detection, so an old form cannot silently overwrite a newer caption. Accept only the keys and locales your application permits; the adapter's JSON bounds are not a substitute for validation. Each entry accepts JSON values only, at most 16 KiB, 512 nested entries and eight levels of nesting. Never put access tokens or file authority in metadata.

## Project ready conversions into SQL

Use `HasManyMediaConversions` when your website or API needs to query named versions in a separate table. It indexes existing server-owned receipts; it neither converts an image nor deletes stored bytes. With Spatie, use Spatie's own conversion APIs instead of this projection.

The original row needs its existing `disk` and `path` columns and a simple `conversions(): HasMany` relationship on the same database connection. Create the application's conversion table explicitly:

```php
Schema::create('gallery_conversions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
    $table->string('name', 64);
    $table->string('disk', 128);
    $table->string('path', 512);
    $table->string('mime_type', 128);
    $table->unsignedBigInteger('bytes');
    $table->char('sha256', 64);
    $table->string('operation_id');
    $table->unsignedInteger('width');
    $table->unsignedInteger('height');
    $table->timestamps();
    $table->unique(['gallery_id', 'name']);
});
```

On `Gallery`, return `$this->hasMany(GalleryConversion::class)` from `conversions()`. `GalleryConversion` is your normal Eloquent model. Do not use soft deletes or model observers that delete disk objects for this index.

For a synchronous `ImageBundle` returned by the [image pipeline](/docs/media-os-images), configure the projector using that exact server-owned plan:

```php
use App\Models\Gallery;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaConversions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

$projection = new HasManyMediaConversions(
    relation: 'conversions',
    authorize: function (Gallery $image): bool {
        // The adapter reloads the gallery row before invoking this callback.
        Gate::authorize('update', $image->post);
        return true;
    },
    authority: function (Gallery $image, MediaReceipt $receipt, ?string $name) use ($bundle): MediaWrite {
        $expected = $name === null ? $bundle->original : ($bundle->variants[$name] ?? null);
        abort_unless($expected === $receipt, 403);
        return $name === null ? $bundle->intent : $bundle->intents[$name];
    },
);

// Capture before changing the projection; keep this on the server.
$revision = $projection->revision($gallery);
DB::transaction(fn () => $projection->sync(
    $gallery, $bundle->original, $bundle->variants, $revision,
));
```

The authority callback must match every receipt to the current trusted plan and supply the appropriate `MediaWrite` policy. A directory prefix, readable path or receipt from browser state is insufficient. FLEX verifies actual stored bytes through Media OS before changing any conversion rows. The original receipt must match the freshly loaded gallery row.

Use `MediaConversionColumns` for explicit alternative column names. `sourceDiskColumn` and `sourcePathColumn` map the original row, and `maxVariants` limits the complete projection (default 32, maximum 100).

For queued work, first obtain an authorized `QueuedImageConversions::snapshot()`. Only project when its `status` is `ready`; pending or failed work must retain the previous SQL index. Build authority from that trusted journal's exact original and named receipts, using the matching application policy for each output disk. Do not reuse the original's MIME allow-list for WebP/JPEG derivatives. See the [queued conversion example](/docs/file-upload-gallery-api#optional-make-named-conversions-queryable-in-sql).

`sync()` replaces the complete ready index. Passing `[]` explicitly removes its rows. A repeated identical request preserves row IDs; a stale different request raises `relation_conflict`. Reload and review the current index before retrying a conflict. Database failures roll back the index, including a model event veto. The parent save and projection require an outer transaction. External side effects in your own observers remain your application's responsibility.
