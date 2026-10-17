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
