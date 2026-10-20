<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageBundle;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageConversion;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\GenerateImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaCollection;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaConversionColumns;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationColumns;
use Bjanczak\FilamentFlexFields\Support\Media\Relations\MediaRelationMetadata;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaConversion;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaEnvironment;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaImage;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaPost;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    RelationMediaEnvironment::configure();
    Schema::create('relation_media_conversions', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('image_id')->constrained('relation_media_images')->cascadeOnDelete();
        $table->string('name', 64);
        $table->string('disk', 128);
        $table->string('path', 512);
        $table->string('mime_type', 128);
        $table->unsignedBigInteger('bytes');
        $table->string('sha256', 64);
        $table->string('operation_id');
        $table->unsignedInteger('width');
        $table->unsignedInteger('height');
        $table->timestamps();
        $table->unique(['image_id', 'name']);
    });
    config()->set('filament-flex-fields.media_capture.require_virus_scan', false);
});

function relationImageBundle(): ImageBundle
{
    $image = imagecreatetruecolor(32, 16);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();

    return app(ImageMedia::class)->store(MediaSource::bytes($bytes, 'Synthetic.png'),
        new MediaWrite(disk: 'relation-private', directory: 'originals', policy: new MediaPolicy),
        [new ImageConversion('thumb', width: 8), new ImageConversion('large', width: 16)]);
}

function relationOriginalRow(ImageBundle $bundle): RelationMediaImage
{
    return RelationMediaPost::create()->galleries()->create(['disk' => $bundle->original->disk,
        'path' => $bundle->original->path, 'collection' => 'main', 'sort_order' => 0, 'original_name' => 'Synthetic.png']);
}

function relationProjection(ImageBundle $bundle): HasManyMediaConversions
{
    return new HasManyMediaConversions('conversions',
        authorize: fn (Model $row): bool => RelationMediaPost::find($row->post_id)?->owner_id === 1,
        authority: function (Model $row, MediaReceipt $receipt, ?string $name) use ($bundle): MediaWrite {
            $expected = $name === null ? $bundle->original : ($bundle->variants[$name] ?? null);
            if ($expected !== $receipt) {
                throw new AuthorizationException('Receipt is not part of this server-owned plan.');
            }

            return $name === null ? $bundle->intent : $bundle->intents[$name];
        });
}

it('projects encoded images, retries without new rows and detaches SQL without deleting bytes', function (): void {
    $bundle = relationImageBundle();
    $row = relationOriginalRow($bundle);
    $projection = relationProjection($bundle);
    $revision = $projection->revision($row);
    DB::transaction(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision));
    $ids = $row->conversions()->orderBy('name')->pluck('id')->all();
    DB::transaction(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision));
    expect($row->conversions()->orderBy('name')->pluck('id')->all())->toBe($ids);
    foreach ($row->conversions()->get() as $conversion) {
        $receipt = $bundle->variant($conversion->name);
        expect($conversion->sha256)->toBe($receipt->file->sha256)
            ->and((int) $conversion->width)->toBe($receipt->file->width)
            ->and((int) $conversion->bytes)->toBe(strlen(Storage::disk($receipt->disk)->get($receipt->path)));
    }
    expect(fn () => DB::transaction(fn () => $projection->sync($row, $bundle->original, [], $revision)))->toThrow(MediaFailure::class);
    $row->load('conversions');
    DB::transaction(fn () => $projection->sync($row, $bundle->original, [], $projection->revision($row)));
    expect($row->conversions()->count())->toBe(0)->and($row->relationLoaded('conversions'))->toBeFalse();
    foreach ([$bundle->original, ...array_values($bundle->variants)] as $receipt) {
        Storage::disk($receipt->disk)->assertExists($receipt->path);
    }
});

it('reauthorizes ownership and rejects a changed original or foreign conversion plan', function (string $case): void {
    $bundle = relationImageBundle();
    $row = relationOriginalRow($bundle);
    $projection = relationProjection($bundle);
    $revision = $projection->revision($row);
    $variants = $bundle->variants;
    if ($case === 'owner') {
        RelationMediaPost::find($row->post_id)->update(['owner_id' => 2]);
    } elseif ($case === 'source') {
        $row->update(['path' => 'originals/replaced.png']);
    } else {
        $variants['thumb'] = relationImageBundle()->variant('thumb');
    }
    expect(fn () => DB::transaction(fn () => $projection->sync($row, $bundle->original, $variants, $revision)))
        ->toThrow($case === 'source' ? MediaFailure::class : AuthorizationException::class)
        ->and($row->conversions()->count())->toBe(0);
})->with(['owner', 'source', 'foreign-plan']);

