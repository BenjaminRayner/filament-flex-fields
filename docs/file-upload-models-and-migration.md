---
title: "Save uploads to models and galleries"
description: "Persist disk paths, connect custom gallery tables, use multiple collections and migrate existing files and thumbnails."
icon: "database"
---

An upload has three stages: transfer into Livewire temporary storage, permanent file storage when the form dehydrates, and database persistence of the file reference. A finished progress bar confirms the first stage. Reloading an unsaved playground/form does not save its state. To restore files after reload, save the model or relation and fill the form from that saved data.

## Choose the database shape

| Existing schema | Configuration |
| --- | --- |
| One string column, e.g. `contract_path` | Single `FlexFileUpload`, same field name |
| JSON array of paths, e.g. `photos` | `multiple()` and an Eloquent array cast |
| Your own `gallery` / `attachments` table | Explicit hydrate/save mapping and `mediaReferenceUsing()` |
| Spatie `media` table | `FlexSpatieMediaLibraryFileUpload` and named collections |

Imports used below:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
```

## One file in a model column

Keep the column and field names identical. A normal Filament Resource create/edit page saves the dehydrated path with its model data:

```php
FlexFileUpload::make('contract_path')
    ->disk('local')->directory('contracts')->visibility('private')
    ->acceptedFileTypes(['application/pdf'])->maxSize(5120)
    ->preventFilePathTampering()->openable()->downloadable();
```

The string column stores a **relative disk path**, not the signed/public URL, temporary filename or file label. On edit, native hydration loads that path from the authorized record. Your Resource policies still control access to that record.

## Several files in a JSON column

In `Post`:

```php
protected function casts(): array
{
    return ['photos' => 'array', 'photo_names' => 'array'];
}
```

In the form:

```php
FlexImageUpload::make('photos')
    ->multiple()->maxFiles(12)->uploadLayout('grid')->reorderable()
    ->disk('public')->directory('posts/photos')->visibility('public')
    ->imagesOnly()->maxSize(5120)->preventFilePathTampering()
    ->storeFileNamesIn('photo_names')->fileViewer();
```

Use JSON columns for both arrays. `photo_names` is an optional path-to-original-name map for display. The main array remains a list of paths. The standard Resource lifecycle accepts stored media after the saved record references it. [Cleanup choices](/docs/file-upload-storage-and-validation#removal-and-replacement) are independent of this persistence mapping.

## Your own gallery table: three galleries on one post

Assume `Post::gallery()` is a `hasMany(Gallery::class)` relation. The gallery table has `post_id`, `collection`, `disk`, `path`, `original_name` and `sort_order`. Add a unique constraint on `(post_id, collection, disk, path)` and an index on `(post_id, collection, sort_order)`. Authorize the post before mounting/saving; scope the relation to the current tenant where appropriate.

Use three **different field names** and collection keys. These are virtual form values; do not add them to the `posts` table just to run this recipe:

```php
private const GALLERIES = [
    'gallery_main' => 'main',
    'gallery_interior' => 'interior',
    'gallery_documents' => 'documents',
];

private function galleryField(string $state, string $collection): FlexFileUpload
{
    return FlexFileUpload::make($state)
        ->multiple()->maxFiles(12)->uploadLayout('grid')->reorderable()
        ->disk('public')->directory('posts/'.$collection)->visibility('public')
        ->maxSize(5120)->storeFileNamesIn($state.'__names')
        ->preventFilePathTampering(allowFilePathUsing:
            fn (?Post $record, string $file): bool => $record?->exists
                && $record->gallery()->where('collection', $collection)
                    ->where('disk', 'public')->where('path', $file)->exists())
        ->mediaReferenceUsing(fn (Post $record, string $identifier): bool =>
            $record->gallery()->where('collection', $collection)
                ->where('disk', 'public')->where('path', $identifier)->exists())
        ->deleteFileOnRemove(false)->deleteReplacedFiles(false)
        ->pruneOrphanedOnSave(false);
}

```

Inside your form schema, alongside a title field:

```php
return [
$this->galleryField('gallery_main', 'main')->imagesOnly()->fileViewer(),
$this->galleryField('gallery_interior', 'interior')->imagesOnly()->fileViewer(),
$this->galleryField('gallery_documents', 'documents')->documentsOnly(),
];
```

`Post` and `Gallery` above are your application models. `allowFilePathUsing` authorizes existing relation paths; `mediaReferenceUsing` proves that the database saved a newly stored path. They serve different purposes. The example leaves physical-file deletion to your retention workflow so removing an item from an unsaved form cannot destroy the previous saved gallery.

### Restore the same three galleries on edit

Configure the form with `->model($post)->statePath('data')`, where `data` is a public array. Fill only from the authorized post relation:

```php
$data = ['title' => $post->title];
$rows = $post->gallery()->orderBy('sort_order')->get();

