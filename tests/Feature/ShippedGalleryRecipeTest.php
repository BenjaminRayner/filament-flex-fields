<?php

declare(strict_types=1);

use App\Filament\Pages\PostGallery;
use App\Models\Post;
use App\Support\GalleryRows;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $recipe = dirname(__DIR__, 2).'/resources/recipes/post-gallery/';
    foreach (['app/Models/Post.php', 'app/Models/Gallery.php', 'app/Support/GalleryRows.php',
        'app/Support/GalleryFileName.php', 'app/Support/GalleryFields.php', 'app/Policies/PostPolicy.php', 'app/Filament/Pages/PostGallery.php'] as $file) {
        require_once $recipe.$file;
    }
    Schema::create('users', fn (Blueprint $table) => $table->id());
    DB::table('users')->insert(['id' => 1]);
    (require $recipe.'database/migrations/2026_10_17_000001_create_posts_and_galleries.php')->up();
    Storage::fake('public');
    $user = new User;
    $user->forceFill(['id' => 1]);
    $user->exists = true;
    $this->actingAs($user);
});

it('runs the shipped page fields, mapper and migration through save, commit, reopen and reorder', function (): void {
    $accepted = [];
    $page = new PostGallery;
    $method = new ReflectionMethod($page, 'galleryField');
    TestableTranslatableForm::$formSchema = [];
    foreach (['main', 'interior'] as $collection) {
        TestableTranslatableForm::$formSchema[] = $method->invoke($page, 'gallery_'.$collection, $collection)
            ->onUploadEvent(UploadEventType::Accepted, function () use (&$accepted, $collection): void {
                $accepted[] = $collection;
            });
    }
    $form = Livewire::test(TestableTranslatableForm::class);
    foreach (['main' => 2, 'interior' => 1] as $collection => $count) {
        for ($i = 0; $i < $count; $i++) {
            $form->upload('data.gallery_'.$collection.'.'.Str::uuid(), [UploadedFile::fake()->image('deck-'.$i.'.png', 600, 400)]);
        }
    }
    $schema = $form->instance()->getSchema('form');
    $state = $schema->getState();
    expect(Schema::hasColumn('posts', 'gallery_main'))->toBeFalse()
        ->and($accepted)->toBeEmpty();
    $post = DB::transaction(function () use ($state, $schema, &$accepted): Post {
        $post = new Post(['title' => 'Original']);
        $post->user_id = 1;
        $post->save();
        $schema->model($post);
        foreach (['main', 'interior'] as $collection) {
            app(GalleryRows::class)->sync($post, $collection, $state['gallery_'.$collection], $state['gallery_'.$collection.'__names']);
            $schema->getComponent('gallery_'.$collection)->completeMediaUploadsAfterSave($post);
        }
        expect($accepted)->toBeEmpty();

        return $post;
    });
    expect($accepted)->toHaveCount(3)->and($post->galleries()->count())->toBe(3);
    $restore = [];
    foreach (['main', 'interior'] as $collection) {
        $items = app(GalleryRows::class)->hydrate($post, $collection);
        $restore['gallery_'.$collection] = $items['paths'];
        $restore['gallery_'.$collection.'__names'] = $items['names'];
    }
    $fresh = Livewire::test(TestableTranslatableForm::class, ['record' => $post])->fillForm($restore);
    foreach (['main', 'interior'] as $collection) {
        $field = $fresh->instance()->getSchema('form')->getComponent('gallery_'.$collection);
        $files = $field->getUploadedFiles();
        expect(count($files))->toBe($collection === 'main' ? 2 : 1)
            ->and($field->shouldAppendFiles())->toBeTrue();
        foreach ($restore['gallery_'.$collection] as $path) {
            Storage::disk('public')->assertExists($path);
            expect(basename($path))->toMatch('/^photo-deck-\d-[a-f0-9-]+\.png$/');
        }
    }
    $ids = $post->galleries()->orderBy('id')->pluck('id')->all();
    $reordered = array_reverse($restore['gallery_main']);
    DB::transaction(fn () => app(GalleryRows::class)->sync($post, 'main', $reordered, $restore['gallery_main__names']));
    expect($post->galleries()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(app(GalleryRows::class)->hydrate($post, 'main')['paths'])->toBe($reordered);
    DB::transaction(fn () => app(GalleryRows::class)->sync($post, 'main', [], []));
    expect($post->galleries()->where('collection', 'main')->count())->toBe(0)
        ->and($post->galleries()->where('collection', 'interior')->count())->toBe(1);
    foreach ($reordered as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('rolls back relation changes and preserves bytes when the record save fails', function (): void {
    $post = new Post(['title' => 'Before']);
    $post->user_id = 1;
    $post->save();
    $path = 'posts/1/main/original.png';
    Storage::disk('public')->put($path, 'original');
    DB::transaction(fn () => app(GalleryRows::class)->sync($post, 'main', [$path], [$path => 'Original.png']));

    expect(function () use ($post): void {
        DB::transaction(function () use ($post): void {
            $post->update(['title' => 'Must roll back']);
            app(GalleryRows::class)->sync($post, 'main', [], []);
            throw new RuntimeException('Controlled application save failure');
        });
    })->toThrow(RuntimeException::class);

    expect($post->fresh()->title)->toBe('Before')
        ->and(app(GalleryRows::class)->hydrate($post, 'main')['paths'])->toBe([$path]);
    Storage::disk('public')->assertExists($path);
});

it('accepts a staged upload only after a successful commit and allows retry after a rolled back save', function (): void {
    $accepted = 0;
    $page = new PostGallery;
    $field = (new ReflectionMethod($page, 'galleryField'))->invoke($page, 'gallery_main', 'main')
        ->onUploadEvent(UploadEventType::Accepted, function () use (&$accepted): void {
            $accepted++;
        });
    TestableTranslatableForm::$formSchema = [$field];
    $form = Livewire::test(TestableTranslatableForm::class);
    $form->upload('data.gallery_main.'.Str::uuid(), [UploadedFile::fake()->image('retry.png', 200, 120)]);
    $schema = $form->instance()->getSchema('form');
    $state = $schema->getState();
    $post = new Post(['title' => 'Existing record']);
    $post->user_id = 1;
    $post->save();
    $schema->model($post);
    $save = function () use ($post, $state, $schema): void {
        app(GalleryRows::class)->sync($post, 'main', $state['gallery_main'], $state['gallery_main__names']);
        $schema->getComponent('gallery_main')->completeMediaUploadsAfterSave($post);
    };

    expect(fn () => DB::transaction(function () use ($save): void {
        $save();
        throw new RuntimeException('Controlled failure before commit');
    }))->toThrow(RuntimeException::class);
    expect($accepted)->toBe(0)->and($post->galleries()->count())->toBe(0);
    Storage::disk('public')->assertExists($state['gallery_main'][0]);

    DB::transaction($save);
    expect($accepted)->toBe(1)->and($post->galleries()->count())->toBe(1);
    DB::transaction($save);
    expect($accepted)->toBe(1)->and($post->galleries()->count())->toBe(1);
});

it('rejects foreign paths, duplicate paths, traversal, missing files and nontransactional writes', function (): void {
    $post = new Post(['title' => 'Owner']);
    $post->user_id = 1;
    $post->save();
    $foreign = new Post(['title' => 'Other record']);
    $foreign->user_id = 1;
    $foreign->save();
    $path = 'posts/1/main/owned.png';
    Storage::disk('public')->put($path, 'original');
    DB::transaction(fn () => app(GalleryRows::class)->sync($foreign, 'main', [$path], []));
    foreach ([[$path], ['posts/2/main/file.png'], ['posts/1/interior/file.png'], ['posts/1/main/../secret.png'],
        ['posts/1/main/missing.png'], [$path, $path]] as $paths) {
        expect(fn () => DB::transaction(fn () => app(GalleryRows::class)->sync($post, 'main', $paths, [])))
            ->toThrow(ValidationException::class);
    }
    expect(fn () => app(GalleryRows::class)->sync($post, 'main', [], []))->toThrow(LogicException::class)
        ->and(fn () => app(GalleryRows::class)->hydrate($post, 'unknown'))->toThrow(LogicException::class)
        ->and($post->galleries()->count())->toBe(0)
        ->and($foreign->galleries()->count())->toBe(1);
});