it('verifies all conversions and rolls back a model veto inside the caller transaction', function (string $case): void {
    $bundle = relationImageBundle();
    $row = relationOriginalRow($bundle);
    $projection = relationProjection($bundle);
    $revision = $projection->revision($row);
    if ($case === 'integrity') {
        Storage::disk('relation-private')->put($bundle->variant('large')->path, 'Changed bytes');
    } else {
        RelationMediaConversion::saving(fn (Model $row): ?bool => $row->name === 'large' ? false : null);
    }
    try {
        DB::transaction(function () use ($projection, $row, $bundle, $revision): void {
            expect(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision))->toThrow(Exception::class);
            expect($row->conversions()->count())->toBe(0);
            $row->update(['original_name' => 'Outer survived.png']);
        });
    } finally {
        RelationMediaConversion::flushEventListeners();
    }
    expect($row->fresh()->original_name)->toBe('Outer survived.png');
    Storage::disk('relation-private')->assertExists($bundle->original->path);
})->with(['integrity', 'veto']);

it('requires a transaction and refuses invalid columns and model keys', function (): void {
    $bundle = relationImageBundle();
    $row = relationOriginalRow($bundle);
    $projection = relationProjection($bundle);
    expect(fn () => $projection->sync($row, $bundle->original, [], $projection->revision($row)))->toThrow(LogicException::class)
        ->and(fn () => new MediaConversionColumns(path: 'disk'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MediaConversionColumns(path: 'bad.path'))->toThrow(InvalidArgumentException::class);
    $unsafe = new HasManyMediaConversions('conversions', fn () => true, fn () => $bundle->intent, columns: new MediaConversionColumns(path: 'image_id'));
    expect(fn () => $unsafe->revision($row))->toThrow(LogicException::class);
});

it('maps metadata, preserves omitted entries, clears explicitly and detects stale metadata writes', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main', fn () => 'posts/'.$post->id.'/main',
        fn () => true, fn () => true, columns: new MediaRelationColumns(metadata: 'metadata'));
    $first = DB::transaction(fn () => $collection->sync($post, [$path], [], $collection->read($post)->revision,
        [$path => ['caption' => ['en' => 'Deck'], 'tags' => ['boat', 'sea']]]));
    expect($first->metadata[$path]['caption']['en'])->toBe('Deck');
    $same = DB::transaction(fn () => $collection->sync($post, [$path], [], $first->revision));
    expect($same->revision)->toBe($first->revision);
    $post->galleries()->first()->update(['metadata' => ['caption' => 'Changed elsewhere']]);
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $first->revision, [$path => []])))
        ->toThrow(MediaFailure::class);
    $latest = $collection->read($post);
    $empty = DB::transaction(fn () => $collection->sync($post, [$path], [], $latest->revision, [$path => []]));
    expect($empty->metadata[$path])->toBe([]);
});

it('rejects executable, recursive, oversized and non-JSON metadata', function (mixed $value): void {
    expect(fn () => MediaRelationMetadata::normalize($value))->toThrow(InvalidArgumentException::class);
})->with([
    'object' => fn () => new class implements JsonSerializable
    {
        public function jsonSerialize(): mixed
        {
            throw new RuntimeException('Must not execute');
        }
    },
    'list' => fn () => ['not an object'], 'infinite' => fn () => ['value' => INF],
    'bytes' => fn () => ['value' => str_repeat('a', 16385)], 'encoding' => fn () => ['value' => "bad\xff"],
    'entries' => fn () => array_fill_keys(range(1, 513), true),
    'recursive' => function (): array {
        $value = [];
        $value['self'] = &$value;

        return $value;
    },
]);

