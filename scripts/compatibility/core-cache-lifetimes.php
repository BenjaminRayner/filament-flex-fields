<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogCategory;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\Catalog\RecommendedPlugin;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalCachePayload;
use Bjanczak\FilamentFlexFields\Support\Runtime\OptionalSharedCache;
use Bjanczak\FilamentFlexFields\Support\Runtime\RenderCacheBudget;
use Bjanczak\FilamentFlexFields\Support\Runtime\RenderCacheWeight;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueContract;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
function cacheCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function cacheRejected(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException|LogicException $exception) {
        return;
    }
    throw new RuntimeException('Invalid cache contract was admitted.');
}
final class CacheLifetimeProbeState
{
    public string $tenant = '';
}
final class CacheLifetimeOtherState {}

final class CacheLifetimeCycleState
{
    public function __construct()
    {
        RequestState::resolve(self::class);
    }
}
final class CacheLifetimeThrowState
{
    public function __construct()
    {
        throw new LogicException('deliberate constructor failure');
    }
}
cacheRejected(fn () => RequestState::resolve(CacheLifetimeCycleState::class));
cacheRejected(fn () => RequestState::resolve(CacheLifetimeCycleState::class));
cacheRejected(fn () => RequestState::resolve(CacheLifetimeThrowState::class));
cacheCheck(RequestState::resolve(CacheLifetimeOtherState::class) instanceof CacheLifetimeOtherState, 'Failed construction held unrelated state admission.');
RequestState::resetStandalone();
echo "PASS: recursive construction fails before recursion; exceptions retire admission guards.\n";

$previousContainer = Container::getInstance();
try {
    $bootContainer = new Application;
    Container::setInstance($bootContainer);
    $bootContainer->scoped(CacheLifetimeProbeState::class);
    $rejectedBootCalls = 0;
    cacheRejected(static function () use (&$rejectedBootCalls): void {
        RequestState::configure(CacheLifetimeProbeState::class, static function () use (&$rejectedBootCalls): void {
            $rejectedBootCalls++;
            throw new LogicException('Rejected boot configuration');
        });
    });
    $bootContainer->forgetScopedInstances();
    cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class)->tenant === '' && $rejectedBootCalls === 1, 'Rejected boot callback was retained into the next scope.');
} finally {
    Container::setInstance($previousContainer);
}
echo "PASS: rejected boot callback does not poison a subsequent real Laravel scope.\n";

$cache = new ScopedRenderCache(new RenderCacheBudget(2, 3, 2, 1000, 500));
$calls = 0;
$render = static function () use (&$calls): string {
    $calls++;

    return 'rendered';
};
$cache->remember('A', '1', $render);
$cache->remember('A', '2', $render);
$cache->remember('A', '1', $render);
$cache->remember('A', '3', $render);
cacheCheck($calls === 3 && array_keys($cache->entries('A')) === [1, 3], 'Per-owner LRU did not retain the recently accessed value.');
$cache->remember('B', '1', $render);
$cache->remember('A', '1', $render);
$cache->remember('C', '1', $render);
cacheCheck($cache->entries('B') === [] && $cache->snapshot()['owners'] === 2, 'Least recent owner did not release all its entries.');
$cache->remember('C', '2', $render);
cacheCheck($cache->snapshot()['entries'] === 3 && $cache->snapshot()['evictions'] === 3, 'Global entry budget did not evict the least recent entry.');
$cache->flush('A');
$cache->flush('A');
$cache->flushAll();
cacheCheck($cache->snapshot()['bytes'] === 0 && $cache->snapshot()['entries'] === 0 && $cache->snapshot()['owners'] === 0, 'Flush did not release accounted retention.');
echo "PASS: per-owner, whole-cache and owner-count LRU bounds; hits and idempotent flush.\n";

