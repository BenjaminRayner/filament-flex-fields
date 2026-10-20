<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaCollection;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationColumns;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationSnapshot;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaEnvironment;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaImage;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaPost;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(fn () => RelationMediaEnvironment::configure());

it('saves, reopens, reorders and detaches two scoped collections without JSON attributes or deleting bytes', function (): void {
    $post = RelationMediaPost::create();
    $a = RelationMediaEnvironment::file($post);
    $b = RelationMediaEnvironment::file($post, 'b.txt');
    $c = RelationMediaEnvironment::file($post, 'c.txt', 'interior');
    $main = RelationMediaEnvironment::collection(admitted: [$a, $b]);
    $interior = RelationMediaEnvironment::collection('interior', [$c]);
    $initial = $main->read($post);
    $otherInitial = $interior->read($post);
    DB::transaction(function () use ($post, $main, $interior, $initial, $otherInitial, $a, $b, $c): void {
        $main->sync($post, [$a, $b], [$a => 'First original.txt', $b => 'Second original.txt'], $initial->revision);
        $interior->sync($post, [$c], [], $otherInitial->revision);
    });
    $saved = $main->read($post->fresh());
    $ids = $post->galleries()->orderBy('id')->pluck('id')->all();
    expect($saved->paths)->toBe([$a, $b])->and($saved->names[$a])->toBe('First original.txt')
        ->and($saved->revision)->not->toBe($initial->revision)
        ->and($main->references($post, $a))->toBeTrue()
        ->and($post->fresh()->getAttributes())->not->toHaveKey('photos');
    $post->load('galleries');
    $reordered = DB::transaction(fn () => $main->sync($post, [$b, $a], [], $saved->revision));
    expect($reordered->paths)->toBe([$b, $a])->and($reordered->names[$a])->toBe('First original.txt')
        ->and($post->relationLoaded('galleries'))->toBeFalse()
        ->and($post->galleries()->orderBy('id')->pluck('id')->all())->toBe($ids);
    DB::transaction(fn () => $main->sync($post, [], [], $reordered->revision));
    expect($main->read($post)->paths)->toBe([])->and($interior->read($post)->paths)->toBe([$c]);
    foreach ([$a, $b, $c] as $path) {
        Storage::disk('relation-private')->assertExists($path);
    }
});

it('rejects stale edits but safely accepts a lost-response replay that already matches committed state', function (): void {
    $post = RelationMediaPost::create();
    $a = RelationMediaEnvironment::file($post);
    $b = RelationMediaEnvironment::file($post, 'b.txt');
    $collection = RelationMediaEnvironment::collection(admitted: [$a, $b]);
    $tabOne = $collection->read($post);
    $tabTwo = $collection->read($post);
    $saved = DB::transaction(fn () => $collection->sync($post, [$a], [], $tabOne->revision));
    try {
        DB::transaction(fn () => $collection->sync($post, [$b], [], $tabTwo->revision));
        $this->fail('A stale form must not replace committed references.');
    } catch (MediaFailure $error) {
        expect($error->reason)->toBe('relation_conflict');
    }
    $replayed = DB::transaction(fn () => $collection->sync($post, [$a], [], $tabOne->revision));
    expect($replayed->revision)->toBe($saved->revision)->and($post->galleries()->count())->toBe(1)
        ->and($collection->read($post)->paths)->toBe([$a]);
});

it('rolls back rows with the parent save and retries without duplicate references', function (): void {
    $post = RelationMediaPost::create(['title' => 'Before']);
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $before = $collection->read($post);
    expect(fn () => DB::transaction(function () use ($post, $collection, $path, $before): void {
        $post->update(['title' => 'Rolled back']);
        $collection->sync($post, [$path], [], $before->revision);
        throw new RuntimeException('Controlled save failure');
    }))->toThrow(RuntimeException::class);
    expect($post->fresh()->title)->toBe('Before')->and($collection->read($post)->revision)->toBe($before->revision);
    DB::transaction(fn () => $collection->sync($post, [$path], [], $before->revision));
    DB::transaction(fn () => $collection->sync($post, [$path], [], $before->revision));
    expect($post->galleries()->count())->toBe(1);
    Storage::disk('relation-private')->assertExists($path);
});

