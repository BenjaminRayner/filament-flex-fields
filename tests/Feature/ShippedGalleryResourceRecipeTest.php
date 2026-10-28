<?php

declare(strict_types=1);

use App\Filament\Resources\PostResource;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Post;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Staging\UploadLeaseRepository;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Storage\UploadManifestRepository;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

class GalleryRecipePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $root = dirname(__DIR__, 2).'/resources/recipes/post-gallery/';
        foreach (['app/Models/Post.php', 'app/Models/Gallery.php', 'app/Support/GalleryRows.php',
            'app/Support/GalleryFileName.php', 'app/Support/GalleryFields.php',
            'app/Filament/Resources/PostResource.php', 'app/Filament/Resources/PostResource/Pages/CreatePost.php',
            'app/Filament/Resources/PostResource/Pages/EditPost.php', 'app/Filament/Resources/PostResource/Pages/ListPosts.php'] as $file) {
            require_once $root.$file;
        }

        return $panel->id('gallery-recipe')->path('gallery-recipe')->default()->resources([PostResource::class]);
    }
}

trait WithGalleryRecipePanel
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), GalleryRecipePanelProvider::class];
    }
}

uses(WithGalleryRecipePanel::class);

beforeEach(function (): void {
    Schema::create('users', fn (Blueprint $table) => $table->id());
    DB::table('users')->insert([['id' => 1], ['id' => 2]]);
    (require dirname(__DIR__, 2).'/resources/recipes/post-gallery/database/migrations/2026_10_17_000001_create_posts_and_galleries.php')->up();
    Storage::fake('public');
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Synthetic owner']);
    $user->exists = true;
    $this->actingAs($user);
    Gate::before(fn ($user, string $ability, array $arguments): ?bool => match ($ability) {
        'create', 'viewAny' => ($arguments[0] ?? null) === Post::class ? true : null,
        'update' => ($arguments[0] ?? null) instanceof Post ? (int) $arguments[0]->user_id === (int) $user->getKey() : null,
        default => null,
    });
    Filament::setCurrentPanel(Filament::getPanel('gallery-recipe'));
    view()->share('errors', new ViewErrorBag);
});

it('uses actual native Resource create/edit saves and restores its related galleries without JSON attributes', function (): void {
    $create = Livewire::test(CreatePost::class)->fillForm(['title' => 'Resource gallery']);
    $create->upload('data.gallery_main.'.Str::uuid(), [UploadedFile::fake()->image('main.png', 300, 180)]);
    $create->upload('data.gallery_interior.'.Str::uuid(), [UploadedFile::fake()->image('interior.png', 300, 180)]);
    $create->call('create')->assertHasNoFormErrors();
    $post = Post::query()->sole();
    expect($post->user_id)->toBe(1)->and($post->galleries()->count())->toBe(2)
        ->and(Schema::hasColumn('posts', 'gallery_main'))->toBeFalse();

    $edit = Livewire::test(EditPost::class, ['record' => $post->getRouteKey()]);
    $restored = $edit->get('data.gallery_main');
    expect(array_values($restored))->toBe($post->galleries()->where('collection', 'main')->pluck('path')->all());
    $edit->fillForm(['title' => 'Edited in Resource'])->call('save')->assertHasNoFormErrors();
    expect($post->fresh()->title)->toBe('Edited in Resource')->and($post->galleries()->count())->toBe(2);
    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertFormSet(['title' => 'Edited in Resource']);
    foreach ($post->galleries as $row) {
        Storage::disk('public')->assertExists($row->path);
        $manifest = app(UploadManifestRepository::class)->read($row->disk, $row->path);
        $lease = $manifest?->context['metadata']['flex_upload_lease'] ?? null;
        expect($lease)->toBeString()
            ->and(app(UploadLeaseRepository::class)->read($lease)?->accepted)->toBeTrue();
    }
});

it('rejects another owners record and rolls back a Resource save after a controlled failure', function (): void {
    $foreign = new Post(['title' => 'Other owner']);
    $foreign->user_id = 2;
    $foreign->save();
    try {
        Livewire::test(EditPost::class, ['record' => $foreign->getRouteKey()])->assertNotFound();
    } catch (ModelNotFoundException $exception) {
        // Older Livewire test brokers propagate this exception instead of an HTTP response.
        expect($exception->getModel())->toBe(Post::class);
    }
    expect($foreign->fresh()->title)->toBe('Other owner')
        ->and(DB::table('galleries')->count())->toBe(0);

    Post::saved(function (Post $post): void {
        if ($post->title === 'Controlled failure') {
            throw new RuntimeException('Controlled Resource save failure');
        }
    });
    $create = Livewire::test(CreatePost::class)->fillForm(['title' => 'Controlled failure']);
    $create->upload('data.gallery_main.'.Str::uuid(), [UploadedFile::fake()->image('retry.png', 300, 180)]);
    expect(fn () => $create->call('create'))->toThrow(RuntimeException::class, 'Controlled Resource save failure');
    expect(Post::query()->count())->toBe(1)->and(DB::table('galleries')->count())->toBe(0);
});
