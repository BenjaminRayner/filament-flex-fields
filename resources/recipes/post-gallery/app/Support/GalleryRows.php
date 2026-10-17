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