it('reauthorizes a freshly loaded owner and rejects changes to ownership after a form was read', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $before = $collection->read($post);
    DB::table('relation_media_posts')->where('id', $post->getKey())->update(['owner_id' => 2]);
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $before->revision)))->toThrow(AuthorizationException::class)
        ->and(fn () => $collection->read($post))->toThrow(AuthorizationException::class)
        ->and(fn () => $collection->references($post, $path))->toThrow(AuthorizationException::class)
        ->and($post->galleries()->count())->toBe(0);
    Storage::disk('relation-private')->assertExists($path);
});

it('does not treat a directory or file existence as admission for a new upload', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection();
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $collection->read($post)->revision)))
        ->toThrow(MediaFailure::class)->and($post->galleries()->count())->toBe(0);
});

it('rejects foreign collections, foreign parents, unsafe paths, missing objects and invalid state without changing rows', function (string $case): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $submitted = match ($case) {
        'foreign-parent' => ['posts/999/main/a.txt'], 'foreign-collection' => ['posts/'.$post->getKey().'/interior/a.txt'],
        'traversal' => ['posts/'.$post->getKey().'/main/../a.txt'], 'absolute' => ['/private/a.txt'],
        'url' => ['https://private.test/a.txt'], 'backslash' => ['posts\\a.txt'],
        'empty' => [''], 'non-string' => [new stdClass], 'duplicate' => [$path, $path],
        'missing' => ['posts/'.$post->getKey().'/main/missing.txt'], 'too-long' => ['posts/'.$post->getKey().'/main/'.str_repeat('a', 513)],
        'not-list' => ['arbitrary' => $path], default => throw new LogicException('Unknown test case'),
    };
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $before = $collection->read($post);
    expect(fn () => DB::transaction(fn () => $collection->sync($post, $submitted, [], $before->revision)))
        ->toThrow(MediaFailure::class)->and($collection->read($post)->revision)->toBe($before->revision);
})->with(['foreign-parent', 'foreign-collection', 'traversal', 'absolute', 'url', 'backslash', 'empty', 'non-string', 'duplicate', 'missing', 'too-long', 'not-list']);

it('prevents attachment of an already claimed path even when an application admission callback allows it', function (): void {
    $first = RelationMediaPost::create();
    $second = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($first);
    $row = $second->galleries()->create(['disk' => 'relation-private', 'path' => $path, 'collection' => 'interior', 'original_name' => 'a.txt', 'sort_order' => 0]);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    expect(fn () => DB::transaction(fn () => $collection->sync($first, [$path], [], $collection->read($first)->revision)))
        ->toThrow(MediaFailure::class)->and($first->galleries()->count())->toBe(0)
        ->and($row->fresh())->not->toBeNull();
});

it('refuses row or byte mutation outside a transaction or before the parent exists', function (): void {
    $collection = RelationMediaEnvironment::collection();
    $post = RelationMediaPost::create();
    $state = $collection->read($post);
    expect(fn () => $collection->sync($post, [], [], $state->revision))->toThrow(LogicException::class)
        ->and(fn () => $collection->read(new RelationMediaPost))->toThrow(LogicException::class)
        ->and(fn () => DB::transaction(fn () => $collection->sync(new RelationMediaPost, [], [], $state->revision)))->toThrow(LogicException::class);
});

it('rejects malformed revisions and invalid names without applying a partial sync', function (string $case): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $before = $collection->read($post);
    $name = match ($case) {
        'newline' => "private\nname", 'invalid-utf8' => "bad\xff", 'object' => new stdClass,
        'long' => str_repeat('a', 1025), default => 'a.txt',
    };
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [$path => $name], $case === 'revision' ? 'bad' : $before->revision)))
        ->toThrow(MediaFailure::class)->and($post->galleries()->count())->toBe(0);
})->with(['newline', 'invalid-utf8', 'object', 'long', 'revision']);

