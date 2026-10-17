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
