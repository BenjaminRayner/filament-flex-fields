<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('galleries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('collection', 32);
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('original_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['post_id', 'collection', 'disk', 'path']);
            $table->index(['post_id', 'collection', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('posts');
    }
};