$cache = new ScopedRenderCache(new RenderCacheBudget(8, 16, 8, 600, 500));
$cache->remember('A', 'one', fn () => str_repeat('x', 200));
$cache->remember('B', 'two', fn () => str_repeat('x', 200));
cacheCheck($cache->snapshot()['entries'] === 1 && $cache->snapshot()['bytes'] <= 600, 'Byte pressure did not evict an old entry.');
$cache->remember('private-tenant', 'private-value', fn () => str_repeat('x', 1000));
$object = new class
{
    public function __serialize(): array
    {
        throw new RuntimeException('Serialization must never run.');
    }
};
cacheCheck($cache->remember('O', 'object', fn () => $object) === $object, 'Uncacheable object changed the return value.');
$cycle = [];
$cycle['self'] = &$cycle;
cacheCheck(RenderCacheWeight::estimate($cycle, 10000) === null, 'Cyclic arrays were not bounded.');
cacheCheck($cache->snapshot()['bypasses'] === 2 && ! str_contains(json_encode($cache->snapshot(), JSON_THROW_ON_ERROR), 'private'), 'Oversized values/objects retained or diagnostics leaked private identities.');
cacheRejected(fn () => $cache->remember("A\0B", 'key', fn () => 'x'));
cacheRejected(fn () => new RenderCacheBudget(bytes: 100, entryBytes: 200));
cacheRejected(fn () => new RenderCacheBudget(owners: 0));
$cache->remember('R', 'reentrant', function () use ($cache): string {
    $cache->remember('R', 'reentrant', fn () => 'inner');

    return 'outer';
});
cacheCheck($cache->remember('R', 'reentrant', fn () => 'bad') === 'outer', 'Reentrant rendering left stale accounting/value.');
echo "PASS: byte pressure, object/large/cyclic bypass without serialization; private-free counters; reentrant admission.\n";

RequestState::resetStandalone();
$outside = RequestState::resolve(CacheLifetimeProbeState::class);
$outside->tenant = 'outside';
$other = RequestState::resolve(CacheLifetimeOtherState::class);
RequestState::resetStandalone(CacheLifetimeOtherState::class);
cacheCheck(RequestState::resolve(CacheLifetimeOtherState::class) !== $other && RequestState::resolve(CacheLifetimeProbeState::class) === $outside, 'Selective standalone reset leaked or cleared another state.');
try {
    RequestState::withStandaloneScope(function () use ($outside): void {
        $state = RequestState::resolve(CacheLifetimeProbeState::class);
        $state->tenant = 'first';
        cacheCheck($state !== $outside, 'Fresh scope reused previous state.');
        RequestState::withStandaloneScope(function () use ($state): void {
            cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class) !== $state, 'Nested scope shared the parent state.');
        });
        cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class) === $state, 'Nested return lost parent identity.');
        throw new RuntimeException('deliberate');
    });
} catch (RuntimeException $exception) {
    cacheCheck($exception->getMessage() === 'deliberate', 'Unexpected scope failure.');
}
cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class) === $outside, 'Exception did not restore the caller scope.');
$left = new Fiber(function (): void {
    RequestState::withStandaloneScope(function (): void {
        RequestState::resolve(CacheLifetimeProbeState::class)->tenant = 'left';
        Fiber::suspend();
        cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class)->tenant === 'left', 'Concurrent Fiber leaked state.');
    });
});
$right = new Fiber(function (): void {
    RequestState::resolve(CacheLifetimeProbeState::class)->tenant = 'right';
    Fiber::suspend();
    cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class)->tenant === 'right', 'Other Fiber reset leaked state.');
    RequestState::resetStandalone();
});
$left->start();
$right->start();
cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class)->tenant === 'outside', 'Fiber changed the synchronous caller.');
$left->resume();
$right->resume();
echo "PASS: selective reset, nested/exception scopes and overlapping Fibers are isolated.\n";

RequestState::withStandaloneScope(function (): void {
    for ($index = 0; $index < 128; $index++) {
        $alias = 'CacheLifetimeAlias'.$index;
        class_alias(CacheLifetimeProbeState::class, $alias);
        RequestState::resolve($alias);
    }
    cacheRejected(fn () => RequestState::resolve(CacheLifetimeOtherState::class));
    RequestState::resetStandalone();
    cacheCheck(RequestState::resolve(CacheLifetimeOtherState::class) instanceof CacheLifetimeOtherState, 'Budget remained exhausted after reset.');
});
$nested = function () use (&$nested): void {
    RequestState::withStandaloneScope($nested);
};
cacheRejected($nested);
cacheCheck(RequestState::resolve(CacheLifetimeProbeState::class) === $outside, 'Rejected nesting corrupted caller state.');
echo "PASS: class and nested scope budgets fail closed and recover after reset/unwind.\n";

