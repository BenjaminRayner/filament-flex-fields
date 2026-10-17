<?php

namespace App\Filament\Pages;

use App\Models\Post;
use App\Support\GalleryFields;
use App\Support\GalleryRows;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class PostGallery extends Page
{
    protected static ?string $slug = 'post-gallery/{post?}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.post-gallery';

    #[Locked]
    public ?int $postId = null;

    public array $data = [];

    private const GALLERIES = [
        'gallery_main' => 'main',
        'gallery_interior' => 'interior',
    ];

    public function mount(?int $post = null): void
    {
        $this->postId = $post;
        $record = $this->authorizedPost();
        $data = ['title' => $record?->title ?? ''];
        foreach (self::GALLERIES as $state => $collection) {
            $items = $record === null ? ['paths' => [], 'names' => []]
                : app(GalleryRows::class)->hydrate($record, $collection);
            $data[$state] = $items['paths'];
            $data[$state.'__names'] = $items['names'];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->authorizedPost();

        return $schema
            ->model($record ?? Post::class)
            ->operation($record === null ? 'create' : 'edit')
            ->statePath('data')
            ->columns(2)
            ->components([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                $this->galleryField('gallery_main', 'main')->label('Main photos'),
                $this->galleryField('gallery_interior', 'interior')->label('Interior photos'),
            ]);
    }

    private function galleryField(string $state, string $collection): FlexImageUpload
    {
        return GalleryFields::make($state, $collection);
    }

    public function save(): void
    {
        $record = $this->authorizedPost();
        $data = $this->form->getState();

        $post = DB::transaction(function () use ($record, $data): Post {
            $post = $record === null
                ? new Post
                : Post::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($post->exists) {
                Gate::authorize('update', $post);
            } else {
                Gate::authorize('create', Post::class);
                $post->user_id = Auth::id();
            }

            $post->title = $data['title'];
            $post->save();

            foreach (self::GALLERIES as $state => $collection) {
                app(GalleryRows::class)->sync(
                    $post, $collection,
                    array_values($data[$state] ?? []),
                    $data[$state.'__names'] ?? [],
                );
            }

            $this->form->model($post);
            foreach (array_keys(self::GALLERIES) as $state) {
                $this->form->getComponent($state)->completeMediaUploadsAfterSave($post);
            }

            return $post;
        });

        Notification::make()->title('Gallery saved')->success()->send();
        $this->redirect(static::getUrl(['post' => $post->getKey()]), navigate: true);
    }

    private function authorizedPost(): ?Post
    {
        abort_unless(Auth::check(), 403);

        if ($this->postId === null) {
            Gate::authorize('create', Post::class);

            return null;
        }

        $post = Post::query()->findOrFail($this->postId);
        Gate::authorize('update', $post);

        return $post;
    }
}
