<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Bjanczak\FilamentFlexFields\Support\Media\Relations\HasManyMediaCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class RelationMediaEnvironment
{
    public static function configure(): void
    {
        Schema::create('relation_media_posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('owner_id')->default(1);
            $table->string('title')->default('Synthetic post');
            $table->timestamps();
        });
        Schema::create('relation_media_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('relation_media_posts')->cascadeOnDelete();
            $table->string('collection', 64);
            $table->string('disk', 128);
            $table->string('path', 512);
            $table->string('original_name');
            $table->unsignedInteger('sort_order');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });
        Storage::fake('relation-private');
    }

    /** @param list<string> $admitted */
    public static function collection(string $collection = 'main', array $admitted = []): HasManyMediaCollection
    {
        return new HasManyMediaCollection(relation: 'galleries', disk: 'relation-private', collection: $collection,
            directory: static fn (Model $post): string => 'posts/'.$post->getKey().'/'.$collection,
            authorize: static fn (Model $post, string $operation): bool => $post->getAttribute('owner_id') === 1,
            admit: static fn (Model $post, string $path, ?Model $existing): bool => $existing !== null || in_array($path, $admitted, true),
        );
    }

    public static function file(Model $post, string $name = 'a.txt', string $collection = 'main'): string
    {
        $path = 'posts/'.$post->getKey().'/'.$collection.'/'.$name;
        Storage::disk('relation-private')->put($path, 'Synthetic file bytes');

        return $path;
    }
}

class RelationMediaPost extends Model
{
    protected $table = 'relation_media_posts';

    protected $guarded = [];

    public function galleries(): HasMany
    {
        return $this->hasMany(RelationMediaImage::class, 'post_id');
    }

    public function oneImage(): HasOne
    {
        return $this->hasOne(RelationMediaImage::class, 'post_id');
    }

    public function limitedImages(): HasMany
    {
        return $this->galleries()->limit(1);
    }

    public function softImages(): HasMany
    {
        return $this->hasMany(RelationSoftMediaImage::class, 'post_id');
    }

    public function externalImages(): HasMany
    {
        return $this->hasMany(RelationExternalMediaImage::class, 'post_id');
    }
}

class RelationMediaImage extends Model
{
    protected $table = 'relation_media_images';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(RelationMediaConversion::class, 'image_id');
    }
}

class RelationMediaConversion extends Model
{
    protected $table = 'relation_media_conversions';

    protected $guarded = [];
}

class RelationSoftMediaImage extends RelationMediaImage
{
    use SoftDeletes;
}

class RelationExternalMediaImage extends RelationMediaImage
{
    protected $connection = 'relation-other';
}
