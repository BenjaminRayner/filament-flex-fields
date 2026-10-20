---
title: "Related galleries in a Resource"
description: "Use the Post gallery recipe in native Filament Resource create and edit pages, with explicit SQL mapping and commit-aware upload acceptance."
icon: "database"
---

Use this alternative when your application already has a **Filament Resource**.
Keep its existing pages and authorization; merge the relevant methods below rather
than replacing your Resource. This is application code, not an automatic
`relationship()` method on the disk uploader.

First complete [steps 1–5 of the Post gallery recipe](/docs/file-upload-gallery-recipe#1-create-the-tables):
models, policy, filename generator, `GalleryRows` and `GalleryFields`. Skip its
custom `PostGallery` page and Blade view. Both integrations use exactly the same
field configuration and map originals to related SQL rows.

The [related gallery adapter](/docs/media-os-relations) can replace the recipe's
`GalleryRows` implementation when you want package-owned row synchronization and
stale-edit detection. Preserve the native page transaction and acceptance hooks
described here.

## What happens on save?

1. Filament validates the form and stores temporary uploads.
2. The page writes the Post's permitted attributes and related gallery rows inside
   its database transaction. Gallery fields are **not** assigned to Post attributes.
3. Native `RecordSaved` handling checks the saved references and accepts staged
   uploads after commit. Unlike a custom save page, these Resource pages do not
   need a manual `completeMediaUploadsAfterSave()` call.
4. Editing hydrates each gallery from its own collection and disk. Query scoping
   and the Post policy prevent opening another owner's record.

`$hasDatabaseTransactions = true` is intentional on both pages. The mapper rejects
writes outside a transaction. This does not turn SQL and disk/S3 into one atomic
transaction. Keep the original recipe's reference-aware retention policy and
test your queue/encoder settings separately.

## 1. Add the fields to the Resource

The standalone example includes a minimal table. An existing Resource can keep its
table, routes, actions and other attributes. Preserve every application-specific
policy and tenant scope when merging the example's owner condition.

{/* recipe-source: resources/recipes/post-gallery/app/Filament/Resources/PostResource.php */}
```php app/Filament/Resources/PostResource.php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Models\Post;
use App\Support\GalleryFields;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Alternative to PostGallery: merge these fields/pages into an existing Resource. */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
            GalleryFields::make('gallery_main', 'main')->label('Main photos'),
            GalleryFields::make('gallery_interior', 'interior')->label('Interior photos'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->searchable()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
```
{/* /recipe-source */}

## 2. Save on create

Only `title` is mass assigned. The authenticated owner comes from the server;
submitted `user_id` or `post_id` values are not accepted as authority.

{/* recipe-source: resources/recipes/post-gallery/app/Filament/Resources/PostResource/Pages/CreatePost.php */}
```php app/Filament/Resources/PostResource/Pages/CreatePost.php
<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Post;
use App\Support\GalleryRows;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Post::class);
        $post = new Post(['title' => $data['title']]);
        $post->user_id = Auth::id();
        $post->save();

        foreach (['main', 'interior'] as $collection) {
            app(GalleryRows::class)->sync($post, $collection,
                array_values($data['gallery_'.$collection] ?? []),
                $data['gallery_'.$collection.'__names'] ?? []);
        }

        // Native RecordSaved accepts referenced uploads after the transaction commits.
        return $post;
    }
}
```
{/* /recipe-source */}

## 3. Restore and save on edit

The edit page exposes only the title and the gallery state needed by its form.
It reloads and locks the Post, then checks the policy again before updating it.
If you already override these methods, merge this mapping with your existing
behavior. Other domain attributes still need their own explicit validated mapping.

{/* recipe-source: resources/recipes/post-gallery/app/Filament/Resources/PostResource/Pages/EditPost.php */}
```php app/Filament/Resources/PostResource/Pages/EditPost.php
<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Post;
use App\Support\GalleryRows;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $post = $this->getRecord();
        Gate::authorize('update', $post);
        // Expose only the attributes needed by this form, plus scoped related files.
        $state = ['title' => $post->title];
        foreach (['main', 'interior'] as $collection) {
            $items = app(GalleryRows::class)->hydrate($post, $collection);
            $state['gallery_'.$collection] = $items['paths'];
            $state['gallery_'.$collection.'__names'] = $items['names'];
        }

        return $state;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $post = Post::query()->lockForUpdate()->findOrFail($record->getKey());
        Gate::authorize('update', $post);
        $post->update(['title' => $data['title']]);
        foreach (['main', 'interior'] as $collection) {
            app(GalleryRows::class)->sync($post, $collection,
                array_values($data['gallery_'.$collection] ?? []),
                $data['gallery_'.$collection.'__names'] ?? []);
        }

        return $post;
    }
}
```
{/* /recipe-source */}

## 4. Keep the native list page

{/* recipe-source: resources/recipes/post-gallery/app/Filament/Resources/PostResource/Pages/ListPosts.php */}
```php app/Filament/Resources/PostResource/Pages/ListPosts.php
<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```
{/* /recipe-source */}

Register `PostResource::class` in the application's panel if it is not already
discovered. Do not register both this example and a second Resource for the same
route. The regular Post policy from the base recipe supplies `viewAny`, `create`
and owner-restricted `update`; no delete action is offered here.

## Verify your integration

Create a Post with files in both galleries, save, reload its edit URL and change
its title and gallery order. Check that the related row IDs stay stable and files
remain in the correct collection. Try another owner's record and a forged stored
path, then force a save failure and retry. An upload finishing is not proof that
the record committed.

The package tests execute these actual native Resource create/edit pages against
synthetic SQLite records and a local fake disk. They check save/reopen/edit,
rollback, ownership scope and accepted upload leases. This is separate from the
recording of the custom-page variant. Adapt and test any additional save actions
or relationship behavior in your own Resource.

For Spatie Media Library, use its native collection persistence instead of this
mapper; see [the Spatie alternative](/docs/file-upload-gallery-recipe#9-alternative-use-spatie-collections).