foreach (self::GALLERIES as $state => $collection) {
    $items = $rows->where('collection', $collection)->where('disk', 'public');
    $data[$state] = $items->pluck('path')->values()->all();
    $data[$state.'__names'] = $items->pluck('original_name', 'path')->all();
}

$this->form->fill($data);
```

### Save files and relation rows together

The following is a custom Livewire form save method. Its schema contains the fields above and a `title` input. `$this->record` is an authorized existing `Post` or null; `$this->data` is the form state. On create, set the form model to `Post::class` until the new post exists. Use your application's additional required columns and policy checks in the indicated places.

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

public function save(): void
{
    Gate::authorize($this->record ? 'update' : 'create', $this->record ?? Post::class);
    $data = $this->form->getState(); // Validate; store temporary files through Media OS.

    DB::transaction(function () use ($data): void {
        $post = $this->record ?? new Post();
        $post->title = $data['title'];
        $post->save();

        foreach (self::GALLERIES as $state => $collection) {
            $paths = array_values($data[$state] ?? []);
            $names = $data[$state.'__names'] ?? [];
            foreach ($paths as $position => $path) {
                $post->gallery()->updateOrCreate(
                    ['collection' => $collection, 'disk' => 'public', 'path' => $path],
                    ['original_name' => $names[$path] ?? basename($path), 'sort_order' => $position],
                );
            }
            $removed = $post->gallery()->where('collection', $collection)->where('disk', 'public');
            if ($paths !== []) {
                $removed->whereNotIn('path', $paths);
            }
            $removed->delete(); // Relation rows only; retained files follow your own policy.
        }

        $this->record = $post;
        $this->form->model($post);
        foreach (array_keys(self::GALLERIES) as $state) {
            $this->form->getComponent($state)->completeMediaUploadsAfterSave($post);
        }
    });
}
```

For nested fields, give the component an explicit `key()` and resolve its actual schema key rather than assuming the root field name. Completion must use the **mounted field instance**. It waits for the database transaction to commit and verifies each saved relation reference. Do not replace FLEX's `saveRelationshipsUsing()` just to persist gallery rows: replacing that hook also replaces its acceptance work.

This example creates gallery entries on successful form save. Creating rows as soon as a file finishes temporary transfer needs an explicit draft/pending-record workflow, ownership and abandoned-draft cleanup. `Stored` alone does not prove the post transaction committed. Use [events and custom save acceptance](/docs/file-upload-events-and-recovery) for those workflows.

A Resource can use the same mapping in its create/update data hooks and model handlers: remove virtual gallery values from the attributes sent to `Post`, write the scoped relation rows in the save transaction, and complete the mounted upload fields after those rows exist. Never mass-assign the entire browser state into a gallery model.

## Keep an existing files table

You do not have to migrate its schema to Spatie. Adapt the relation recipe:

| Existing column | Map to |
| --- | --- |
| `file_path`, `storage_key` | Field path list and relation reference proof |
| `storage_disk` | The field's actual Laravel disk |
| `filename`, `original_filename` | Optional `storeFileNamesIn()` map |
| `position` | Ordered hydration and saving |
| `post_id`, tenant / collection columns | Owner-scoped relation queries |

Group files by disk/visibility; one disk field is not a mixed-disk picker. Do not feed full legacy URLs or numeric gallery IDs into a path-based field. Resolve IDs to authorized relative paths on the server and map saved paths back to your table. A separate migration is needed if the old database only contains URLs with no storage ownership/path mapping.

## Replace native Filament FileUpload

If the existing field already saves Laravel paths, change the class import and preserve its field name, `multiple()`, disk, directory, visibility, casts and original-name companion column:

```php
// Before: Filament\Forms\Components\FileUpload::make('attachments')
FlexFileUpload::make('attachments')
    ->multiple()->disk('s3')->directory('documents')->visibility('private')
    ->maxSize(5120)->preventFilePathTampering()->openable()->downloadable();
```

Saved paths are hydrated from the same model attribute. A change of class does not copy files, rewrite database paths or regenerate old conversions. Keep existing signing/CORS settings. Review old low-level `saveUploadedFileUsing()`/`getUploadedFileUsing()` overrides individually; replacing those callbacks can bypass the Media OS storage or read bridge. Prefer the dedicated options in these pages.