it('keeps model keys and timestamps out of configurable mappings and rejects unsupported relations', function (string $case): void {
    $post = RelationMediaPost::create();
    $collection = new HasManyMediaCollection(relation: match ($case) {
        'one' => 'oneImage', 'limited' => 'limitedImages', default => 'galleries'
    },
        disk: 'relation-private', collection: 'main', directory: fn () => 'posts/1/main', authorize: fn () => true, admit: fn () => true,
        columns: new MediaRelationColumns(path: match ($case) {
            'key' => 'id', 'foreign' => 'post_id', 'timestamp' => 'created_at', default => 'path'
        }));
    expect(fn () => $collection->read($post))->toThrow(LogicException::class);
})->with(['one', 'limited', 'key', 'foreign', 'timestamp']);

it('uses nested rollback when a model event refuses the second row after the first was inserted', function (): void {
    $post = RelationMediaPost::create();
    $a = RelationMediaEnvironment::file($post);
    $b = RelationMediaEnvironment::file($post, 'b.txt');
    $collection = RelationMediaEnvironment::collection(admitted: [$a, $b]);
    $revision = $collection->read($post)->revision;
    RelationMediaImage::saving(fn (RelationMediaImage $row): ?bool => $row->path === $b ? false : null);
    try {
        DB::transaction(function () use ($post, $collection, $a, $b, $revision): void {
            try {
                $collection->sync($post, [$a, $b], [], $revision);
                $this->fail('The model-event rejection must abort relation writes.');
            } catch (MediaFailure $error) {
                expect($error->reason)->toBe('relation_write_rejected');
            }
            expect($post->galleries()->count())->toBe(0);
            $post->update(['title' => 'Outer transaction survived']);
        });
    } finally {
        RelationMediaImage::flushEventListeners();
    }
    expect($post->fresh()->title)->toBe('Outer transaction survived')->and($post->galleries()->count())->toBe(0);
});

it('rolls back a rejected detach and preserves the physical original', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $state = DB::transaction(fn () => $collection->sync($post, [$path], [], $collection->read($post)->revision));
    RelationMediaImage::deleting(fn () => false);
    try {
        expect(fn () => DB::transaction(fn () => $collection->sync($post, [], [], $state->revision)))->toThrow(MediaFailure::class);
    } finally {
        RelationMediaImage::flushEventListeners();
    }
    expect($post->galleries()->count())->toBe(1);
    Storage::disk('relation-private')->assertExists($path);
});

it('supports explicit alternative column names on an existing related model', function (): void {
    Schema::table('relation_media_images', function (Blueprint $table): void {
        $table->renameColumn('disk', 'storage');
        $table->renameColumn('path', 'object_key');
        $table->renameColumn('collection', 'bucket_group');
        $table->renameColumn('original_name', 'client_name');
        $table->renameColumn('sort_order', 'position');
    });
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main',
        directory: fn (Model $post) => 'posts/'.$post->getKey().'/main', authorize: fn () => true, admit: fn () => true,
        columns: new MediaRelationColumns('storage', 'object_key', 'bucket_group', 'client_name', 'position'));
    $state = DB::transaction(fn () => $collection->sync($post, [$path], [$path => 'Renamed.txt'], $collection->read($post)->revision));
    expect($state->paths)->toBe([$path])->and($state->names[$path])->toBe('Renamed.txt')
        ->and($post->galleries()->first()->object_key)->toBe($path);
});

it('enforces row budgets before returning a truncated state that could detach unseen rows', function (): void {
    $post = RelationMediaPost::create();
    foreach (['a.txt', 'b.txt'] as $i => $name) {
        $path = RelationMediaEnvironment::file($post, $name);
        $post->galleries()->create(['disk' => 'relation-private', 'collection' => 'main', 'path' => $path, 'original_name' => $name, 'sort_order' => $i]);
    }
    $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main', directory: fn () => 'posts/1/main', authorize: fn () => true, admit: fn () => true, maxItems: 1);
    expect(fn () => $collection->read($post))->toThrow(MediaFailure::class)
        ->and(fn () => DB::transaction(fn () => $collection->sync($post, [], [], str_repeat('a', 64))))->toThrow(MediaFailure::class)
        ->and($post->galleries()->count())->toBe(2);
});

