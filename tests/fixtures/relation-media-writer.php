<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaEnvironment;
use Bjanczak\FilamentFlexFields\Tests\Support\RelationMediaPost;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Facade;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once dirname(__DIR__).'/Support/RelationMediaEnvironment.php';

$config = json_decode(getenv('FFF_MEDIA_RELATION_SQL') ?: '{}', true, 16, JSON_THROW_ON_ERROR);
if (! in_array($config['driver'] ?? null, ['mysql', 'pgsql'], true) || ($config['host'] ?? null) !== '127.0.0.1'
    || ! preg_match('/^fff_core_probe_[a-z0-9_]+$/D', $config['database'] ?? '')
    || ! preg_match('/^r4_[a-f0-9]{8}_$/D', $config['prefix'] ?? '')) {
    throw new RuntimeException('This fixture requires an explicit disposable loopback SQL connection.');
}
$app = new Container;
$app->instance('config', new Repository(['filesystems' => ['disks' => ['relation-private' => [
    'driver' => 'local', 'root' => getenv('FFF_MEDIA_RELATION_FILES'), 'visibility' => 'private',
]]]]));
$capsule = new Manager($app);
$capsule->addConnection($config, 'r4sql');
$capsule->getDatabaseManager()->setDefaultConnection('r4sql');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$app->instance('filesystem', new FilesystemManager($app));
Facade::setFacadeApplication($app);
$db = $capsule->getConnection('r4sql');
$db->statement($config['driver'] === 'mysql' ? 'SET SESSION innodb_lock_wait_timeout=5' : 'SET statement_timeout=5000');
[$script, $mode, $id, $revision, $path] = $argv;
try {
    $db->transaction(function () use ($mode, $id, $revision, $path): void {
        $post = RelationMediaPost::findOrFail($id);
        $collection = RelationMediaEnvironment::collection(admitted: [$path]);
        echo "attempting\n";
        flush();
        $collection->sync($post, [$path], [], $revision);
        if ($mode === 'commit' || $mode === 'rollback') {
            echo "held\n";
            flush();
            if (fgets(STDIN) !== "release\n") {
                throw new RuntimeException('The writer barrier was not released.');
            }
        }
        if ($mode === 'rollback') {
            throw new RuntimeException('controlled_rollback');
        }
    });
    $result = ['status' => 'committed'];
} catch (MediaFailure $error) {
    $result = ['status' => $error->reason];
} catch (Throwable $error) {
    $result = ['status' => $error->getMessage() === 'controlled_rollback' ? 'rolled_back' : 'error', 'type' => $error::class];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
exit(in_array($result['status'], ['committed', 'rolled_back', 'relation_conflict'], true) ? 0 : 1);
