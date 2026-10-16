<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadEventType;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('stores three independent galleries, accepts their relation references and restores them on a fresh form', function () {
    Storage::fake('public');
    Schema::create('upload_recipe_posts', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('upload_recipe_gallery', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->string('collection');
        $table->string('disk');
        $table->string('path');
        $table->string('original_name');
        $table->unsignedInteger('sort_order');
        $table->unique(['post_id', 'collection', 'disk', 'path']);
    });
    $accepted = [];
    $schema = [];
    foreach (['main', 'interior', 'documents'] as $collection) {
        $schema[] = FlexFileUpload::make('gallery_'.$collection)->multiple()->disk('public')->directory('posts/'.$collection)
            ->storeFileNamesIn('gallery_'.$collection.'__names')
            ->preventFilePathTampering(allowFilePathUsing: fn (?UploadRecipePost $record, string $file): bool => $record?->exists
                && DB::table('upload_recipe_gallery')->where('post_id', $record->getKey())->where('collection', $collection)->where('disk', 'public')->where('path', $file)->exists())
            ->mediaReferenceUsing(fn (UploadRecipePost $record, string $identifier): bool => DB::table('upload_recipe_gallery')
                ->where('post_id', $record->getKey())->where('collection', $collection)->where('disk', 'public')->where('path', $identifier)->exists())
            ->onUploadEvent(UploadEventType::Accepted, function () use (&$accepted, $collection): void {
                $accepted[] = $collection;
            });
    }
    TestableTranslatableForm::$formSchema = $schema;
    $form = Livewire::test(TestableTranslatableForm::class);
    foreach (['main', 'interior', 'documents'] as $collection) {
        $form->upload('data.gallery_'.$collection.'.'.Str::uuid(), [UploadedFile::fake()->create($collection.'.txt', 1, 'text/plain')]);
    }
    $state = $form->instance()->getSchema('form')->getState();
    $post = UploadRecipePost::create();
    DB::transaction(function () use ($state, $post, $form): void {
        foreach (['main', 'interior', 'documents'] as $collection) {
            $fieldName = 'gallery_'.$collection;
            foreach ($state[$fieldName] as $position => $path) {
                DB::table('upload_recipe_gallery')->insert(['post_id' => $post->getKey(), 'collection' => $collection, 'disk' => 'public',
                    'path' => $path, 'original_name' => $state[$fieldName.'__names'][$path], 'sort_order' => $position]);
            }
        }
        $schema = $form->instance()->getSchema('form')->model($post);
        foreach (['main', 'interior', 'documents'] as $collection) {
            $schema->getComponent('gallery_'.$collection)->completeMediaUploadsAfterSave($post);
        }
    });
    expect($accepted)->toEqualCanonicalizing(['main', 'interior', 'documents']);
    $restore = [];
    foreach (['main', 'interior', 'documents'] as $collection) {
        $rows = DB::table('upload_recipe_gallery')->where('post_id', $post->getKey())->where('collection', $collection)->orderBy('sort_order')->get();
        $restore['gallery_'.$collection] = $rows->pluck('path')->all();
        $restore['gallery_'.$collection.'__names'] = $rows->pluck('original_name', 'path')->all();
    }
    $fresh = Livewire::test(TestableTranslatableForm::class, ['record' => $post])->fillForm($restore);
    foreach (['main', 'interior', 'documents'] as $collection) {
        $field = $fresh->instance()->getSchema('form')->getComponent('gallery_'.$collection);
        $files = array_values($field->getUploadedFiles());
        expect($files)->toHaveCount(1)->and($files[0]['name'])->toBe($collection.'.txt');
        Storage::disk('public')->assertExists($restore['gallery_'.$collection][0]);
    }
    $foreign = Livewire::test(TestableTranslatableForm::class, ['record' => UploadRecipePost::create()]);
    $foreign->instance()->data['gallery_main'] = ['forged' => $restore['gallery_main'][0]];
    $field = $foreign->instance()->getSchema('form')->getComponent('gallery_main')->fileClickUrl(fn () => '/viewer');
    expect(fn () => $field->activateUploadedFile('forged'))->toThrow(MediaFailure::class);
});

class UploadRecipePost extends Model
{
    protected $table = 'upload_recipe_posts';

    protected $guarded = [];

    public $timestamps = false;
}