it('roundtrips numeric JSON metadata through the Eloquent array cast and retries unchanged', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main', fn () => 'posts/'.$post->id.'/main',
        fn () => true, fn () => true, columns: new MediaRelationColumns(metadata: 'metadata'));
    $metadata = [$path => ['ratio' => 1.0, 'zero' => -0.0, 'fraction' => 1.25,
        'nested' => [2.0, 3.5], 'integer' => PHP_INT_MAX]];
    $initial = $collection->read($post);
    $saved = DB::transaction(fn () => $collection->sync($post, [$path], [], $initial->revision, $metadata));
    $reloaded = $collection->read($post);
    $retried = DB::transaction(fn () => $collection->sync($post, [$path], [], $initial->revision, $metadata));

    expect($saved->metadata[$path])->toBe(['fraction' => 1.25, 'integer' => PHP_INT_MAX, 'nested' => [2, 3.5], 'ratio' => 1, 'zero' => 0])
        ->and($reloaded->metadata)->toBe($saved->metadata)
        ->and($retried->revision)->toBe($saved->revision)
        ->and($post->galleries()->count())->toBe(1);
});

it('rolls back projection hooks that redirect a conversion and rejects duplicate objects under different names', function (): void {
    $bundle = relationImageBundle();
    $row = relationOriginalRow($bundle);
    $projection = relationProjection($bundle);
    $revision = $projection->revision($row);
    RelationMediaConversion::saving(function (Model $conversion): void {
        $conversion->path = 'unrelated/path.webp';
    });
    try {
        expect(fn () => DB::transaction(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision)))
            ->toThrow(MediaFailure::class)->and($row->conversions()->count())->toBe(0);
    } finally {
        RelationMediaConversion::flushEventListeners();
    }
    $duplicates = ['thumb' => $bundle->variant('thumb'), 'large' => $bundle->variant('thumb')];
    expect(fn () => DB::transaction(fn () => $projection->sync($row, $bundle->original, $duplicates, $revision)))
        ->toThrow(MediaFailure::class)->and($row->conversions()->count())->toBe(0);
});

it('rejects foreign metadata keys and unconfigured metadata without changing the originals', function (): void {
    $post = RelationMediaPost::create();
    $path = RelationMediaEnvironment::file($post);
    $collection = RelationMediaEnvironment::collection(admitted: [$path]);
    $revision = $collection->read($post)->revision;
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $revision, [$path => ['caption' => 'Must configure mapping']])))
        ->toThrow(MediaFailure::class)->and($post->galleries()->count())->toBe(0);
    $collection = new HasManyMediaCollection('galleries', 'relation-private', 'main', fn () => 'posts/'.$post->id.'/main',
        fn () => true, fn () => true, columns: new MediaRelationColumns(metadata: 'metadata'));
    expect(fn () => DB::transaction(fn () => $collection->sync($post, [$path], [], $collection->read($post)->revision, ['foreign/path' => []])))
        ->toThrow(MediaFailure::class)->and($post->galleries()->count())->toBe(0);
});

it('projects a real ready queued snapshot after processing and leaves the index intact while pending', function (): void {
    Storage::fake('relation-journals');
    config()->set('filament-flex-fields.media_capture.images.queue.journal_disk', 'relation-journals');
    config()->set('queue.default', 'relation-worker');
    config()->set('queue.connections.relation-worker', ['driver' => 'database', 'retry_after' => 120]);
    Bus::fake([GenerateImageConversions::class]);
    $image = imagecreatetruecolor(16, 8);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    $authority = new MediaWrite(disk: 'relation-private', policy: new MediaPolicy);
    $conversions = app(QueuedImageConversions::class);
    $queued = $conversions->store(MediaSource::bytes($bytes, 'Queued.png'), $authority, [new ImageConversion('thumb', width: 8)]);
    $row = relationOriginalRow(new ImageBundle($queued->original, $authority, [], []));
    $pending = $conversions->snapshot($queued->id, $queued->original, $authority);
    expect($pending['status'])->toBe('queued')->and($row->conversions()->count())->toBe(0);
    $conversions->process($queued->id);
    $ready = $conversions->snapshot($queued->id, $queued->original, $authority);
    expect($ready['status'])->toBe('ready');
    $bundle = new ImageBundle($queued->original, $authority, $ready['variants'], ['thumb' => $authority]);
    $projection = relationProjection($bundle);
    $revision = $projection->revision($row);
    DB::transaction(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision));
    DB::transaction(fn () => $projection->sync($row, $bundle->original, $bundle->variants, $revision));
    expect($row->conversions()->count())->toBe(1)->and($row->conversions()->first()->name)->toBe('thumb');
});