For a native Spatie field, replace it with `FlexSpatieMediaLibraryFileUpload`, retaining the authorized record, collection, disks, filters and model conversion names. Existing media UUIDs/relationships remain Spatie-owned; do not convert them into disk paths.

## Use thumbnails generated by an older system

Keep the original's relative path as field state. `previewUrlUsing()` overrides only the saved-file thumbnail after the original passes the bridge's ownership/read checks. Its named arguments are `file` (disk path or Spatie UUID), `defaultUrl`, `component` and `record`. Return null to retain the normal preview; pass null to the setter to remove the override.

```php
use Illuminate\Support\Facades\Storage;

FlexImageUpload::make('photos')
    ->multiple()->disk('public')->visibility('public')->uploadLayout('grid')
    ->preventFilePathTampering()->openable()->downloadable()->fileViewer()
    ->previewUrlUsing(function (?Post $record, string $file): ?string {
        // Prefer a preloaded, owner-scoped relation when displaying many thumbnails.
        $row = $record?->gallery()->where('disk', 'public')->where('path', $file)->first();
        return $row?->thumbnail_path
            ? Storage::disk('public')->url($row->thumbnail_path)
            : null;
    });
```

Combine this with the gallery path authorization above when `photos` is a virtual field instead of a model column. The example requires your legacy `thumbnail_path` column. Return a trusted application-relative or HTTP(S) URL; active schemes, credentials and malformed URLs reject. Do not use a browser-supplied thumbnail path. For private storage, resolve a short-lived signed URL through your authorized application delivery service instead of making it public.

The list/grid thumbnail uses that small image. The lightbox defaults to the **original** through Media OS. Choose another named large conversion with `fileViewerConversion('large')`, or resolve a trusted legacy large URL with `fileViewerUrlUsing()`. The image editor, Open and Download retain the original. Store the large original path as state; do not put the thumbnail path there. The original must remain available on its declared disk. This resolver does not import old conversions into FLEX manifests, regenerate images or delete old thumbnail files. Your old conversion-retention policy remains yours.

## Generate conversions for new uploads

```php
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;

FlexImageUpload::make('photos')
    ->multiple()->imagesOnly()->disk('public')->visibility('public')
    ->imageConversions([new ImageConversion(name: 'thumb', width: 320, height: 320)])
    ->previewConversion('thumb')->fileViewer();
```

Native conversion manifests retain the original and owned variants. Existing unconverted originals fall back to the original; they are not automatically reprocessed on edit. Use `previewUrlUsing()` for existing external derivatives, or a deliberate migration job. For Spatie, retain model `registerMediaConversions()` and use `previewConversion('thumb')`; do not also enable a second disk conversion engine. [Image and queue configuration](/docs/file-upload-images-and-sources).

## Several Spatie galleries

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;

FlexSpatieMediaLibraryFileUpload::make('main_photos')
    ->collection('main')->multiple()->imagesOnly()->uploadLayout('grid')->fileViewer();
FlexSpatieMediaLibraryFileUpload::make('interior_photos')
    ->collection('interior')->multiple()->imagesOnly()->uploadLayout('grid')->fileViewer();
FlexSpatieMediaLibraryFileUpload::make('documents')
    ->collection('documents')->multiple()->documentsOnly();
```

Attach the form to an authorized `Post` implementing Spatie `HasMedia`/`InteractsWithMedia`. Native relationship persistence handles create/edit records; each collection remains independent. Register collection disks and conversions on your model. [Complete Spatie setup](/docs/media-os-spatie).

## Choose your own stored filename

```php
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

FlexFileUpload::make('documents')
    ->multiple()->disk('local')->directory('documents')
    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string =>
        'invoice-'.Str::uuid().'.'.$file->extension())
    ->storeFileNamesIn('document_names');
```

You control the basename. Use a unique, trusted name and the inspected extension; do not insert a path or unsanitized customer filename. A fixed name such as `invoice.pdf` is possible only when the destination is unique/empty: Media OS refuses to overwrite existing files. Changing this generator affects **new** files; existing paths and legacy names still load unchanged. [Filename and collision rules](/docs/file-upload-storage-and-validation#filename-generation).

## Verify persistence before deployment

Save a record, reload its edit form, verify file order/names and open an original. Test a second record and all three collection keys so files cannot cross owners/collections. Test removal plus a failed save separately from successful cleanup. A successful upload in the playground demonstrates transfer and UI, not your application's database mapping.