it('keeps column identifiers and options explicit and bounded', function (array $arguments): void {
    expect(fn () => new MediaRelationColumns(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([[['path' => 'disk']], [['path' => 'DISK']], [['path' => 'path; DROP TABLE posts']], [['order' => 'table.position']], [['originalName' => '']], [['path' => str_repeat('a', 64)]]]);

it('rejects invalid adapter options before resolving a relationship or disk', function (string $option, mixed $value): void {
    $arguments = ['relation' => 'galleries', 'disk' => 'relation-private', 'collection' => 'main',
        'directory' => fn () => 'posts/1/main', 'authorize' => fn () => true, 'admit' => fn () => true];
    $arguments[$option] = $value;
    expect(fn () => new HasManyMediaCollection(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    ['relation', 'gallery.items'], ['disk', ''], ['disk', "bad\nname"], ['collection', '../other'],
    ['maxItems', 0], ['maxItems', 1001],
]);

it('detects mapped metadata changes and binds revisions to the parent and collection', function (): void {
    $post = RelationMediaPost::create();
    $other = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $before = $collection->read($post);
    expect($before->revision)->not->toBe($collection->read($other)->revision)
        ->and($before->revision)->not->toBe(RelationMediaEnvironment::collection('interior')->read($post)->revision);
    $saved = DB::transaction(fn () => $collection->sync($post, [$path], [], $before->revision));
    $post->galleries()->first()->update(['original_name' => 'Changed elsewhere.txt']);
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [], [], $saved->revision)))->toThrow(MediaFailure::class)
        ->and($post->galleries()->first()->original_name)->toBe('Changed elsewhere.txt');
});

it('rolls back model hooks that redirect a mapped reference unexpectedly', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $revision = $collection->read($post)->revision;
    RelationMediaImage::saving(function (RelationMediaImage $row): void {
        $row->collection = 'interior';
    });
    try {
        expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $revision)))->toThrow(MediaFailure::class)
            ->and($post->galleries()->count())->toBe(0);
    } finally {
        RelationMediaImage::flushEventListeners();
    }
    Storage::disk('relation-private')->assertExists($path);
});

it('does not manufacture revisions from malformed or duplicate database rows', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $post->galleries()->create(['disk' => 'relation-private', 'collection' => 'main', 'path' => $path, 'original_name' => 'a.txt', 'sort_order' => 0]);
    Schema::table('relation_media_images', fn (Blueprint $table) => $table->dropUnique(['disk', 'path']));
    $post->galleries()->create(['disk' => 'relation-private', 'collection' => 'main', 'path' => $path, 'original_name' => 'b.txt', 'sort_order' => 1]);
    expect(fn () => RelationMediaEnvironment::collection()->read($post))->toThrow(MediaFailure::class);
});

it('detaches mutable caller references from a read snapshot', function (): void {
    $path = 'posts/1/main/a.txt';
    $name = 'Original.txt';
    $snapshot = new MediaRelationSnapshot([&$path], ['posts/1/main/a.txt' => &$name], str_repeat('a', 64));
    $path = 'foreign-path';
    $name = 'Other.txt';
    expect($snapshot->paths)->toBe(['posts/1/main/a.txt'])->and($snapshot->names['posts/1/main/a.txt'])->toBe('Original.txt');
});

it('rejects soft deletes, cross-connection references and case variants of protected keys', function (string $case): void {
    config()->set('database.connections.relation-other', ['driver' => 'sqlite', 'database' => ':memory:']);
    $post = RelationMediaPost::create();
    $collection = new HasManyMediaCollection(relation: $case === 'key-case' ? 'galleries' : $case,
        disk: 'relation-private', collection: 'main', directory: fn () => 'posts/1/main', authorize: fn () => true, admit: fn () => true,
        columns: new MediaRelationColumns(path: $case === 'key-case' ? 'ID' : 'path'));
    expect(fn () => $collection->read($post))->toThrow(LogicException::class);
})->with(['softImages', 'externalImages', 'key-case']);