$start = hrtime(true);
$memoryBefore = memory_get_usage();
for ($iteration = 0; $iteration < 10000; $iteration++) {
    RequestState::withStandaloneScope(function () use ($iteration): void {
        $cache = RequestState::resolve(ScopedRenderCache::class);
        $cache->remember('tenant-'.$iteration, 'markup', fn () => str_repeat('x', 1000));
        RequestState::resolve(CacheLifetimeProbeState::class)->tenant = (string) $iteration;
    });
}
gc_collect_cycles();
cacheCheck(memory_get_usage() - $memoryBefore < 262144, 'Sequential standalone operation retention grows.');
echo json_encode(['sequentialScopes' => 10000, 'milliseconds' => round((hrtime(true) - $start) / 1e6, 2), 'retainedByteDelta' => memory_get_usage() - $memoryBefore], JSON_THROW_ON_ERROR).PHP_EOL;
RequestState::resetStandalone();
// Actual Laravel scope wins over standalone reset/wrapping; no global worker flush.
require 'scripts/compatibility/core-framework-host.php';
cacheCheck(! app()->resolved(FlexCatalog::class), 'Native boot eagerly constructed catalog-only services.');
$scoped = app(ScopedRenderCache::class);
$scoped->remember('installed', 'key', fn () => 'installed');
RequestState::resetStandalone();
RequestState::withStandaloneScope(fn () => cacheCheck(RequestState::resolve(ScopedRenderCache::class) === $scoped, 'Standalone operation altered Laravel ownership.'));
app()->forgetScopedInstances();
cacheCheck(app(ScopedRenderCache::class) !== $scoped && app(ScopedRenderCache::class)->snapshot()['entries'] === 0, 'Next framework scope reused cached rendered data.');
echo "PASS: actual framework scoped bindings remain authoritative and reset between requests/jobs.\n";

$originalCache = Cache::getFacadeRoot();
try {
    $repository = new Repository(new ArrayStore);
    Cache::swap($repository);
    $optional = new OptionalSharedCache;
    $productions = 0;
    $produce = static function () use (&$productions): array {
        $productions++;

        return ['label' => 'fresh'];
    };
    $accepts = static fn (mixed $value): bool => is_array($value) && ($value['label'] ?? null) === 'fresh';
    $optional->remember('probe.versioned.data', 60, $produce, $accepts);
    $optional->remember('probe.versioned.data', 60, $produce, $accepts);
    cacheCheck($productions === 1 && $optional->snapshot()['hits'] === 1, 'Optional cache did not reuse admitted JSON data.');
    cacheRejected(fn () => $optional->remember('probe.versioned.data', 0, $produce, $accepts));
    foreach (['{"format":999,"value":{}}', '{', ['old' => 'unversioned'], '{"format":1,"value":{"label":"forged"}}'] as $invalid) {
        $repository->put('probe.versioned.data', $invalid, 60);
        cacheCheck($optional->remember('probe.versioned.data', 60, $produce, $accepts) === ['label' => 'fresh'], 'Malformed or incompatible cached data entered the consumer.');
    }
    cacheCheck($optional->snapshot()['rejections'] === 4, 'Invalid cache entries were not accounted.');
    try {
        $optional->remember('probe.producer.failure', 60, static fn () => throw new LogicException('producer failure'), $accepts);
        throw new RuntimeException('Producer exception was swallowed.');
    } catch (LogicException $exception) {
        cacheCheck($exception->getMessage() === 'producer failure' && ! $optional->snapshot()['unavailable'], 'Producer exception was classified as cache transport failure.');
    }
    $neverSerialize = new class
    {
        public function jsonSerialize(): mixed
        {
            throw new RuntimeException('Must never invoke object serialization.');
        }
    };
    cacheCheck(OptionalCachePayload::encode($neverSerialize) === null, 'Optional acceleration admitted object payloads.');
    $cyclic = [];
    $cyclic['self'] = &$cyclic;
    cacheCheck(OptionalCachePayload::encode($cyclic) === null, 'Optional JSON cycle exceeded bounded traversal.');
    $nullable = static fn (mixed $value): bool => $value === null;
    $optional->remember('probe.nullable', 60, static fn () => null, $nullable);
    cacheCheck($optional->remember('probe.nullable', 60, static fn () => throw new LogicException('cached null lost'), $nullable) === null, 'Cached null was treated as absence.');
    echo "PASS: optional cache hit, corrupt/legacy/versioned rejection, null preservation, argument admission, bounded objects/cycles and producer failure separation.\n";

    $failedStore = new class extends ArrayStore
    {
        public int $attempts = 0;

        public function many(array $keys): array
        {
            $this->attempts++;
            throw new RuntimeException('Unavailable cache transport.');
        }
    };
    Cache::swap(new Repository($failedStore));
    app()->forgetScopedInstances();
    $failed = app(OptionalSharedCache::class);
    for ($operation = 0; $operation < 20; $operation++) {
        cacheCheck($failed->remember('probe.unavailable', 60, $produce, $accepts) === ['label' => 'fresh'], 'Cache failure prevented normal production.');
    }
    cacheCheck($failedStore->attempts === 1 && $failed->snapshot()['failures'] === 1, 'Unavailable backend was retried repeatedly in the same scope.');
    Cache::swap($repository);
    app()->forgetScopedInstances();
    $recovered = app(OptionalSharedCache::class);
    cacheCheck($recovered !== $failed && ! $recovered->snapshot()['unavailable'], 'Next request inherited optional cache failure state.');
    cacheCheck($recovered->remember('probe.versioned.data', 60, $produce, $accepts) === ['label' => 'fresh'] && $recovered->snapshot()['hits'] === 1, 'Next request did not recover shared cache access.');
    echo "PASS: unavailable optional backend falls back once per scope; later framework scopes recover.\n";
} finally {
    Cache::swap($originalCache);
    app()->forgetScopedInstances();
}

