<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaEnvironment;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaPost;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

uses(TestCase::class);

it('serializes actual independent SQL writers and preserves the correct result after commit or rollback', function (bool $rollback): void {
    $driver = getenv('FFF_DATA_DRIVER');
    $database = getenv('FFF_DATA_DATABASE');
    $port = filter_var(getenv('FFF_DATA_PORT'), FILTER_VALIDATE_INT);
    if (! in_array($driver, ['mysql', 'pgsql'], true) || ! is_string($database)
        || ! preg_match('/^fff_core_probe_[a-z0-9_]+$/D', $database) || $port === false || $port < 1024 || $port > 65535) {
        throw new RuntimeException('Opt-in test requires a disposable fff_core_probe_* loopback SQL database and explicit port.');
    }
    $configuration = ['driver' => $driver, 'host' => '127.0.0.1', 'port' => $port, 'database' => $database,
        'username' => getenv('FFF_DATA_USERNAME'), 'password' => getenv('FFF_DATA_PASSWORD'),
        'prefix' => 'r4_'.bin2hex(random_bytes(4)).'_', 'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8'];
    config()->set('database.connections.r4sql', $configuration);
    DB::setDefaultConnection('r4sql');
    RelationMediaEnvironment::configure();
    $first = null;
    $second = null;
    try {
        $post = RelationMediaPost::create();
        $a = RelationMediaEnvironment::file($post);
        $b = RelationMediaEnvironment::file($post, 'b.txt');
        $revision = RelationMediaEnvironment::collection()->read($post)->revision;
        $environment = ['FFF_MEDIA_RELATION_SQL' => json_encode($configuration, JSON_THROW_ON_ERROR),
            'FFF_MEDIA_RELATION_FILES' => Storage::disk('relation-private')->path('')];
        $fixture = dirname(__DIR__).'/fixtures/relation-media-writer.php';
        $input = new InputStream;
        $first = new Process([PHP_BINARY, $fixture, $rollback ? 'rollback' : 'commit', (string) $post->getKey(), $revision, $a], env: $environment, timeout: 15);
        $first->setInput($input);
        $first->start();
        expect($first->waitUntil(fn ($type, string $output): bool => str_contains($output, "held\n")))->toBeTrue();
        $second = new Process([PHP_BINARY, $fixture, 'contend', (string) $post->getKey(), $revision, $b], env: $environment, timeout: 15);
        $second->start();
        expect($second->waitUntil(fn ($type, string $output): bool => str_contains($output, "attempting\n")))->toBeTrue();
        usleep(150000);
        expect($second->isRunning())->toBeTrue()->and($second->getOutput())->not->toContain('"status"');
        $input->write("release\n");
        $input->close();
        $first->wait();
        $second->wait();
        expect($first->getExitCode())->toBe(0, $first->getErrorOutput().' '.$first->getOutput())
            ->and($second->getExitCode())->toBe(0, $second->getErrorOutput().' '.$second->getOutput())
            ->and($first->getOutput())->toContain('"status":"'.($rollback ? 'rolled_back' : 'committed').'"')
            ->and($second->getOutput())->toContain('"status":"'.($rollback ? 'committed' : 'relation_conflict').'"')
            ->and(RelationMediaEnvironment::collection()->read($post)->paths)->toBe([$rollback ? $b : $a])
            ->and($post->galleries()->count())->toBe(1);
        Storage::disk('relation-private')->assertExists($a);
        Storage::disk('relation-private')->assertExists($b);
    } finally {
        $first?->stop(0.5);
        $second?->stop(0.5);
        Schema::dropIfExists('relation_media_images');
        Schema::dropIfExists('relation_media_posts');
        DB::purge('r4sql');
        DB::setDefaultConnection('testing');
    }
})->with([false, true]);