it('supports a new parent created after an upload without requiring a predicted parent ID', function (): void {
    $accepted = 0;
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('photos')->key('photos')->multiple()->appendFiles()
        ->disk('relation-private')->directory('actors/1/main')->visibility('private')->storeFileNamesIn('photo_names')
        ->mediaReferenceUsing(fn (RelationMediaPost $record, string $identifier): bool => $record->galleries()->where('path', $identifier)->exists())
        ->onUploadEvent(UploadEventType::Accepted, function () use (&$accepted): void {
            $accepted++;
        })];
    $form = Livewire::test(TestableTranslatableForm::class);
    $form->upload('data.photos.'.Str::uuid(), [UploadedFile::fake()->create('New parent.txt', 1, 'text/plain')]);
    $schema = $form->instance()->getSchema('form');
    $state = $schema->getState();
    expect(RelationMediaPost::count())->toBe(0)->and($accepted)->toBe(0);
    $post = DB::transaction(function () use ($state, $schema, &$accepted): RelationMediaPost {
        $post = RelationMediaPost::create();
        $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main',
            directory: fn (Model $post): string => 'actors/'.$post->owner_id.'/main',
            authorize: fn (Model $post): bool => $post->owner_id === 1,
            admit: fn (Model $post, string $path): bool => in_array($path, $state['photos'], true));
        $collection->sync($post, array_values($state['photos']), $state['photo_names'], $collection->read($post)->revision);
        $schema->model($post)->getComponent('photos')->completeMediaUploadsAfterSave($post);
        expect($accepted)->toBe(0);

        return $post;
    });
    expect($accepted)->toBe(1)->and($post->galleries()->count())->toBe(1)
        ->and($post->galleries()->first()->path)->toStartWith('actors/1/main/');
});

it('integrates the real upload field with stage, rollback, commit, acceptance and a fresh reopened form', function (): void {
    $post = RelationMediaPost::create();
    $collection = RelationMediaEnvironment::collection();
    $initial = $collection->read($post);
    $accepted = 0;
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('photos')->key('photos')->multiple()->appendFiles()->reorderable()
        ->disk('relation-private')->directory('posts/'.$post->getKey().'/main')->visibility('private')->storeFileNamesIn('photo_names')
        ->preventFilePathTampering(allowFilePathUsing: fn (?RelationMediaPost $record, string $file): bool => $record?->exists && $collection->references($record, $file))
        ->mediaReferenceUsing(fn (RelationMediaPost $record, string $identifier): bool => $collection->references($record, $identifier))
        ->deleteFileOnRemove(false)->deleteReplacedFiles(false)->pruneOrphanedOnSave(false)
        ->onUploadEvent(UploadEventType::Accepted, function () use (&$accepted): void {
            $accepted++;
        })];
    $form = Livewire::test(TestableTranslatableForm::class, ['record' => $post]);
    $form->upload('data.photos.'.Str::uuid(), [UploadedFile::fake()->create('Original.txt', 1, 'text/plain')]);
    $schema = $form->instance()->getSchema('form');
    $state = $schema->getState();
    $collection = RelationMediaEnvironment::collection(admitted: array_values($state['photos']));
    $save = function () use ($post, $schema, $state, $collection, $initial): void {
        $collection->sync($post, array_values($state['photos']), $state['photo_names'], $initial->revision);
        $schema->getComponent('photos')->completeMediaUploadsAfterSave($post);
    };
    expect(fn () => DB::transaction(function () use ($save): void {
        $save();
        throw new RuntimeException('Rejected save');
    }))->toThrow(RuntimeException::class);
    expect($accepted)->toBe(0)->and($post->galleries()->count())->toBe(0);
    DB::transaction($save);
    expect($accepted)->toBe(1)->and($post->galleries()->count())->toBe(1);
    DB::transaction($save);
    expect($accepted)->toBe(1)->and($post->galleries()->count())->toBe(1);
    $saved = $collection->read($post->fresh());
    $reopened = Livewire::test(TestableTranslatableForm::class, ['record' => $post->fresh()])
        ->fillForm(['photos' => $saved->paths, 'photo_names' => $saved->names]);
    expect($reopened->instance()->getSchema('form')->getComponent('photos')->getUploadedFiles())->toHaveCount(1);
});