$catalog = app(FlexCatalog::class);
$snapshot = $catalog->entries();
cacheRejected(fn () => $catalog->registerPlugin(new RecommendedPlugin(
    'late-worker-mutation', 'Late', 'Must reject outside provider boot.', 'Probe', 'https://example.com/package',
    CatalogCategory::Controls,
)));
cacheCheck($catalog->entries() === $snapshot, 'Rejected registration changed process catalog metadata.');
cacheRejected(fn () => app(ComponentRegistry::class)->register(
    new ComponentDefinition('late-worker-component', ComponentKind::Display),
));
$scopes = (new ReflectionProperty(Container::class, 'scopedInstances'))->getValue(app());
$inventory = json_decode(file_get_contents('.internal/core-architecture-inventory.json'), true, 32, JSON_THROW_ON_ERROR);
foreach ($inventory['serviceBindings'] as $binding) {
    cacheCheck(app()->bound($binding['service']), 'Declared core binding is missing from the real container.');
    cacheCheck(in_array($binding['service'], $scopes, true) === ($binding['lifetime'] === 'request-or-job'), 'Actual service lifetime differs from the inventory.');
}
echo 'PASS: native boot freezes component/catalog mutation; actual '.count($inventory['serviceBindings'])." service lifetimes match their source inventory.\n";

// Laravel owns listener registration; FLEX only scopes the recursive-delivery guard.
$events = app(Dispatcher::class);
$delivered = 0;
$events->listen(FieldMounted::class, static function (FieldMounted $event) use (&$delivered): void {
    $delivered++;
    app(ActivityDispatcher::class)->dispatch($event);
});
$firstActivity = app(ActivityDispatcher::class);
$firstActivity->dispatch(new FieldMounted('probe', 'select'));
cacheCheck($delivered === 1, 'Recursive activity listener was delivered more than once.');
app()->forgetScopedInstances();
cacheCheck(app(ActivityDispatcher::class) !== $firstActivity, 'Activity guard leaked to the next scope.');
app(ActivityDispatcher::class)->dispatch(new FieldMounted('probe', 'select'));
cacheCheck($delivered === 2, 'Laravel boot listener did not survive the next scope.');
$events->forget(FieldMounted::class);
echo "PASS: native Laravel activity delivery, recursion isolation and per-scope guard disposal.\n";

$referenceCache = new ScopedRenderCache;
$text = 'small';
$referenceCache->remember('reference', 'entry', static function () use (&$text): array {
    return ['nested' => ['text' => &$text]];
});
$accounting = $referenceCache->snapshot()['bytes'];
$text = str_repeat('x', 8 * 1024 * 1024);
$hit = $referenceCache->remember('reference', 'entry', static fn (): null => null);
cacheCheck($hit === ['nested' => ['text' => 'small']], 'Caller reference mutated retained payload.');
$hit['nested']['text'] = 'changed hit';
$export = $referenceCache->entries('reference');
$export['entry']['nested']['text'] = 'changed export';
cacheCheck($referenceCache->remember('reference', 'entry', static fn (): null => null)['nested']['text'] === 'small', 'Returned arrays mutated retained payload.');
cacheCheck($referenceCache->snapshot()['bytes'] === $accounting, 'Detached payload changed byte accounting.');
$rule = 'required';
$contract = new FieldValueContract('title', [&$rule], nestedRules: ['text' => [&$rule]]);
$rule = 'nullable';
cacheCheck($contract->rules === ['required'] && $contract->nestedRules === ['text' => ['required']], 'Trusted rule arrays retained caller references.');
echo "PASS: retained/returned array snapshots and nested rule contracts detach caller reference cells.\n";
